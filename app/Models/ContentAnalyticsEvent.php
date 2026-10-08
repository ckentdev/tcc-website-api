<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentAnalyticsEvent extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_ARTICLE = 'article';

    public const TYPE_RESEARCH = 'research_publication';

    public const TYPE_SITE_PAGE = 'site_page';

    public const EVENT_CLICK = 'click';

    public const EVENT_VIEW = 'view';

    protected $fillable = [
        'content_type',
        'content_id',
        'content_slug',
        'event_type',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
