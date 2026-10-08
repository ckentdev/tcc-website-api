<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Services\ContentAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsAnalyticsController extends Controller
{
    public function __construct(
        private readonly ContentAnalyticsService $analytics,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $daysParam = $request->query('days', '30');
        $days = null;
        if ($daysParam !== 'all' && $daysParam !== '') {
            $days = max(1, min(365, (int) $daysParam));
        }

        return response()->json($this->analytics->dashboard($days));
    }

    public function author(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $daysParam = $request->query('days', '30');
        $days = null;
        if ($daysParam !== 'all' && $daysParam !== '') {
            $days = max(1, min(365, (int) $daysParam));
        }

        return response()->json($this->analytics->authorDashboard((int) $user->id, $days));
    }
}
