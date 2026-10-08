<?php

namespace App\Services\Assistant;

class HandbookRetriever
{
    /** @var list<array{id: string, chapter: string, title: string, text: string, url: ?string, embedding?: list<float>}> */
    private array $chunks = [];

    private bool $hasEmbeddings = false;

    public function __construct(
        private readonly ?OpenAiClient $openAi = null,
    ) {
        $this->loadIndex();
    }

    public function isReady(): bool
    {
        return $this->chunks !== [];
    }

    /**
     * @return list<array{id: string, chapter: string, title: string, text: string, url: ?string, score: float}>
     */
    public function retrieve(string $query, ?int $topK = null): array
    {
        $topK ??= (int) config('assistant.retrieval_top_k');
        if ($this->chunks === []) {
            return [];
        }

        if ($this->hasEmbeddings && $this->openAi !== null) {
            return $this->retrieveByEmbedding($query, $topK);
        }

        return $this->retrieveByKeywords($query, $topK);
    }

    /**
     * @return list<array{id: string, chapter: string, title: string, text: string, url: ?string, score: float}>
     */
    private function retrieveByEmbedding(string $query, int $topK): array
    {
        try {
            $vector = $this->openAi->embed($query);
        } catch (\Throwable) {
            return $this->retrieveByKeywords($query, $topK);
        }
        $scored = [];

        foreach ($this->chunks as $chunk) {
            if (! isset($chunk['embedding']) || ! is_array($chunk['embedding'])) {
                continue;
            }
            $scored[] = [
                'id' => $chunk['id'],
                'chapter' => $chunk['chapter'],
                'title' => $chunk['title'],
                'text' => $chunk['text'],
                'url' => $chunk['url'] ?? null,
                'score' => $this->cosineSimilarity($vector, $chunk['embedding']),
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $topK);
    }

    /**
     * @return list<array{id: string, chapter: string, title: string, text: string, url: ?string, score: float}>
     */
    private function retrieveByKeywords(string $query, int $topK): array
    {
        $terms = array_values(array_filter(
            preg_split('/\s+/', strtolower($query)) ?: [],
            fn ($t) => strlen($t) >= 3,
        ));

        $scored = [];
        foreach ($this->chunks as $chunk) {
            $hay = strtolower($chunk['chapter'].' '.$chunk['title'].' '.$chunk['text']);
            $score = 0.0;
            foreach ($terms as $term) {
                $score += substr_count($hay, $term) * 1.0;
                if (str_contains($chunk['title'], $term)) {
                    $score += 2.5;
                }
            }
            if ($score > 0) {
                $scored[] = [
                    'id' => $chunk['id'],
                    'chapter' => $chunk['chapter'],
                    'title' => $chunk['title'],
                    'text' => $chunk['text'],
                    'url' => $chunk['url'] ?? null,
                    'score' => $score,
                ];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        if ($scored === [] && $this->chunks !== []) {
            return array_map(fn ($c) => [
                'id' => $c['id'],
                'chapter' => $c['chapter'],
                'title' => $c['title'],
                'text' => $c['text'],
                'url' => $c['url'] ?? null,
                'score' => 0.0,
            ], array_slice($this->chunks, 0, min(2, $topK)));
        }

        return array_slice($scored, 0, $topK);
    }

    private function loadIndex(): void
    {
        $path = (string) config('assistant.index_path');
        if (! is_readable($path)) {
            return;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || ! isset($data['chunks']) || ! is_array($data['chunks'])) {
            return;
        }

        $this->chunks = $data['chunks'];
        $this->hasEmbeddings = (bool) ($data['has_embeddings'] ?? false);
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $len = min(count($a), count($b));

        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
