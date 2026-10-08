<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistantConversation extends Model
{
    protected $fillable = [
        'visitor_id',
        'source',
        'message_count',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'message_count' => 'integer',
            'last_message_at' => 'datetime',
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
     * @return HasMany<AssistantMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class, 'conversation_id');
    }

    /**
     * @return HasMany<AssistantTicket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(AssistantTicket::class, 'conversation_id');
    }
}
