<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ResearchPublication;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SdgCoverageService
{
    public const GOAL_MIN = 1;

    public const GOAL_MAX = 17;

    /**
     * @return array<string, mixed>
     */
    public function overview(User $user): array
    {
        $articles = $this->articleQuery($user)->get(['id', 'sdg_goals', 'published_at']);
        $researchRows = $this->researchQuery($user)->get(['id', 'sdg_goals', 'published_at', 'output_type']);

        $news = $this->emptyBucket();
        $research = $this->emptyBucket();
        $publications = $this->emptyBucket();
        $goals = [];
        for ($id = self::GOAL_MIN; $id <= self::GOAL_MAX; $id++) {
            $goals[$id] = $this->emptyGoal($id);
        }

        foreach ($articles as $article) {
            $published = $this->isPublished($article->published_at);
            $ids = $this->goalIds($article->sdg_goals);
            $this->tallyBucket($news, $published, $ids !== []);
            foreach ($ids as $id) {
                $this->tallyGoalField($goals[$id]['articles'], $published);
            }
        }

        foreach ($researchRows as $row) {
            $published = $this->isPublished($row->published_at);
            $ids = $this->goalIds($row->sdg_goals);
            $isPublication = $row->output_type === ResearchPublication::OUTPUT_PUBLICATION;
            $field = $isPublication ? 'publications' : 'research';
            if ($isPublication) {
                $this->tallyBucket($publications, $published, $ids !== []);
            } else {
                $this->tallyBucket($research, $published, $ids !== []);
            }
            foreach ($ids as $id) {
                $this->tallyGoalField($goals[$id][$field], $published);
            }
        }

        $goalList = array_values(array_map(function (array $goal): array {
            $goal['total'] = $goal['articles']['total'] + $goal['research']['total'] + $goal['publications']['total'];
            $goal['published_total'] = $goal['articles']['published'] + $goal['research']['published'] + $goal['publications']['published'];

            return $goal;
        }, $goals));

        $goalsWithContent = count(array_filter($goalList, static fn (array $goal): bool => $goal['total'] > 0));
        $goalsWithPublished = count(array_filter($goalList, static fn (array $goal): bool => $goal['published_total'] > 0));

        return [
            'scope' => $user->is_admin ? 'all' : 'own',
            'summary' => [
                'articles' => $news,
                'research' => $research,
                'publications' => $publications,
                'goals_with_content' => $goalsWithContent,
                'goals_with_published' => $goalsWithPublished,
            ],
            'goals' => $goalList,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function goal(User $user, int $goal, bool $publishedOnly = false): array
    {
        $articlesQuery = $this->articleQuery($user)->whereJsonContains('sdg_goals', $goal);
        $researchQuery = $this->researchQuery($user)->whereJsonContains('sdg_goals', $goal);

        if ($publishedOnly) {
            $articlesQuery->published();
            $researchQuery->published();
        }

        $articles = $articlesQuery
            ->orderByRaw('published_at IS NULL')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'slug', 'published_at']);

        $researchRows = $researchQuery
            ->orderByRaw('published_at IS NULL')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'slug', 'published_at', 'output_type']);

        return [
            'id' => $goal,
            'scope' => $user->is_admin ? 'all' : 'own',
            'articles' => $articles->map(fn (Article $row) => $this->contentPayload($row, 'article'))->values()->all(),
            'research' => $researchRows
                ->filter(fn (ResearchPublication $row) => $row->output_type !== ResearchPublication::OUTPUT_PUBLICATION)
                ->map(fn (ResearchPublication $row) => $this->contentPayload($row, 'research'))
                ->values()
                ->all(),
            'publications' => $researchRows
                ->filter(fn (ResearchPublication $row) => $row->output_type === ResearchPublication::OUTPUT_PUBLICATION)
                ->map(fn (ResearchPublication $row) => $this->contentPayload($row, 'publication'))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Builder<Article>
     */
    private function articleQuery(User $user): Builder
    {
        $query = Article::query();
        if (! $user->is_admin) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    /**
     * @return Builder<ResearchPublication>
     */
    private function researchQuery(User $user): Builder
    {
        $query = ResearchPublication::query();
        if (! $user->is_admin) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    /**
     * @return list<int>
     */
    private function goalIds(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            $id = (int) $value;
            if ($id >= self::GOAL_MIN && $id <= self::GOAL_MAX) {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }

    private function isPublished(mixed $publishedAt): bool
    {
        if (! $publishedAt instanceof Carbon) {
            return false;
        }

        return $publishedAt->lte(Carbon::now());
    }

    /**
     * @return array{total: int, tagged: int, untagged: int, published: int, draft: int, tagged_published: int, tagged_draft: int}
     */
    private function emptyBucket(): array
    {
        return [
            'total' => 0,
            'tagged' => 0,
            'untagged' => 0,
            'published' => 0,
            'draft' => 0,
            'tagged_published' => 0,
            'tagged_draft' => 0,
        ];
    }

    /**
     * @return array{id: int, articles: array{total: int, published: int, draft: int}, research: array{total: int, published: int, draft: int}, publications: array{total: int, published: int, draft: int}, total: int, published_total: int}
     */
    private function emptyGoal(int $id): array
    {
        return [
            'id' => $id,
            'articles' => ['total' => 0, 'published' => 0, 'draft' => 0],
            'research' => ['total' => 0, 'published' => 0, 'draft' => 0],
            'publications' => ['total' => 0, 'published' => 0, 'draft' => 0],
            'total' => 0,
            'published_total' => 0,
        ];
    }

    /**
     * @param  array{total: int, tagged: int, untagged: int, published: int, draft: int, tagged_published: int, tagged_draft: int}  $bucket
     */
    private function tallyBucket(array &$bucket, bool $published, bool $tagged): void
    {
        $bucket['total']++;
        if ($tagged) {
            $bucket['tagged']++;
            if ($published) {
                $bucket['tagged_published']++;
            } else {
                $bucket['tagged_draft']++;
            }
        } else {
            $bucket['untagged']++;
        }
        if ($published) {
            $bucket['published']++;
        } else {
            $bucket['draft']++;
        }
    }

    /**
     * @param  array{total: int, published: int, draft: int}  $field
     */
    private function tallyGoalField(array &$field, bool $published): void
    {
        $field['total']++;
        if ($published) {
            $field['published']++;
        } else {
            $field['draft']++;
        }
    }

    /**
     * @return array{id: int, title: string, slug: string, published_at: string|null, published: bool, kind: string}
     */
    private function contentPayload(Article|ResearchPublication $row, string $kind): array
    {
        return [
            'id' => (int) $row->id,
            'title' => (string) $row->title,
            'slug' => (string) $row->slug,
            'published_at' => $row->published_at?->toIso8601String(),
            'published' => $this->isPublished($row->published_at),
            'kind' => $kind,
        ];
    }
}
