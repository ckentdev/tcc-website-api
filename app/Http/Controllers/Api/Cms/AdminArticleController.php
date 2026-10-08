<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Support\PlainText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AdminArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 50);
        $search = trim((string) $request->query('search', ''));
        if (strlen($search) > 200) {
            $search = mb_substr($search, 0, 200);
        }

        $sort = (string) $request->query('sort', 'created_desc');
        $allowedSorts = [
            'created_desc',
            'created_asc',
            'updated_desc',
            'updated_asc',
            'published_desc',
            'published_asc',
            'title_asc',
            'title_desc',
        ];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_desc';
        }

        $query = Article::query()->with('author:id,name');

        if (! $request->user()?->is_admin) {
            $query->where('user_id', $request->user()->id);
        }

        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $like = '%'.$escaped.'%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            });
        }

        match ($sort) {
            'created_asc' => $query->orderBy('created_at', 'asc')->orderBy('id', 'asc'),
            'updated_desc' => $query->orderByDesc('updated_at')->orderByDesc('id'),
            'updated_asc' => $query->orderBy('updated_at', 'asc')->orderBy('id', 'asc'),
            'published_desc' => $query->orderByRaw('published_at IS NULL, published_at DESC')->orderByDesc('created_at'),
            'published_asc' => $query->orderByRaw('published_at IS NULL, published_at ASC')->orderByDesc('created_at'),
            'title_asc' => $query->orderBy('title', 'asc')->orderByDesc('created_at'),
            'title_desc' => $query->orderByDesc('title')->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        $paginator = $query->paginate($perPage);

        return ArticleResource::collection($paginator)->response();
    }

    public function show(Request $request, Article $article): JsonResponse
    {
        $this->authorizeArticle($request, $article);
        $article->load('author:id,name');

        return response()->json((new ArticleResource($article))->resolve());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:articles,slug'],
            'excerpt' => ['nullable', 'string'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'og_image_alt' => ['nullable', 'string', 'max:255'],
            'no_index' => ['sometimes', 'boolean'],
            'body' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'thumbnail_url' => ['nullable', 'string', 'max:2048'],
            'sdg_goals' => ['nullable', 'array'],
            'sdg_goals.*' => ['integer', 'min:1', 'max:17'],
            'featured' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        $slug = filled($validated['slug'] ?? null)
            ? $validated['slug']
            : Article::slugFromTitle($validated['title']);

        $images = $this->normalizeArticleImages(
            $validated['thumbnail_url'] ?? null,
            $validated['image_url'] ?? null,
        );

        $article = Article::query()->create([
            'user_id' => $request->user()->id,
            'slug' => $slug,
            'title' => $validated['title'],
            'excerpt' => filled($validated['excerpt'] ?? null)
                ? $validated['excerpt']
                : (PlainText::excerptFromBody($validated['body'] ?? null) ?: null),
            'seo_title' => $validated['seo_title'] ?? null,
            'seo_description' => $validated['seo_description'] ?? null,
            'og_image_alt' => $validated['og_image_alt'] ?? null,
            'no_index' => $validated['no_index'] ?? false,
            'body' => $validated['body'] ?? null,
            'image_url' => $images['image_url'],
            'thumbnail_url' => $images['thumbnail_url'],
            'sdg_goals' => isset($validated['sdg_goals']) ? array_values(array_unique(array_map('intval', $validated['sdg_goals']))) : null,
            'featured' => $validated['featured'] ?? false,
            'published_at' => isset($validated['published_at'])
                ? Carbon::parse($validated['published_at'])
                : null,
        ]);

        $article->load('author:id,name');

        return response()->json((new ArticleResource($article))->resolve(), JsonResponse::HTTP_CREATED);
    }

    public function update(Request $request, Article $article): JsonResponse
    {
        $this->authorizeArticle($request, $article);
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('articles', 'slug')->ignore($article->id),
            ],
            'excerpt' => ['nullable', 'string'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'og_image_alt' => ['nullable', 'string', 'max:255'],
            'no_index' => ['sometimes', 'boolean'],
            'body' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'thumbnail_url' => ['nullable', 'string', 'max:2048'],
            'sdg_goals' => ['nullable', 'array'],
            'sdg_goals.*' => ['integer', 'min:1', 'max:17'],
            'featured' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        if (array_key_exists('title', $validated)) {
            $article->title = $validated['title'];
        }

        if (array_key_exists('slug', $validated)) {
            if (filled($validated['slug'])) {
                $article->slug = $validated['slug'];
            } elseif (array_key_exists('title', $validated)) {
                $article->slug = Article::slugFromTitle($validated['title'], $article->id);
            }
        } elseif (array_key_exists('title', $validated)) {
            $article->slug = Article::slugFromTitle($validated['title'], $article->id);
        }

        if (array_key_exists('excerpt', $validated)) {
            $article->excerpt = filled($validated['excerpt'])
                ? $validated['excerpt']
                : (PlainText::excerptFromBody(
                    array_key_exists('body', $validated) ? $validated['body'] : $article->body
                ) ?: null);
        } elseif (array_key_exists('body', $validated) && ! filled($article->excerpt)) {
            $article->excerpt = PlainText::excerptFromBody($validated['body']) ?: $article->excerpt;
        }
        if (array_key_exists('seo_title', $validated)) {
            $article->seo_title = $validated['seo_title'];
        }
        if (array_key_exists('seo_description', $validated)) {
            $article->seo_description = $validated['seo_description'];
        }
        if (array_key_exists('og_image_alt', $validated)) {
            $article->og_image_alt = $validated['og_image_alt'];
        }
        if (array_key_exists('no_index', $validated)) {
            $article->no_index = $validated['no_index'];
        }
        if (array_key_exists('body', $validated)) {
            $article->body = $validated['body'];
        }
        if (array_key_exists('image_url', $validated) || array_key_exists('thumbnail_url', $validated)) {
            $images = $this->normalizeArticleImages(
                array_key_exists('thumbnail_url', $validated) ? $validated['thumbnail_url'] : $article->thumbnail_url,
                array_key_exists('image_url', $validated) ? $validated['image_url'] : $article->image_url,
            );
            $article->image_url = $images['image_url'];
            $article->thumbnail_url = $images['thumbnail_url'];
        }
        if (array_key_exists('sdg_goals', $validated)) {
            $article->sdg_goals = $validated['sdg_goals'] !== null
                ? array_values(array_unique(array_map('intval', $validated['sdg_goals'])))
                : null;
        }
        if (array_key_exists('featured', $validated)) {
            $article->featured = $validated['featured'];
        }
        if (array_key_exists('published_at', $validated)) {
            $article->published_at = $validated['published_at']
                ? Carbon::parse($validated['published_at'])
                : null;
        }

        if ($article->user_id === null) {
            $article->user_id = $request->user()->id;
        }

        $article->save();
        $article->load('author:id,name');

        return response()->json((new ArticleResource($article))->resolve());
    }

    public function destroy(Request $request, Article $article): JsonResponse
    {
        $this->authorizeArticle($request, $article);
        $article->delete();

        return response()->json(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function authorizeArticle(Request $request, Article $article): void
    {
        $user = $request->user();
        if (! $user) {
            abort(JsonResponse::HTTP_FORBIDDEN);
        }
        if ($user->is_admin) {
            return;
        }
        if ($article->user_id !== $user->id) {
            abort(JsonResponse::HTTP_FORBIDDEN, 'You can only manage your own articles.');
        }
    }

    /**
     * @return array{thumbnail_url: string, image_url: string|null}
     */
    private function normalizeArticleImages(?string $thumbnailUrl, ?string $imageUrl): array
    {
        $thumb = filled($thumbnailUrl) ? trim($thumbnailUrl) : null;
        $image = filled($imageUrl) ? trim($imageUrl) : null;

        if ($thumb === null && $image === null) {
            return [
                'thumbnail_url' => Article::DEFAULT_THUMBNAIL_URL,
                'image_url' => null,
            ];
        }

        return [
            'thumbnail_url' => $thumb ?? $image,
            'image_url' => $image,
        ];
    }
}
