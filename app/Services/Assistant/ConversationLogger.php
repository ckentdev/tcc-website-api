<?php

namespace App\Services\Assistant;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantVisitor;
use App\Support\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class ConversationLogger
{
    /**
     * @return array{visitor: AssistantVisitor, conversation: AssistantConversation}
     */
    public function resolve(Request $request, ?string $visitorKey, ?int $conversationId, string $source): array
    {
        $visitor = $this->upsertVisitor($request, $visitorKey);
        $conversation = $this->resolveConversation($visitor, $conversationId, $source);

        return [
            'visitor' => $visitor,
            'conversation' => $conversation,
        ];
    }

    public function recordTurn(
        AssistantConversation $conversation,
        string $userMessage,
        string $assistantReply,
        bool $needsFollowup,
    ): void {
        AssistantMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'body' => $userMessage,
            'needs_followup' => false,
        ]);

        AssistantMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'body' => $assistantReply,
            'needs_followup' => $needsFollowup,
        ]);

        $conversation->forceFill([
            'message_count' => $conversation->message_count + 2,
            'last_message_at' => now(),
        ])->save();
    }

    public function upsertVisitor(Request $request, ?string $visitorKey): AssistantVisitor
    {
        $key = is_string($visitorKey) && Str::isUuid($visitorKey)
            ? strtolower($visitorKey)
            : (string) Str::uuid();

        $ua = (string) $request->userAgent();
        $parsed = UserAgent::parse($ua);
        $now = now();

        $visitor = AssistantVisitor::query()->firstOrNew(['visitor_key' => $key]);
        $visitor->ip = $request->ip();
        $visitor->user_agent = $ua !== '' ? mb_substr($ua, 0, 2000) : null;
        $visitor->browser = $parsed['browser'];
        $visitor->platform = $parsed['platform'];
        $visitor->last_seen_at = $now;
        if (! $visitor->exists) {
            $visitor->first_seen_at = $now;
        }
        $visitor->save();

        return $visitor;
    }

    private function resolveConversation(
        AssistantVisitor $visitor,
        ?int $conversationId,
        string $source,
    ): AssistantConversation {
        $source = $source === 'chats' ? 'chats' : 'mini';

        if ($conversationId) {
            $existing = AssistantConversation::query()
                ->where('id', $conversationId)
                ->where('visitor_id', $visitor->id)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        return AssistantConversation::query()->create([
            'visitor_id' => $visitor->id,
            'source' => $source,
            'message_count' => 0,
            'last_message_at' => now(),
        ]);
    }

    public function tryRecord(
        Request $request,
        ?string $visitorKey,
        ?int $conversationId,
        string $source,
        string $userMessage,
        string $assistantReply,
        bool $needsFollowup,
    ): ?array {
        try {
            $resolved = $this->resolve($request, $visitorKey, $conversationId, $source);
            $this->recordTurn($resolved['conversation'], $userMessage, $assistantReply, $needsFollowup);

            return [
                'visitor_key' => $resolved['visitor']->visitor_key,
                'conversation_id' => $resolved['conversation']->id,
            ];
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
