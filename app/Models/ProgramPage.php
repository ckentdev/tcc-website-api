<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ProgramPage extends Model
{
    protected $fillable = [
        'user_id',
        'slug',
        'title',
        'excerpt',
        'body',
        'info_pdf_url',
        'info_pdf_name',
        'image_url',
        'thumbnail_url',
        'org_chart_url',
        'org_chart_urls',
        'org_chart_body',
        'academic_program_id',
        'link_url',
        'sdg_goals',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'sdg_goals' => 'array',
            'org_chart_urls' => 'array',
        ];
    }

    /**
     * @param  Builder<ProgramPage>  $query
     * @return Builder<ProgramPage>
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
