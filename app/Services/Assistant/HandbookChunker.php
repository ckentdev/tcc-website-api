<?php

namespace App\Services\Assistant;

class HandbookChunker
{
    public function __construct(
        private readonly HandbookLinkResolver $links,
    ) {}

    /**
     * @return list<array{id: string, chapter: string, title: string, text: string, url: ?string}>
     */
    public function chunkFromSource(?string $sourcePath = null): array
    {
        $sourcePath ??= (string) config('assistant.source_markdown');
        if (! is_readable($sourcePath)) {
            throw new \RuntimeException("Handbook source not found: {$sourcePath}");
        }

        $body = $this->extractHandbookBody((string) file_get_contents($sourcePath));

        return $this->chunkBody($body);
    }

    public function writeRagMarkdown(array $chunks, ?string $targetPath = null): string
    {
        $targetPath ??= (string) config('assistant.rag_markdown');
        $lines = [
            '# Tagoloan Community College Student Handbook (RAG)',
            '',
            'Structured excerpt for the TCC AI Assistant. Auto-generated from `handbook-student.md`.',
            'Re-run `php artisan assistant:index-handbook` after handbook updates.',
            '',
        ];

        $currentChapter = '';
        foreach ($chunks as $chunk) {
            if ($chunk['chapter'] !== $currentChapter) {
                $currentChapter = $chunk['chapter'];
                $lines[] = '## '.$currentChapter;
                $lines[] = '';
            }
            $lines[] = '### '.$chunk['title'];
            $lines[] = '';
            $lines[] = trim($chunk['text']);
            $lines[] = '';
        }

        $markdown = implode("\n", $lines);
        file_put_contents($targetPath, $markdown);

        return $targetPath;
    }

    /**
     * @return list<array{id: string, chapter: string, title: string, text: string, url: ?string}>
     */
    public function chunkFromRagMarkdown(?string $ragPath = null): array
    {
        $ragPath ??= (string) config('assistant.rag_markdown');
        if (! is_readable($ragPath)) {
            return $this->chunkFromSource();
        }

        $raw = (string) file_get_contents($ragPath);
        $chunks = [];
        $chapter = 'Student Handbook';
        $currentTitle = null;
        $buffer = [];

        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            if (preg_match('/^##\s+(.+)$/', $line, $m)) {
                $chapter = trim($m[1]);
                continue;
            }
            if (preg_match('/^###\s+(.+)$/', $line, $m)) {
                if ($currentTitle !== null) {
                    $chunks[] = $this->makeChunk($chapter, $currentTitle, implode("\n", $buffer));
                }
                $currentTitle = trim($m[1]);
                $buffer = [];
                continue;
            }
            if ($currentTitle !== null) {
                $buffer[] = $line;
            }
        }

        if ($currentTitle !== null) {
            $chunks[] = $this->makeChunk($chapter, $currentTitle, implode("\n", $buffer));
        }

        return $chunks;
    }

    private function extractHandbookBody(string $markdown): string
    {
        if (preg_match('/\*\*CHAPTER 1\.\s+THE TAGOLOAN COMMUNITY COLLEGE\*\*/', $markdown, $m, PREG_OFFSET_CAPTURE)) {
            $first = $m[0][1];
            $rest = substr($markdown, $first + strlen($m[0][0]));
            if (preg_match('/\*\*CHAPTER 1\.\s+THE TAGOLOAN COMMUNITY COLLEGE\*\*/', $rest, $m2, PREG_OFFSET_CAPTURE)) {
                return substr($rest, $m2[0][1]);
            }
        }

        return $markdown;
    }

    /**
     * @return list<array{id: string, chapter: string, title: string, text: string, url: ?string}>
     */
    private function chunkBody(string $body): array
    {
        $chunks = [];
        $parts = preg_split('/(?=^\*\*CHAPTER\s)/m', $body) ?: [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $chapter = 'Student Handbook';
            if (preg_match('/^\*\*(CHAPTER[^*]+)\*\*/m', $part, $cm)) {
                $chapter = trim($cm[1]);
            }

            $sections = preg_split('/(?=^Section\s+\d+\.)/m', $part) ?: [];
            $chapterIntro = trim($sections[0] ?? '');
            if ($chapterIntro !== '' && ! preg_match('/^Section\s+\d+\./', $chapterIntro)) {
                $intro = preg_replace('/^\*\*CHAPTER[^*]+\*\*/', '', $chapterIntro);
                $intro = trim($intro);
                if ($intro !== '') {
                    $chunks = array_merge($chunks, $this->splitLargeChunk($chapter, $chapter.' overview', $intro));
                }
            }

            foreach ($sections as $section) {
                $section = trim($section);
                if ($section === '' || ! preg_match('/^Section\s+(\d+)\.\s*(.+)$/m', $section, $sm)) {
                    continue;
                }

                $title = trim(preg_replace('/\s+\d+$/', '', $sm[2]) ?? $sm[2]);
                $content = trim(preg_replace('/^Section\s+\d+\.\s*.+$/m', '', $section, 1) ?? $section);
                $chunks = array_merge($chunks, $this->splitLargeChunk($chapter, $title, $content));
            }
        }

        return $chunks;
    }

    /**
     * @return list<array{id: string, chapter: string, title: string, text: string, url: ?string}>
     */
    private function splitLargeChunk(string $chapter, string $title, string $text): array
    {
        $max = (int) config('assistant.chunk_max_chars');
        $text = $this->cleanText($text);
        if ($text === '') {
            return [];
        }

        if (strlen($text) <= $max) {
            return [$this->makeChunk($chapter, $title, $text)];
        }

        $paragraphs = preg_split("/\n{2,}/", $text) ?: [$text];
        $chunks = [];
        $buffer = '';
        $part = 1;

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            if ($buffer !== '' && strlen($buffer) + strlen($paragraph) + 2 > $max) {
                $chunks[] = $this->makeChunk($chapter, $title.' (part '.$part.')', $buffer);
                $buffer = '';
                $part++;
            }
            $buffer = $buffer === '' ? $paragraph : $buffer."\n\n".$paragraph;
        }

        if ($buffer !== '') {
            $chunks[] = $this->makeChunk($chapter, $title.($part > 1 ? ' (part '.$part.')' : ''), $buffer);
        }

        return $chunks;
    }

    /**
     * @return array{id: string, chapter: string, title: string, text: string, url: ?string}
     */
    private function makeChunk(string $chapter, string $title, string $text): array
    {
        $text = $this->cleanText($text);
        $id = substr(sha1($chapter.'|'.$title.'|'.substr($text, 0, 120)), 0, 16);

        return [
            'id' => $id,
            'chapter' => $chapter,
            'title' => $title,
            'text' => $text,
            'url' => $this->links->resolve($title),
        ];
    }

    private function cleanText(string $text): string
    {
        $text = preg_replace('/\r\n|\r/', "\n", $text) ?? $text;
        // Word/export artifacts — not handbook content
        $text = preg_replace('/<!--[\s\S]*?-->/', '', $text) ?? $text;
        $text = preg_replace('/<!--\s*--?/', '', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        $text = preg_replace('/\*\*/', '', $text) ?? $text;
        // Markdown blockquote lines only (do not strip `>` inside HTML or text)
        $text = preg_replace('/^>\s?/m', '', $text) ?? $text;

        return trim($text);
    }
}
