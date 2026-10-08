<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\AssistantMessage;
use App\Models\AssistantVisitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAssistantVisitorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);
        $search = trim((string) $request->query('search', ''));
        if (strlen($search) > 200) {
            $search = mb_substr($search, 0, 200);
        }

        $query = AssistantVisitor::query()
            ->withCount(['conversations', 'tickets'])
            ->with([
                'tickets' => fn ($q) => $q->latest('id')->limit(3)->select('id', 'visitor_id', 'name', 'email', 'phone', 'status'),
            ]);

        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $like = '%'.$escaped.'%';
            $query->where(function ($q) use ($like) {
                $q->where('ip', 'like', $like)
                    ->orWhere('browser', 'like', $like)
                    ->orWhere('platform', 'like', $like)
                    ->orWhere('visitor_key', 'like', $like)
                    ->orWhereHas('tickets', function ($tickets) use ($like) {
                        $tickets->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('phone', 'like', $like);
                    })
                    ->orWhereHas('conversations.messages', function ($messages) use ($like) {
                        $messages->where('role', 'user')->where('body', 'like', $like);
                    });
            });
        }

        $paginator = $query->orderByDesc('last_seen_at')->orderByDesc('id')->paginate($perPage);

        $visitorIds = $paginator->getCollection()->pluck('id')->all();
        $lastQuestions = $this->lastUserQuestions($visitorIds);

        $paginator->setCollection(
            $paginator->getCollection()->map(function (AssistantVisitor $visitor) use ($lastQuestions) {
                return [
                    'id' => $visitor->id,
                    'visitor_key' => $visitor->visitor_key,
                    'ip' => $visitor->ip,
                    'browser' => $visitor->browser,
                    'platform' => $visitor->platform,
                    'first_seen_at' => $visitor->first_seen_at?->toIso8601String(),
                    'last_seen_at' => $visitor->last_seen_at?->toIso8601String(),
                    'conversations_count' => $visitor->conversations_count,
                    'tickets_count' => $visitor->tickets_count,
                    'last_question' => $lastQuestions[$visitor->id] ?? null,
                    'contact_name' => $visitor->tickets->first()?->name,
                    'contact_email' => $visitor->tickets->first()?->email,
                    'contact_phone' => $visitor->tickets->first()?->phone,
                ];
            })
        );

        return response()->json($paginator);
    }

    public function show(AssistantVisitor $visitor): JsonResponse
    {
        $visitor->load([
            'conversations' => fn ($q) => $q->orderByDesc('last_message_at')->orderByDesc('id'),
            'conversations.messages' => fn ($q) => $q->orderBy('id'),
            'tickets' => fn ($q) => $q->latest('id'),
        ]);

        return response()->json([
            'id' => $visitor->id,
            'visitor_key' => $visitor->visitor_key,
            'ip' => $visitor->ip,
            'user_agent' => $visitor->user_agent,
            'browser' => $visitor->browser,
            'platform' => $visitor->platform,
            'first_seen_at' => $visitor->first_seen_at?->toIso8601String(),
            'last_seen_at' => $visitor->last_seen_at?->toIso8601String(),
            'conversations' => $visitor->conversations->map(fn ($c) => [
                'id' => $c->id,
                'source' => $c->source,
                'message_count' => $c->message_count,
                'last_message_at' => $c->last_message_at?->toIso8601String(),
                'created_at' => $c->created_at?->toIso8601String(),
                'messages' => $c->messages->map(fn ($m) => [
                    'id' => $m->id,
                    'role' => $m->role,
                    'body' => $m->body,
                    'needs_followup' => $m->needs_followup,
                    'created_at' => $m->created_at?->toIso8601String(),
                ])->values(),
            ])->values(),
            'tickets' => $visitor->tickets->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'email' => $t->email,
                'phone' => $t->phone,
                'status' => $t->status,
                'priority' => $t->priority,
                'created_at' => $t->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * @param  list<int>  $visitorIds
     * @return array<int, string>
     */
    private function lastUserQuestions(array $visitorIds): array
    {
        if ($visitorIds === []) {
            return [];
        }

        $rows = AssistantMessage::query()
            ->select('assistant_messages.body', 'assistant_conversations.visitor_id')
            ->join('assistant_conversations', 'assistant_conversations.id', '=', 'assistant_messages.conversation_id')
            ->whereIn('assistant_conversations.visitor_id', $visitorIds)
            ->where('assistant_messages.role', 'user')
            ->whereIn('assistant_messages.id', function ($q) {
                $q->selectRaw('MAX(m.id)')
                    ->from('assistant_messages as m')
                    ->join('assistant_conversations as c', 'c.id', '=', 'm.conversation_id')
                    ->where('m.role', 'user')
                    ->groupBy('c.visitor_id');
            })
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->visitor_id] = mb_substr((string) $row->body, 0, 180);
        }

        return $map;
    }
}
