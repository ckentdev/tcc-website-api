<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistantVisitor extends Model
{
    protected $fillable = [
        'visitor_key',
        'ip',
        'user_agent',
        'browser',
        'platform',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<AssistantConversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(AssistantConversation::class, 'visitor_id');
    }

    /**
     * @return HasMany<AssistantTicket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(AssistantTicket::class, 'visitor_id');
    }
}
