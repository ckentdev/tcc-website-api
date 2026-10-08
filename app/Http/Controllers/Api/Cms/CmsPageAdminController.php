<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsPageAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $pages = CmsPage::query()->orderBy('slug')->get();

        return response()->json($pages);
    }

    public function show(string $slug): JsonResponse
    {
        $page = CmsPage::query()->where('slug', $slug)->firstOrFail();

        return response()->json($page);
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $page = CmsPage::query()->where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
        ]);

        if (array_key_exists('title', $validated)) {
            $page->title = $validated['title'];
        }
        if (array_key_exists('body', $validated)) {
            $page->body = $validated['body'];
        }
        $page->save();

        return response()->json($page);
    }
}
