<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\ResearchPublication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminResearchPublicationController extends Controller
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

        $query = ResearchPublication::query()->with('creator:id,name');

        if (! $request->user()?->is_admin) {
            $query->where('user_id', $request->user()->id);
        }

        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $like = '%'.$escaped.'%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('authors', 'like', $like);
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

        return response()->json($paginator);
    }

    public function show(Request $request, ResearchPublication $researchPublication): JsonResponse
    {
        $this->authorizeResearch($request, $researchPublication);

        return response()->json($researchPublication);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:research_publications,slug'],
            'excerpt' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'thumbnail_url' => ['nullable', 'string', 'max:2048'],
            'academic_program_id' => ['required', 'string', 'max:128', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'output_type' => ['required', 'string', Rule::in([
                ResearchPublication::OUTPUT_PUBLICATION,
                ResearchPublication::OUTPUT_RESEARCH,
            ])],
            'authors' => ['nullable', 'string'],
            'venue_or_journal' => ['nullable', 'string', 'max:255'],
            'link_url' => ['nullable', 'string', 'max:2048'],
            'sdg_goals' => ['nullable', 'array'],
            'sdg_goals.*' => ['integer', 'min:1', 'max:17'],
            'published_at' => ['nullable', 'date'],
        ]);

        $slug = filled($validated['slug'] ?? null)
            ? $validated['slug']
            : $this->uniqueSlug(Str::slug($validated['title']));

        $row = ResearchPublication::query()->create([
            'user_id' => $request->user()->id,
            'slug' => $slug,
            'title' => $validated['title'],
            'excerpt' => $validated['excerpt'] ?? null,
            'body' => $validated['body'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
            'thumbnail_url' => $validated['thumbnail_url'] ?? null,
            'academic_program_id' => $validated['academic_program_id'],
            'output_type' => $validated['output_type'],
            'authors' => $validated['authors'] ?? null,
            'venue_or_journal' => $validated['venue_or_journal'] ?? null,
            'link_url' => $validated['link_url'] ?? null,
            'sdg_goals' => isset($validated['sdg_goals']) ? array_values(array_unique(array_map('intval', $validated['sdg_goals']))) : null,
            'published_at' => isset($validated['published_at'])
                ? Carbon::parse($validated['published_at'])
                : null,
        ]);

        return response()->json($row, JsonResponse::HTTP_CREATED);
    }

    public function update(Request $request, ResearchPublication $researchPublication): JsonResponse
    {
        $this->authorizeResearch($request, $researchPublication);
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('research_publications', 'slug')->ignore($researchPublication->id),
            ],
            'excerpt' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'thumbnail_url' => ['nullable', 'string', 'max:2048'],
            'academic_program_id' => ['sometimes', 'required', 'string', 'max:128', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'output_type' => ['sometimes', 'required', 'string', Rule::in([
                ResearchPublication::OUTPUT_PUBLICATION,
                ResearchPublication::OUTPUT_RESEARCH,
            ])],
            'authors' => ['nullable', 'string'],
            'venue_or_journal' => ['nullable', 'string', 'max:255'],
            'link_url' => ['nullable', 'string', 'max:2048'],
            'sdg_goals' => ['nullable', 'array'],
            'sdg_goals.*' => ['integer', 'min:1', 'max:17'],
            'published_at' => ['nullable', 'date'],
        ]);

        if (array_key_exists('title', $validated)) {
            $researchPublication->title = $validated['title'];
        }

        if (array_key_exists('slug', $validated)) {
            if (filled($validated['slug'])) {
                $researchPublication->slug = $validated['slug'];
            } elseif (array_key_exists('title', $validated)) {
                $researchPublication->slug = $this->uniqueSlug(Str::slug($validated['title']), $researchPublication->id);
            }
        } elseif (array_key_exists('title', $validated)) {
            $researchPublication->slug = $this->uniqueSlug(Str::slug($validated['title']), $researchPublication->id);
        }

        foreach ([
            'excerpt', 'body', 'image_url', 'thumbnail_url', 'academic_program_id', 'output_type',
            'authors', 'venue_or_journal', 'link_url',
        ] as $field) {
            if (array_key_exists($field, $validated)) {
                $researchPublication->{$field} = $validated[$field];
            }
        }

        if (array_key_exists('sdg_goals', $validated)) {
            $researchPublication->sdg_goals = $validated['sdg_goals'] !== null
                ? array_values(array_unique(array_map('intval', $validated['sdg_goals'])))
                : null;
        }
        if (array_key_exists('published_at', $validated)) {
            $researchPublication->published_at = $validated['published_at']
                ? Carbon::parse($validated['published_at'])
                : null;
        }

        if ($researchPublication->user_id === null) {
            $researchPublication->user_id = $request->user()->id;
        }

        $researchPublication->save();

        return response()->json($researchPublication);
    }

    public function destroy(Request $request, ResearchPublication $researchPublication): JsonResponse
    {
        $this->authorizeResearch($request, $researchPublication);
        $researchPublication->delete();

        return response()->json(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function authorizeResearch(Request $request, ResearchPublication $researchPublication): void
    {
        $user = $request->user();
        if (! $user) {
            abort(JsonResponse::HTTP_FORBIDDEN);
        }
        if ($user->is_admin) {
            return;
        }
        if ($researchPublication->user_id !== $user->id) {
            abort(JsonResponse::HTTP_FORBIDDEN, 'You can only manage your own research entries.');
        }
    }

    private function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base !== '' ? $base : 'research';
        $candidate = $slug;
        $n = 1;
        while (ResearchPublication::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $slug.'-'.$n;
            $n++;
        }

        return $candidate;
    }
}
