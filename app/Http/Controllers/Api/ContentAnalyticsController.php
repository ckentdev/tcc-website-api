<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentAnalyticsEvent;
use App\Services\ContentAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContentAnalyticsController extends Controller
{
    public function __construct(
        private readonly ContentAnalyticsService $analytics,
    ) {}

    public function track(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content_type' => ['required', 'string', Rule::in([
                ContentAnalyticsEvent::TYPE_ARTICLE,
                ContentAnalyticsEvent::TYPE_RESEARCH,
                ContentAnalyticsEvent::TYPE_SITE_PAGE,
            ])],
            'event_type' => ['required', 'string', Rule::in([
                ContentAnalyticsEvent::EVENT_CLICK,
                ContentAnalyticsEvent::EVENT_VIEW,
            ])],
            'content_id' => ['nullable', 'integer', 'min:1'],
            'content_slug' => ['nullable', 'string', 'max:200'],
        ]);

        if (empty($data['content_id']) && empty($data['content_slug'])) {
            return response()->json(['message' => 'content_id or content_slug is required.'], 422);
        }

        $this->analytics->track(
            $data['content_type'],
            $data['event_type'],
            isset($data['content_id']) ? (int) $data['content_id'] : null,
            isset($data['content_slug']) ? trim($data['content_slug']) : null,
        );

        return response()->json(['ok' => true]);
    }
}
