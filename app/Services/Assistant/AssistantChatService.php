<?php

namespace App\Services\Assistant;

use RuntimeException;

class AssistantChatService
{
    public function __construct(
        private readonly HandbookRetriever $retriever,
        private readonly OpenAiClient $openAi,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return array{reply: string, needs_followup: bool}
     */
    public function reply(string $message, array $history = []): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new RuntimeException('Please enter a message.');
        }

        if (! $this->retriever->isReady()) {
            throw new RuntimeException(
                'The handbook index is not built yet. Run: php artisan assistant:index-handbook'
            );
        }

        $passages = $this->retriever->retrieve($message);
        $context = $this->formatContext($passages);
        $forceFollowup = $passages === [];

        $system = $this->systemPrompt($context);
        $messages = [['role' => 'system', 'content' => $system]];

        $maxHistory = (int) config('assistant.max_history_messages');
        foreach (array_slice($history, -$maxHistory) as $turn) {
            $role = $turn['role'] === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $raw = $this->openAi->chatWithResilience($messages, null, true);
        $parsed = $this->parseModelJson($raw, $forceFollowup);

        if ($parsed['reply'] === '') {
            throw new RuntimeException('The assistant returned an empty response. Please try again.');
        }

        return $parsed;
    }

    /**
     * @return array{reply: string, needs_followup: bool}
     */
    private function parseModelJson(string $raw, bool $forceFollowup): array
    {
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $start = strpos($raw, '{');
            $end = strrpos($raw, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);
            }
        }

        if (is_array($decoded)) {
            $reply = trim((string) ($decoded['reply'] ?? ''));
            $needs = $forceFollowup || $this->toBool($decoded['needs_followup'] ?? false);

            return [
                'reply' => $reply,
                'needs_followup' => $needs,
            ];
        }

        $text = trim($raw);

        return [
            'reply' => $text,
            'needs_followup' => $forceFollowup,
        ];
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes'], true);
        }

        return false;
    }

    private function systemPrompt(string $context): string
    {
        return <<<PROMPT
You are the **TCC AI Assistant**, the official virtual helper for Tagoloan Community College (TCC). You speak with the confidence of staff who already knows TCC policies, programs, admissions, registration, academics, grades, retention, student services, and campus life.

Voice and style:
- Answer in a warm, direct, professional tone — as if you already know the information. Never sound like you are reading, searching, or quoting a document.
- Do NOT say: "according to the handbook", "the handbook states", "based on the excerpts", "the document says", "I found in the text", "the provided context", or similar.
- State policies and steps naturally (e.g. "For transferees, you need…" not "Section 2 says…").
- Be concise, friendly, and accurate. Use simple Markdown so answers are easy to scan: **bold** for key terms, bullet lists (`-`) for requirements or options, numbered lists (`1.`) for steps. Short paragraphs between lists. No URLs, paths, or markdown links.

Scope:
- Answer ONLY TCC-related questions (admissions, academics, grades, campus services, student life at TCC).
- Decline unrelated topics (other schools, jokes, coding, politics, etc.) and invite a TCC-related question. Unrelated topics are not follow-up tickets.

Accuracy:
- Use only facts supported by your background knowledge below. Do not invent requirements, fees, dates, or policies.
- For official transactions or case-specific decisions you cannot complete from the knowledge below, you must ask for a staff follow-up.

Follow-up (required when you cannot fully answer):
- Set needs_followup to true when the background knowledge does not cover the question, you are not certain, or a real staff member must review a specific case.
- In that reply, briefly say you cannot fully answer, then ask the visitor to share their **name** and an **email or phone number** so a TCC staff member can follow up.
- Do not mention handbooks, source documents, or JSON.
- When you can answer fully, set needs_followup to false.

Output format:
- Respond with a JSON object only, no markdown fences:
{"reply":"your message to the visitor","needs_followup":false}

Background knowledge (for you only — never reference this block to the user):
{$context}
PROMPT;
    }

    /**
     * @param  list<array{chapter: string, title: string, text: string, url: ?string}>  $passages
     */
    private function formatContext(array $passages): string
    {
        if ($passages === []) {
            return '(No specific policy notes matched this question. Do not guess. Set needs_followup to true and ask for name and email or phone so staff can follow up.)';
        }

        $blocks = [];
        foreach ($passages as $i => $p) {
            $n = $i + 1;
            $blocks[] = "[{$n}] Topic: {$p['title']}\n".mb_substr($p['text'], 0, 1400);
        }

        return implode("\n\n", $blocks);
    }
}
