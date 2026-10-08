<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ResearchPublication extends Model
{
    protected $fillable = [
        'user_id',
        'slug',
        'title',
        'excerpt',
        'body',
        'image_url',
        'thumbnail_url',
        'academic_program_id',
        'output_type',
        'authors',
        'venue_or_journal',
        'link_url',
        'sdg_goals',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'sdg_goals' => 'array',
        ];
    }

    public const OUTPUT_PUBLICATION = 'publication';

    public const OUTPUT_RESEARCH = 'research';

    /**
     * @param  Builder<ResearchPublication>  $query
     * @return Builder<ResearchPublication>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', Carbon::now());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
