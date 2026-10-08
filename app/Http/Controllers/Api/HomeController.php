<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\CampusEventResource;
use App\Models\Article;
use App\Models\CampusEvent;
use App\Models\HeroSlide;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    /** Hero carousel: latest published posts marked featured. */
    private const FEATURED_CAROUSEL_LIMIT = 10;

    public function index(): JsonResponse
    {
        return response()->json([
            'slides' => HeroSlide::query()->orderBy('sort_order')->get(),
            /** Latest posts for home “news” grid (not necessarily featured). */
            'news' => ArticleResource::collection(
                Article::query()
                    ->published()
                    ->with('author:id,name,job_title,position,bio,profile_links,profile_photo_url,cover_photo_url')
                    ->orderByDesc('published_at')
                    ->limit(16)
                    ->get(),
            )->resolve(),
            /** Hero carousel: published + featured only (empty → client falls back to CMS slides). */
            'featured_news' => ArticleResource::collection(
                Article::query()
                    ->published()
                    ->with('author:id,name,job_title,position,bio,profile_links,profile_photo_url,cover_photo_url')
                    ->where('featured', true)
                    ->orderByDesc('published_at')
                    ->limit(self::FEATURED_CAROUSEL_LIMIT)
                    ->get(),
            )->resolve(),
            'events' => CampusEventResource::collection(
                CampusEvent::query()
                    ->with('author:id,name,job_title,position,bio,profile_links,profile_photo_url,cover_photo_url')
                    ->upcoming()
                    ->orderBy('starts_at')
                    ->limit(5)
                    ->get(),
            )->resolve(),
        ]);
    }
}
