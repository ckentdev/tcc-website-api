<?php

namespace App\Services\Assistant;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiClient
{
    /**
     * @param  list<string>  $apiKeys  Primary first, then optional fallback key (e.g. account with balance).
     */
    public function __construct(
        private readonly array $apiKeys,
        private readonly string $baseUrl = 'https://api.openai.com/v1',
    ) {}

    public static function fromConfig(): self
    {
        $keys = array_values(array_unique(array_filter([
            trim((string) config('assistant.openai_api_key')),
            trim((string) config('assistant.openai_api_key_fallback')),
        ])));

        if ($keys === []) {
            throw new RuntimeException('OPENAI_API_KEY is not configured on the server.');
        }

        return new self($keys);
    }

    /**
     * @return list<float>
     */
    public function embed(string $text, ?string $model = null): array
    {
        $model ??= (string) config('assistant.embedding_model');
        $lastError = null;

        foreach ($this->apiKeys as $apiKey) {
            try {
                return $this->requestEmbedding($apiKey, $text, $model);
            } catch (OpenAiRecoverableException $e) {
                $lastError = $e;
            }
        }

        throw $lastError ?? new RuntimeException('Embedding request failed.');
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function chat(array $messages, ?string $model = null, ?int $maxTokens = null): string
    {
        return $this->chatWithResilience($messages, $maxTokens);
    }

    /**
     * Tries primary key + model, then fallback model, then fallback key — with retries on rate limits.
     *
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function chatWithResilience(array $messages, ?int $maxTokens = null, bool $json = false): string
    {
        $maxTokens ??= (int) config('assistant.max_output_tokens');
        $models = $this->chatModelsToTry();
        $errors = [];

        foreach ($this->apiKeys as $keyIndex => $apiKey) {
            foreach ($models as $model) {
                try {
                    return $this->requestChat($apiKey, $messages, $model, $maxTokens, $json);
                } catch (OpenAiRecoverableException $e) {
                    $errors[] = sprintf('key#%d %s: %s', $keyIndex + 1, $model, $e->getMessage());
                }
            }
        }

        throw new RuntimeException(
            'The TCC AI Assistant is temporarily unable to reach OpenAI. '
            .'If limits were hit, add OPENAI_API_KEY_FALLBACK (another account with balance) or ASSISTANT_CHAT_MODEL_FALLBACK in backend/.env.'
        );
    }

    /**
     * @return list<string>
     */
    private function chatModelsToTry(): array
    {
        $primary = trim((string) config('assistant.chat_model', 'gpt-4o-mini'));
        $fallback = trim((string) config('assistant.chat_model_fallback', ''));

        $models = array_values(array_unique(array_filter([$primary, $fallback])));

        return $models !== [] ? $models : ['gpt-4o-mini'];
    }

    /**
     * @return list<float>
     */
    private function requestEmbedding(string $apiKey, string $text, string $model): array
    {
        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post("{$this->baseUrl}/embeddings", [
                'model' => $model,
                'input' => $text,
            ]);

        return $this->parseEmbeddingResponse($response);
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function requestChat(string $apiKey, array $messages, string $model, int $maxTokens, bool $json = false): string
    {
        $maxRetries = max(0, (int) config('assistant.openai_max_retries', 2));
        $retryMs = max(200, (int) config('assistant.openai_retry_ms', 800));
        $lastRecoverable = null;
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => 0.25,
        ];
        if ($json) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $response = Http::withToken($apiKey)
                ->timeout(90)
                ->post("{$this->baseUrl}/chat/completions", $payload);

            if ($response->successful()) {
                return trim((string) $response->json('choices.0.message.content', ''));
            }

            if ($attempt < $maxRetries && $response->status() === 429) {
                usleep($this->retryAfterMs($response, $retryMs, $attempt) * 1000);

                continue;
            }

            if ($this->isRecoverable($response)) {
                $lastRecoverable = new OpenAiRecoverableException($this->errorMessage($response));

                break;
            }

            throw new RuntimeException('Chat request failed: '.$this->errorMessage($response));
        }

        throw $lastRecoverable ?? new OpenAiRecoverableException('Chat request failed after retries.');
    }

    /**
     * @return list<float>
     */
    private function parseEmbeddingResponse(Response $response): array
    {
        if ($response->successful()) {
            /** @var list<float> */
            return $response->json('data.0.embedding') ?? [];
        }

        if ($this->isRecoverable($response)) {
            throw new OpenAiRecoverableException('Embedding: '.$this->errorMessage($response));
        }

        throw new RuntimeException('Embedding request failed: '.$this->errorMessage($response));
    }

    private function isRecoverable(Response $response): bool
    {
        $status = $response->status();
        if (in_array($status, [429, 402, 503, 529], true)) {
            return true;
        }

        $body = $response->json();
        if (! is_array($body)) {
            return false;
        }

        $error = $body['error'] ?? [];
        if (! is_array($error)) {
            return false;
        }

        $type = strtolower((string) ($error['type'] ?? ''));
        $code = strtolower((string) ($error['code'] ?? ''));
        $message = strtolower((string) ($error['message'] ?? ''));

        if (in_array($type, ['insufficient_quota', 'rate_limit_exceeded', 'tokens', 'billing_not_active'], true)) {
            return true;
        }

        if (in_array($code, ['insufficient_quota', 'rate_limit_exceeded', 'billing_not_active'], true)) {
            return true;
        }

        foreach (['quota', 'rate limit', 'billing', 'insufficient', 'exceeded your current'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function errorMessage(Response $response): string
    {
        $body = $response->json();
        if (is_array($body) && isset($body['error']['message'])) {
            return (string) $body['error']['message'];
        }

        return $response->body() ?: $response->reason();
    }

    private function retryAfterMs(Response $response, int $defaultMs, int $attempt): int
    {
        $header = $response->header('Retry-After');
        if ($header !== null && is_numeric($header)) {
            return max(200, (int) ((float) $header * 1000));
        }

        return $defaultMs * ($attempt + 1);
    }
}
