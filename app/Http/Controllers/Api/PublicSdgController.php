<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\CampusEventResource;
use App\Models\Article;
use App\Models\CampusEvent;
use App\Models\ResearchPublication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicSdgController extends Controller
{
    private const LIMIT = 9;

    public function index(): JsonResponse
    {
        $totals = array_fill(1, 17, 0);

        foreach (Article::query()->published()->get(['sdg_goals']) as $row) {
            $this->tallyGoals($totals, $row->sdg_goals);
        }
        foreach (CampusEvent::query()->get(['sdg_goals']) as $row) {
            $this->tallyGoals($totals, $row->sdg_goals);
        }
        foreach (ResearchPublication::query()->published()->get(['sdg_goals']) as $row) {
            $this->tallyGoals($totals, $row->sdg_goals);
        }

        $goals = [];
        for ($id = 1; $id <= 17; $id++) {
            $goals[] = [
                'id' => $id,
                'total' => $totals[$id],
            ];
        }

        return response()->json(['goals' => $goals]);
    }

    public function show(Request $request, int $goal): JsonResponse
    {
        if ($goal < 1 || $goal > 17) {
            abort(404, 'Unknown Sustainable Development Goal.');
        }

        $authorCols = 'author:id,name,is_admin,job_title,position,bio,profile_links,profile_photo_url,cover_photo_url';

        $newsQuery = Article::query()->published()->whereJsonContains('sdg_goals', $goal);
        $eventsQuery = CampusEvent::query()->whereJsonContains('sdg_goals', $goal);
        $researchQuery = ResearchPublication::query()
            ->published()
            ->where('output_type', ResearchPublication::OUTPUT_RESEARCH)
            ->whereJsonContains('sdg_goals', $goal);
        $publicationsQuery = ResearchPublication::query()
            ->published()
            ->where('output_type', ResearchPublication::OUTPUT_PUBLICATION)
            ->whereJsonContains('sdg_goals', $goal);

        $newsBuilder = (clone $newsQuery)
            ->with($authorCols)
            ->orderByDesc('published_at');

        if ($request->has('news_limit')) {
            $newsBuilder->limit(max(1, (int) $request->query('news_limit')));
        }

        $news = $newsBuilder->get();
        $events = (clone $eventsQuery)
            ->with('author:id,name,job_title,position,bio,profile_links,profile_photo_url,cover_photo_url')
            ->orderBy('starts_at')
            ->limit(self::LIMIT)
            ->get();
        $research = (clone $researchQuery)->orderByDesc('published_at')->limit(self::LIMIT)->get();
        $publications = (clone $publicationsQuery)->orderByDesc('published_at')->limit(self::LIMIT)->get();

        return response()->json([
            'goal' => $goal,
            'news' => ArticleResource::collection($news)->resolve(),
            'events' => CampusEventResource::collection($events)->resolve(),
            'research' => $research->all(),
            'publications' => $publications->all(),
            'counts' => [
                'news' => (clone $newsQuery)->count(),
                'events' => (clone $eventsQuery)->count(),
                'research' => (clone $researchQuery)->count(),
                'publications' => (clone $publicationsQuery)->count(),
            ],
        ]);
    }

    /**
     * @param  array<int, int>  $totals
     */
    private function tallyGoals(array &$totals, mixed $raw): void
    {
        if (! is_array($raw)) {
            return;
        }

        $seen = [];
        foreach ($raw as $value) {
            $id = (int) $value;
            if ($id < 1 || $id > 17 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $totals[$id]++;
        }
    }
}