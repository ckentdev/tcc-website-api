<?php

namespace App\Console\Commands;

use App\Services\Assistant\HandbookChunker;
use App\Services\Assistant\OpenAiClient;
use Illuminate\Console\Command;
use RuntimeException;

class IndexHandbookForAssistant extends Command
{
    protected $signature = 'assistant:index-handbook
                            {--skip-embeddings : Build chunks and RAG markdown only (keyword search fallback)}
                            {--from-rag : Chunk from handbook-rag.md instead of re-parsing the full handbook}';

    protected $description = 'Build handbook RAG chunks and optional OpenAI embeddings for the TCC AI Assistant';

    public function handle(HandbookChunker $chunker): int
    {
        $this->info('Chunking student handbook…');

        $chunks = $this->option('from-rag')
            ? $chunker->chunkFromRagMarkdown()
            : $chunker->chunkFromSource();

        if ($chunks === []) {
            $this->error('No handbook chunks were produced. Check handbook-student.md.');

            return self::FAILURE;
        }

        $ragPath = $chunker->writeRagMarkdown($chunks);
        $this->info('Wrote '.$ragPath.' ('.count($chunks).' sections)');

        $hasEmbeddings = false;
        $indexed = $chunks;

        if (! $this->option('skip-embeddings')) {
            try {
                $client = OpenAiClient::fromConfig();
                $model = (string) config('assistant.embedding_model');
                $bar = $this->output->createProgressBar(count($chunks));
                $bar->start();

                foreach ($indexed as $i => $chunk) {
                    $input = $chunk['chapter']."\n".$chunk['title']."\n".$chunk['text'];
                    $indexed[$i]['embedding'] = $client->embed(mb_substr($input, 0, 6000), $model);
                    $bar->advance();
                    usleep(80_000);
                }

                $bar->finish();
                $this->newLine();
                $hasEmbeddings = true;
                $this->info('Embeddings created with '.$model);
            } catch (RuntimeException $e) {
                $this->warn($e->getMessage());
                $this->warn('Continuing without embeddings (keyword search fallback).');
            }
        }

        $indexPath = (string) config('assistant.index_path');
        $dir = dirname($indexPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($indexPath, json_encode([
            'version' => 1,
            'generated_at' => now()->toIso8601String(),
            'embedding_model' => config('assistant.embedding_model'),
            'has_embeddings' => $hasEmbeddings,
            'chunk_count' => count($indexed),
            'chunks' => $indexed,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('Index saved to '.$indexPath);

        return self::SUCCESS;
    }
}
