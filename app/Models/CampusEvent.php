<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class CampusEvent extends Model
{
    protected $table = 'campus_events';

    protected $fillable = [
        'user_id',
        'slug',
        'title',
        'excerpt',
        'body',
        'starts_at',
        'ends_at',
        'location',
        'image_url',
        'sdg_goals',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'sdg_goals' => 'array',
        ];
    }

    /**
     * @param  Builder<CampusEvent>  $query
     * @return Builder<CampusEvent>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', Carbon::now());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
