<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantTicket;
use App\Services\Assistant\ConversationLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantFollowUpController extends Controller
{
    public function __construct(
        private readonly ConversationLogger $logger,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'visitor_key' => ['nullable', 'string', 'max:64'],
            'conversation_id' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $email = trim((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        if ($email === '' && $phone === '') {
            return response()->json([
                'message' => 'Provide an email or a phone number so staff can follow up.',
                'errors' => [
                    'email' => ['Provide an email or a phone number so staff can follow up.'],
                ],
            ], 422);
        }

        $visitor = $this->logger->upsertVisitor($request, $data['visitor_key'] ?? null);

        $conversation = null;
        if (! empty($data['conversation_id'])) {
            $conversation = AssistantConversation::query()
                ->where('id', $data['conversation_id'])
                ->where('visitor_id', $visitor->id)
                ->first();
        }

        $summary = $this->latestUserQuestion($conversation);

        $ticket = AssistantTicket::query()->create([
            'visitor_id' => $visitor->id,
            'conversation_id' => $conversation?->id,
            'name' => trim($data['name']),
            'email' => trim((string) ($data['email'] ?? '')) ?: null,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'summary' => $summary,
            'priority' => 'high',
            'status' => 'open',
        ]);

        return response()->json([
            'ok' => true,
            'ticket_id' => $ticket->id,
            'visitor_key' => $visitor->visitor_key,
            'conversation_id' => $conversation?->id,
        ], 201);
    }

    private function latestUserQuestion(?AssistantConversation $conversation): ?string
    {
        if (! $conversation) {
            return null;
        }

        $message = AssistantMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'user')
            ->orderByDesc('id')
            ->value('body');

        if (! is_string($message) || trim($message) === '') {
            return null;
        }

        return mb_substr(trim($message), 0, 500);
    }
}
