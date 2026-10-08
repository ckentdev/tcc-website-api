<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ContentAnalyticsEvent;
use App\Models\ResearchPublication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ContentAnalyticsService
{
    public function track(string $contentType, string $eventType, ?int $contentId, ?string $contentSlug): void
    {
        ContentAnalyticsEvent::query()->create([
            'content_type' => $contentType,
            'content_id' => $contentId,
            'content_slug' => $contentSlug,
            'event_type' => $eventType,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(?int $days): array
    {
        $since = $days !== null && $days > 0 ? Carbon::now()->subDays($days) : null;

        $base = ContentAnalyticsEvent::query();
        if ($since) {
            $base->where('created_at', '>=', $since);
        }

        $clicks = (clone $base)->where('event_type', ContentAnalyticsEvent::EVENT_CLICK)->count();
        $views = (clone $base)->where('event_type', ContentAnalyticsEvent::EVENT_VIEW)->count();

        $articleClicks = $this->countFor($since, ContentAnalyticsEvent::TYPE_ARTICLE, ContentAnalyticsEvent::EVENT_CLICK);
        $articleViews = $this->countFor($since, ContentAnalyticsEvent::TYPE_ARTICLE, ContentAnalyticsEvent::EVENT_VIEW);
        $researchClicks = $this->countFor($since, ContentAnalyticsEvent::TYPE_RESEARCH, ContentAnalyticsEvent::EVENT_CLICK);
        $researchViews = $this->countFor($since, ContentAnalyticsEvent::TYPE_RESEARCH, ContentAnalyticsEvent::EVENT_VIEW);

        $sitePageViews = $this->sitePageViews($since);

        return [
            'period_days' => $days,
            'summary' => [
                'clicks' => $clicks,
                'views' => $views,
                'reads' => $views,
                'articles' => [
                    'clicks' => $articleClicks,
                    'views' => $articleViews,
                    'reads' => $articleViews,
                    'published' => Article::query()->published()->count(),
                    'draft' => Article::query()->where(function ($q) {
                        $q->whereNull('published_at')->orWhere('published_at', '>', Carbon::now());
                    })->count(),
                ],
                'research' => [
                    'clicks' => $researchClicks,
                    'views' => $researchViews,
                    'reads' => $researchViews,
                    'published' => ResearchPublication::query()->published()->count(),
                    'draft' => ResearchPublication::query()->where(function ($q) {
                        $q->whereNull('published_at')->orWhere('published_at', '>', Carbon::now());
                    })->count(),
                ],
                'site_pages' => $sitePageViews,
            ],
            'top_articles' => $this->topContent(ContentAnalyticsEvent::TYPE_ARTICLE, $since),
            'top_research' => $this->topContent(ContentAnalyticsEvent::TYPE_RESEARCH, $since),
            'daily' => $this->dailySeries($since),
        ];
    }

    private function countFor(?Carbon $since, string $contentType, string $eventType): int
    {
        $q = ContentAnalyticsEvent::query()
            ->where('content_type', $contentType)
            ->where('event_type', $eventType);

        if ($since) {
            $q->where('created_at', '>=', $since);
        }

        return $q->count();
    }

    /**
     * @return array<string, int>
     */
    private function sitePageViews(?Carbon $since): array
    {
        $slugs = ['home', 'news', 'news-events', 'research-publication'];
        $out = [];
        foreach ($slugs as $slug) {
            $q = ContentAnalyticsEvent::query()
                ->where('content_type', ContentAnalyticsEvent::TYPE_SITE_PAGE)
                ->where('content_slug', $slug)
                ->where('event_type', ContentAnalyticsEvent::EVENT_VIEW);
            if ($since) {
                $q->where('created_at', '>=', $since);
            }
            $out[$slug] = $q->count();
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topContent(string $contentType, ?Carbon $since): array
    {
        $q = ContentAnalyticsEvent::query()
            ->where('content_type', $contentType)
            ->whereNotNull('content_id');

        if ($since) {
            $q->where('created_at', '>=', $since);
        }

        $rows = $q
            ->select([
                'content_id',
                DB::raw("SUM(CASE WHEN event_type = '".ContentAnalyticsEvent::EVENT_CLICK."' THEN 1 ELSE 0 END) as clicks"),
                DB::raw("SUM(CASE WHEN event_type = '".ContentAnalyticsEvent::EVENT_VIEW."' THEN 1 ELSE 0 END) as views"),
            ])
            ->groupBy('content_id')
            ->orderByDesc('views')
            ->orderByDesc('clicks')
            ->limit(10)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $ids = $rows->pluck('content_id')->all();

        if ($contentType === ContentAnalyticsEvent::TYPE_ARTICLE) {
            $items = Article::query()->whereIn('id', $ids)->get()->keyBy('id');
        } else {
            $items = ResearchPublication::query()->whereIn('id', $ids)->get()->keyBy('id');
        }

        $result = [];
        foreach ($rows as $row) {
            $item = $items->get($row->content_id);
            if (! $item) {
                continue;
            }
            $result[] = [
                'id' => (int) $row->content_id,
                'title' => $item->title,
                'slug' => $item->slug,
                'clicks' => (int) $row->clicks,
                'views' => (int) $row->views,
                'reads' => (int) $row->views,
            ];
        }

        return $result;
    }

    /**
     * @return list<array{date: string, clicks: int, views: int}>
     */
    private function dailySeries(?Carbon $since): array
    {
        $q = ContentAnalyticsEvent::query()
            ->select([
                DB::raw('DATE(created_at) as day'),
                DB::raw("SUM(CASE WHEN event_type = '".ContentAnalyticsEvent::EVENT_CLICK."' THEN 1 ELSE 0 END) as clicks"),
                DB::raw("SUM(CASE WHEN event_type = '".ContentAnalyticsEvent::EVENT_VIEW."' THEN 1 ELSE 0 END) as views"),
            ])
            ->groupBy('day')
            ->orderBy('day');

        if ($since) {
            $q->where('created_at', '>=', $since);
        } else {
            $q->where('created_at', '>=', Carbon::now()->subDays(30));
        }

        return $q->get()->map(fn ($r) => [
            'date' => (string) $r->day,
            'clicks' => (int) $r->clicks,
            'views' => (int) $r->views,
        ])->all();
    }

    /**
     * Dashboard stats scoped to one CMS author's news articles.
     *
     * @return array<string, mixed>
     */
    public function authorDashboard(int $userId, ?int $days): array
    {
        $since = $days !== null && $days > 0 ? Carbon::now()->subDays($days) : null;

        $articlesQuery = Article::query()->where('user_id', $userId);
        $articleIds = (clone $articlesQuery)->pluck('id');

        $published = (clone $articlesQuery)->published()->count();
        $draft = (clone $articlesQuery)->where(function ($q) {
            $q->whereNull('published_at')->orWhere('published_at', '>', Carbon::now());
        })->count();

        $clicks = $this->countArticleEventsForUser($articleIds, ContentAnalyticsEvent::EVENT_CLICK, $since);
        $reads = $this->countArticleEventsForUser($articleIds, ContentAnalyticsEvent::EVENT_VIEW, $since);

        $recent = Article::query()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get(['id', 'title', 'slug', 'published_at', 'featured', 'updated_at']);

        $researchQuery = ResearchPublication::query()->where('user_id', $userId);
        $researchPublished = (clone $researchQuery)->published()->count();
        $researchDraft = (clone $researchQuery)->where(function ($q) {
            $q->whereNull('published_at')->orWhere('published_at', '>', Carbon::now());
        })->count();

        $recentResearch = ResearchPublication::query()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get(['id', 'title', 'slug', 'published_at', 'output_type', 'updated_at']);

        return [
            'period_days' => $days,
            'summary' => [
                'articles' => [
                    'total' => (clone $articlesQuery)->count(),
                    'published' => $published,
                    'draft' => $draft,
                    'clicks' => $clicks,
                    'reads' => $reads,
                ],
                'research' => [
                    'total' => (clone $researchQuery)->count(),
                    'published' => $researchPublished,
                    'draft' => $researchDraft,
                ],
            ],
            'top_articles' => $this->topArticlesForUser($userId, $since),
            'recent_articles' => $recent->map(fn (Article $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'slug' => $a->slug,
                'published_at' => $a->published_at?->toIso8601String(),
                'featured' => (bool) $a->featured,
                'updated_at' => $a->updated_at?->toIso8601String(),
            ])->all(),
            'recent_research' => $recentResearch->map(fn (ResearchPublication $r) => [
                'id' => $r->id,
                'title' => $r->title,
                'slug' => $r->slug,
                'output_type' => $r->output_type,
                'published_at' => $r->published_at?->toIso8601String(),
                'updated_at' => $r->updated_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>|list<int>  $articleIds
     */
    private function countArticleEventsForUser($articleIds, string $eventType, ?Carbon $since): int
    {
        if ($articleIds->isEmpty()) {
            return 0;
        }

        $q = ContentAnalyticsEvent::query()
            ->where('content_type', ContentAnalyticsEvent::TYPE_ARTICLE)
            ->where('event_type', $eventType)
            ->whereIn('content_id', $articleIds);

        if ($since) {
            $q->where('created_at', '>=', $since);
        }

        return $q->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topArticlesForUser(int $userId, ?Carbon $since): array
    {
        $articleIds = Article::query()->where('user_id', $userId)->pluck('id');
        if ($articleIds->isEmpty()) {
            return [];
        }

        $q = ContentAnalyticsEvent::query()
            ->where('content_type', ContentAnalyticsEvent::TYPE_ARTICLE)
            ->whereIn('content_id', $articleIds)
            ->whereNotNull('content_id');

        if ($since) {
            $q->where('created_at', '>=', $since);
        }

        $rows = $q
            ->select([
                'content_id',
                DB::raw("SUM(CASE WHEN event_type = '".ContentAnalyticsEvent::EVENT_CLICK."' THEN 1 ELSE 0 END) as clicks"),
                DB::raw("SUM(CASE WHEN event_type = '".ContentAnalyticsEvent::EVENT_VIEW."' THEN 1 ELSE 0 END) as views"),
            ])
            ->groupBy('content_id')
            ->orderByDesc('views')
            ->orderByDesc('clicks')
            ->limit(10)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $items = Article::query()->whereIn('id', $rows->pluck('content_id'))->get()->keyBy('id');
        $result = [];
        foreach ($rows as $row) {
            $item = $items->get($row->content_id);
            if (! $item) {
                continue;
            }
            $result[] = [
                'id' => (int) $row->content_id,
                'title' => $item->title,
                'slug' => $item->slug,
                'clicks' => (int) $row->clicks,
                'views' => (int) $row->views,
                'reads' => (int) $row->views,
            ];
        }

        return $result;
    }
}
