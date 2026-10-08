<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use Illuminate\Http\JsonResponse;

class PublicCmsPageController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $page = CmsPage::query()->where('slug', $slug)->first();

        return response()->json([
            'slug' => $slug,
            'title' => $page?->title,
            'body' => $page?->body,
            'found' => $page !== null,
        ]);
    }
}
