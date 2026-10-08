<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\AssistantTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminAssistantTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);
        $search = trim((string) $request->query('search', ''));
        if (strlen($search) > 200) {
            $search = mb_substr($search, 0, 200);
        }
        $status = trim((string) $request->query('status', ''));

        $query = AssistantTicket::query()
            ->with([
                'visitor:id,visitor_key,ip,browser,platform',
                'assignee:id,name',
            ]);

        if ($status !== '' && in_array($status, AssistantTicket::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $like = '%'.$escaped.'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('summary', 'like', $like);
            });
        }

        $paginator = $query->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'resolved' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (AssistantTicket $ticket) => $this->serialize($ticket, false))
        );

        return response()->json($paginator);
    }

    public function show(AssistantTicket $ticket): JsonResponse
    {
        $ticket->load([
            'visitor',
            'assignee:id,name,email',
            'conversation.messages' => fn ($q) => $q->orderBy('id'),
        ]);

        return response()->json($this->serialize($ticket, true));
    }

    public function update(Request $request, AssistantTicket $ticket): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(AssistantTicket::STATUSES)],
            'staff_notes' => ['sometimes', 'nullable', 'string', 'max:8000'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        if (array_key_exists('status', $data)) {
            $ticket->status = $data['status'];
            if (in_array($data['status'], ['resolved', 'closed'], true)) {
                $ticket->resolved_at = $ticket->resolved_at ?? now();
            } else {
                $ticket->resolved_at = null;
            }
        }
        if (array_key_exists('staff_notes', $data)) {
            $ticket->staff_notes = $data['staff_notes'];
        }
        if (array_key_exists('assigned_to', $data)) {
            $ticket->assigned_to = $data['assigned_to'];
        }
        $ticket->save();

        $ticket->load(['visitor', 'assignee:id,name,email', 'conversation.messages' => fn ($q) => $q->orderBy('id')]);

        return response()->json($this->serialize($ticket, true));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(AssistantTicket $ticket, bool $withTranscript): array
    {
        $payload = [
            'id' => $ticket->id,
            'name' => $ticket->name,
            'email' => $ticket->email,
            'phone' => $ticket->phone,
            'summary' => $ticket->summary,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'staff_notes' => $ticket->staff_notes,
            'assigned_to' => $ticket->assigned_to,
            'assignee' => $ticket->assignee ? [
                'id' => $ticket->assignee->id,
                'name' => $ticket->assignee->name,
            ] : null,
            'visitor_id' => $ticket->visitor_id,
            'conversation_id' => $ticket->conversation_id,
            'visitor' => $ticket->visitor ? [
                'id' => $ticket->visitor->id,
                'visitor_key' => $ticket->visitor->visitor_key,
                'ip' => $ticket->visitor->ip,
                'browser' => $ticket->visitor->browser,
                'platform' => $ticket->visitor->platform,
            ] : null,
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];

        if ($withTranscript) {
            $payload['messages'] = $ticket->conversation
                ? $ticket->conversation->messages->map(fn ($m) => [
                    'id' => $m->id,
                    'role' => $m->role,
                    'body' => $m->body,
                    'needs_followup' => $m->needs_followup,
                    'created_at' => $m->created_at?->toIso8601String(),
                ])->values()
                : [];
        }

        return $payload;
    }
}
