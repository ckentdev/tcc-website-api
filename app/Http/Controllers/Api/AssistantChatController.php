<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Assistant\AssistantChatService;
use App\Services\Assistant\ConversationLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class AssistantChatController extends Controller
{
    public function __construct(
        private readonly AssistantChatService $chat,
        private readonly ConversationLogger $logger,
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['sometimes', 'array', 'max:8'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:2000'],
            'visitor_key' => ['nullable', 'string', 'max:64'],
            'conversation_id' => ['nullable', 'integer', 'min:1'],
            'source' => ['nullable', 'in:mini,chats'],
        ]);

        try {
            $result = $this->chat->reply(
                $data['message'],
                $data['history'] ?? [],
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'The assistant is temporarily unavailable. Please try again or contact TCC.',
            ], 500);
        }

        $logged = $this->logger->tryRecord(
            $request,
            $data['visitor_key'] ?? null,
            isset($data['conversation_id']) ? (int) $data['conversation_id'] : null,
            (string) ($data['source'] ?? 'mini'),
            $data['message'],
            $result['reply'],
            $result['needs_followup'],
        );

        return response()->json([
            'reply' => $result['reply'],
            'needs_followup' => $result['needs_followup'],
            'visitor_key' => $logged['visitor_key'] ?? $data['visitor_key'] ?? null,
            'conversation_id' => $logged['conversation_id'] ?? null,
        ]);
    }
}
