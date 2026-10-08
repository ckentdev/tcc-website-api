<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantTicket extends Model
{
    public const STATUSES = ['open', 'in_progress', 'resolved', 'closed'];

    public const PRIORITIES = ['high', 'normal'];

    protected $fillable = [
        'visitor_id',
        'conversation_id',
        'name',
        'email',
        'phone',
        'summary',
        'priority',
        'status',
        'assigned_to',
        'staff_notes',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AssistantVisitor, $this>
     */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(AssistantVisitor::class, 'visitor_id');
    }

    /**
     * @return BelongsTo<AssistantConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'conversation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
