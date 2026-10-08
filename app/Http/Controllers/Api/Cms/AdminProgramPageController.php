<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\ProgramPage;
use App\Support\ProgramOfferingMap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminProgramPageController extends Controller
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

        $query = ProgramPage::query()->with('creator:id,name');

        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $like = '%'.$escaped.'%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('academic_program_id', 'like', $like);
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

        return response()->json($query->paginate($perPage));
    }

    public function show(Request $request, ProgramPage $programPage): JsonResponse
    {
        $this->authorizeProgram($request, $programPage);

        return response()->json($programPage);
    }

    public function showByOffering(string $offering): JsonResponse
    {
        if (! ProgramOfferingMap::isKnown($offering)) {
            abort(JsonResponse::HTTP_NOT_FOUND, 'Unknown program offering.');
        }

        $row = $this->findByOffering($offering);
        if (! $row) {
            return response()->json(null);
        }

        return response()->json($row);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'excerpt' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'info_pdf_url' => ['nullable', 'string', 'max:2048'],
            'info_pdf_name' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'thumbnail_url' => ['nullable', 'string', 'max:2048'],
            'org_chart_url' => ['nullable', 'string', 'max:2048'],
            'org_chart_urls' => ['nullable', 'array', 'max:20'],
            'org_chart_urls.*' => ['string', 'max:2048'],
            'org_chart_body' => ['nullable', 'string'],
            'academic_program_id' => ['required', 'string', 'max:128', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'link_url' => ['nullable', 'string', 'max:2048'],
            'sdg_goals' => ['nullable', 'array'],
            'sdg_goals.*' => ['integer', 'min:1', 'max:17'],
            'published_at' => ['nullable', 'date'],
        ]);

        $offeringSlug = ProgramOfferingMap::offeringSlug($validated['academic_program_id']);
        if ($offeringSlug === null) {
            throw ValidationException::withMessages([
                'academic_program_id' => 'Unknown program offering.',
            ]);
        }
        $validated['academic_program_id'] = $offeringSlug;

        $existing = $this->findByOffering($offeringSlug);
        if ($existing) {
            return $this->update($request, $existing);
        }

        $slug = filled($validated['slug'] ?? null)
            ? $validated['slug']
            : $this->uniqueSlug($offeringSlug);
        if (ProgramPage::query()->where('slug', $slug)->exists()) {
            $slug = $this->uniqueSlug($slug);
        }

        $chartUrls = $this->resolvedOrgChartUrls($validated);

        $row = ProgramPage::query()->create([
            'user_id' => $request->user()->id,
            'slug' => $slug,
            'title' => $validated['title'],
            'excerpt' => $validated['excerpt'] ?? null,
            'body' => $validated['body'] ?? null,
            'info_pdf_url' => $validated['info_pdf_url'] ?? null,
            'info_pdf_name' => $validated['info_pdf_name'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
            'thumbnail_url' => $validated['thumbnail_url'] ?? null,
            'org_chart_url' => $chartUrls[0] ?? null,
            'org_chart_urls' => $chartUrls !== [] ? $chartUrls : null,
            'org_chart_body' => $validated['org_chart_body'] ?? null,
            'academic_program_id' => $offeringSlug,
            'link_url' => $validated['link_url'] ?? null,
            'sdg_goals' => isset($validated['sdg_goals']) ? array_values(array_unique(array_map('intval', $validated['sdg_goals']))) : null,
            'published_at' => isset($validated['published_at'])
                ? Carbon::parse($validated['published_at'])
                : null,
        ]);

        return response()->json($row, JsonResponse::HTTP_CREATED);
    }

    public function update(Request $request, ProgramPage $programPage): JsonResponse
    {
        $this->authorizeProgram($request, $programPage);
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('program_pages', 'slug')->ignore($programPage->id),
            ],
            'excerpt' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'info_pdf_url' => ['nullable', 'string', 'max:2048'],
            'info_pdf_name' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'thumbnail_url' => ['nullable', 'string', 'max:2048'],
            'org_chart_url' => ['nullable', 'string', 'max:2048'],
            'org_chart_urls' => ['nullable', 'array', 'max:20'],
            'org_chart_urls.*' => ['string', 'max:2048'],
            'org_chart_body' => ['nullable', 'string'],
            'academic_program_id' => ['sometimes', 'required', 'string', 'max:128', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'link_url' => ['nullable', 'string', 'max:2048'],
            'sdg_goals' => ['nullable', 'array'],
            'sdg_goals.*' => ['integer', 'min:1', 'max:17'],
            'published_at' => ['nullable', 'date'],
        ]);

        if (array_key_exists('title', $validated)) {
            $programPage->title = $validated['title'];
        }

        if (array_key_exists('slug', $validated)) {
            if (filled($validated['slug'])) {
                $programPage->slug = $validated['slug'];
            } elseif (array_key_exists('title', $validated)) {
                $programPage->slug = $this->uniqueSlug(Str::slug($validated['title']), $programPage->id);
            }
        } elseif (array_key_exists('title', $validated)) {
            $programPage->slug = $this->uniqueSlug(Str::slug($validated['title']), $programPage->id);
        }

        if (array_key_exists('academic_program_id', $validated)) {
            $offeringSlug = ProgramOfferingMap::offeringSlug($validated['academic_program_id']);
            if ($offeringSlug === null) {
                throw ValidationException::withMessages([
                    'academic_program_id' => 'Unknown program offering.',
                ]);
            }
            $programPage->academic_program_id = $offeringSlug;
        }

        foreach (['excerpt', 'body', 'info_pdf_url', 'info_pdf_name', 'image_url', 'thumbnail_url', 'link_url', 'org_chart_body'] as $field) {
            if (array_key_exists($field, $validated)) {
                $programPage->{$field} = $validated[$field];
            }
        }

        if (array_key_exists('org_chart_urls', $validated) || array_key_exists('org_chart_url', $validated)) {
            $chartUrls = $this->resolvedOrgChartUrls($validated);
            $programPage->org_chart_urls = $chartUrls !== [] ? $chartUrls : null;
            $programPage->org_chart_url = $chartUrls[0] ?? null;
        }

        if (array_key_exists('sdg_goals', $validated)) {
            $programPage->sdg_goals = $validated['sdg_goals'] !== null
                ? array_values(array_unique(array_map('intval', $validated['sdg_goals'])))
                : null;
        }
        if (array_key_exists('published_at', $validated)) {
            $programPage->published_at = $validated['published_at']
                ? Carbon::parse($validated['published_at'])
                : null;
        }

        if ($programPage->user_id === null) {
            $programPage->user_id = $request->user()->id;
        }

        $programPage->save();

        return response()->json($programPage);
    }

    public function destroy(Request $request, ProgramPage $programPage): JsonResponse
    {
        $this->authorizeProgram($request, $programPage);
        $programPage->delete();

        return response()->json(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function findByOffering(string $offering): ?ProgramPage
    {
        $keys = ProgramOfferingMap::filterKeys($offering);
        if ($keys === []) {
            return null;
        }

        return ProgramPage::query()
            ->whereIn('academic_program_id', $keys)
            ->orderByDesc('updated_at')
            ->first();
    }

    private function authorizeProgram(Request $request, ProgramPage $programPage): void
    {
        if (! $request->user()) {
            abort(JsonResponse::HTTP_FORBIDDEN);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<string>
     */
    private function resolvedOrgChartUrls(array $validated): array
    {
        if (array_key_exists('org_chart_urls', $validated)) {
            return $this->normalizedOrgChartUrls(is_array($validated['org_chart_urls']) ? $validated['org_chart_urls'] : null);
        }

        $single = $validated['org_chart_url'] ?? null;

        return is_string($single) ? $this->normalizedOrgChartUrls([$single]) : [];
    }

    /**
     * @param  list<mixed>|null  $urls
     * @return list<string>
     */
    private function normalizedOrgChartUrls(?array $urls): array
    {
        if ($urls === null) {
            return [];
        }

        $out = [];
        foreach ($urls as $url) {
            if (! is_string($url)) {
                continue;
            }
            $trimmed = trim($url);
            if ($trimmed !== '' && strlen($trimmed) <= 2048) {
                $out[] = $trimmed;
            }
        }

        return array_values(array_unique($out));
    }

    private function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base !== '' ? $base : 'program';
        $candidate = $slug;
        $n = 1;
        while (ProgramPage::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $slug.'-'.$n;
            $n++;
        }

        return $candidate;
    }
}
