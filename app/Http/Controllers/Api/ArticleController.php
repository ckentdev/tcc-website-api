<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Article::query()
            ->published()
            ->with('author:id,name,is_admin,job_title,position,bio,profile_links,profile_photo_url,cover_photo_url')
            ->orderByDesc('published_at');

        if ($request->boolean('featured')) {
            $query->where('featured', true);
        }

        $this->applySearch($query, $this->normalizeSearch($request->query('q')));

        $year = $this->normalizeYearFilter($request);
        if ($year !== null) {
            $query->whereYear('published_at', $year);
        }

        $sdg = $this->normalizeSdgFilter($request);
        if ($sdg !== null) {
            $query->whereJsonContains('sdg_goals', $sdg);
        }

        $perPage = min((int) $request->query('per_page', 12), 50);

        return ArticleResource::collection($query->paginate($perPage))->response();
    }

    public function show(string $slug): JsonResponse
    {
        $article = Article::query()
            ->published()
            ->with('author:id,name,is_admin,job_title,position,bio,profile_links,profile_photo_url,cover_photo_url')
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json((new ArticleResource($article))->resolve());
    }

    private function applySearch(Builder $query, ?string $search): void
    {
        if ($search === null) {
            return;
        }

        $needle = '%'.addcslashes($search, '%_\\').'%';
        $query->where(function (Builder $sub) use ($needle): void {
            $sub->where('title', 'like', $needle)
                ->orWhere('excerpt', 'like', $needle);
        });
    }

    private function normalizeSearch(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $q = trim($raw);
        if ($q === '') {
            return null;
        }

        return strlen($q) > 120 ? substr($q, 0, 120) : $q;
    }

    private function normalizeYearFilter(Request $request): ?int
    {
        if (! $request->filled('year')) {
            return null;
        }

        $y = (int) $request->query('year');
        $max = (int) date('Y') + 1;

        return ($y >= 1900 && $y <= $max) ? $y : null;
    }

    private function normalizeSdgFilter(Request $request): ?int
    {
        if (! $request->filled('sdg')) {
            return null;
        }

        $sdg = (int) $request->query('sdg');

        return ($sdg >= 1 && $sdg <= 17) ? $sdg : null;
    }
}
