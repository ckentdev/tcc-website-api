<?php

return [

    'openai_api_key' => env('OPENAI_API_KEY'),

    /**
     * Optional second OpenAI key (e.g. another account with remaining balance).
     * Used automatically when the primary key hits rate limits or quota errors.
     */
    'openai_api_key_fallback' => env('OPENAI_API_KEY_FALLBACK'),

    /** Cheap defaults: gpt-4o-mini + text-embedding-3-small */
    'chat_model' => env('ASSISTANT_CHAT_MODEL', 'gpt-4o-mini'),

    /** Optional cheaper/alternate model tried before switching API keys. */
    'chat_model_fallback' => env('ASSISTANT_CHAT_MODEL_FALLBACK', ''),

    'embedding_model' => env('ASSISTANT_EMBEDDING_MODEL', 'text-embedding-3-small'),

    /** Retries per key+model on HTTP 429 (rate limit) before trying fallbacks. */
    'openai_max_retries' => (int) env('ASSISTANT_OPENAI_MAX_RETRIES', 2),
    'openai_retry_ms' => (int) env('ASSISTANT_OPENAI_RETRY_MS', 800),

    'max_output_tokens' => (int) env('ASSISTANT_MAX_OUTPUT_TOKENS', 550),
    'max_history_messages' => (int) env('ASSISTANT_MAX_HISTORY_MESSAGES', 6),
    'retrieval_top_k' => (int) env('ASSISTANT_RETRIEVAL_TOP_K', 4),

    'source_markdown' => resource_path('markdown/handbook-student.md'),
    'rag_markdown' => resource_path('markdown/handbook-rag.md'),
    'index_path' => storage_path('app/handbook-rag-index.json'),

    /** Max characters per chunk stored in the index */
    'chunk_max_chars' => (int) env('ASSISTANT_CHUNK_MAX_CHARS', 1800),

];
