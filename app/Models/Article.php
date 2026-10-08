<?php

namespace App\Models;

use App\Support\PlainText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Article extends Model
{
    public const DEFAULT_THUMBNAIL_URL = '/article-default-thumbnail.svg';

    protected $fillable = [
        'user_id',
        'slug',
        'title',
        'excerpt',
        'seo_title',
        'seo_description',
        'og_image_alt',
        'no_index',
        'body',
        'image_url',
        'thumbnail_url',
        'sdg_goals',
        'featured',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'no_index' => 'boolean',
            'published_at' => 'datetime',
            'sdg_goals' => 'array',
        ];
    }

    /**
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', Carbon::now());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resolvedThumbnailUrl(): string
    {
        if (filled($this->thumbnail_url)) {
            return $this->thumbnail_url;
        }

        if (filled($this->image_url)) {
            return $this->image_url;
        }

        return self::DEFAULT_THUMBNAIL_URL;
    }

    public static function isPlaceholderSlug(?string $slug): bool
    {
        $slug = trim((string) $slug);

        return $slug === '' || (bool) preg_match('/^post(?:-\d+)?$/', $slug);
    }

    public static function makeUniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base !== '' ? $base : 'post';
        $candidate = $slug;
        $n = 1;
        while (self::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $slug.'-'.$n;
            $n++;
        }

        return $candidate;
    }

    public static function slugFromTitle(string $title, ?int $ignoreId = null): string
    {
        return self::makeUniqueSlug(PlainText::slugBase($title), $ignoreId);
    }
}
