<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\AuthorResource;
use App\Models\Article;
use App\Models\ResearchPublication;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AuthorController extends Controller
{
    public function show(Request $request, User $user): JsonResponse
    {
        $articlePerPage = min((int) $request->query('article_per_page', 9), 50);
        $researchPerPage = min((int) $request->query('research_per_page', 9), 50);

        $content = (string) $request->query('content', 'all');
        if (! in_array($content, ['all', 'articles', 'research'], true)) {
            $content = 'all';
        }

        $search = $this->normalizeSearch($request->query('q'));
        $year = $this->normalizeYearFilter($request);

        $articles = $content === 'research'
            ? $this->emptyPaginator($articlePerPage, 'article_page')
            : $this->paginateArticles($user, $articlePerPage, $search, $year);

        $research = $content === 'articles'
            ? $this->emptyPaginator($researchPerPage, 'research_page')
            : $this->paginateResearch($user, $researchPerPage, $search, $year, $request);

        return response()->json([
            'author' => (new AuthorResource($user))->resolve(),
            'summary' => [
                'articles_total' => Article::query()->published()->where('user_id', $user->id)->count(),
                'research_total' => ResearchPublication::query()->published()->where('user_id', $user->id)->count(),
            ],
            'articles' => ArticleResource::collection($articles)->response()->getData(true),
            'research' => $research,
        ]);
    }

    private function paginateArticles(User $user, int $perPage, ?string $search, ?int $year): LengthAwarePaginator
    {
        $query = Article::query()
            ->published()
            ->where('user_id', $user->id);

        $this->applyArticleSearch($query, $search);

        if ($year !== null) {
            $query->whereYear('published_at', $year);
        }

        return $query
            ->orderByDesc('published_at')
            ->paginate($perPage, ['*'], 'article_page');
    }

    private function paginateResearch(User $user, int $perPage, ?string $search, ?int $year, Request $request): LengthAwarePaginator
    {
        $query = ResearchPublication::query()
            ->published()
            ->where('user_id', $user->id);

        $this->applyResearchSearch($query, $search);

        if ($year !== null) {
            $query->whereYear('published_at', $year);
        }

        $outputType = $request->query('research_output_type');
        if (in_array($outputType, [ResearchPublication::OUTPUT_PUBLICATION, ResearchPublication::OUTPUT_RESEARCH], true)) {
            $query->where('output_type', $outputType);
        }

        return $query
            ->orderByDesc('published_at')
            ->paginate($perPage, ['*'], 'research_page');
    }

    private function applyArticleSearch(Builder $query, ?string $search): void
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

    private function applyResearchSearch(Builder $query, ?string $search): void
    {
        if ($search === null) {
            return;
        }

        $needle = '%'.addcslashes($search, '%_\\').'%';
        $query->where(function (Builder $sub) use ($needle): void {
            $sub->where('title', 'like', $needle)
                ->orWhere('excerpt', 'like', $needle)
                ->orWhere('authors', 'like', $needle)
                ->orWhere('venue_or_journal', 'like', $needle);
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

    private function emptyPaginator(int $perPage, string $pageName): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, 1, [
            'path' => request()->url(),
            'pageName' => $pageName,
        ]);
    }
}
