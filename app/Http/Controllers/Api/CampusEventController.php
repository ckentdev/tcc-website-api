<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampusEventResource;
use App\Models\CampusEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampusEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CampusEvent::query()
            ->with('author:id,name,job_title,position,bio,profile_links,profile_photo_url,cover_photo_url')
            ->orderBy('starts_at');

        if ($request->boolean('upcoming')) {
            $query->upcoming();
        }

        $sdg = $this->normalizeSdgFilter($request);
        if ($sdg !== null) {
            $query->whereJsonContains('sdg_goals', $sdg);
        }

        $perPage = min((int) $request->query('per_page', 12), 50);

        return CampusEventResource::collection($query->paginate($perPage))->response();
    }

    public function show(string $slug): JsonResponse
    {
        $event = CampusEvent::query()
            ->with('author:id,name,job_title,position,bio,profile_links,profile_photo_url,cover_photo_url')
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json((new CampusEventResource($event))->resolve());
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
