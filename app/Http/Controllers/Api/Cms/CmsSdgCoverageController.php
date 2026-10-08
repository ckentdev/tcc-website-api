<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Services\SdgCoverageService;
use App\Support\CmsModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsSdgCoverageController extends Controller
{
    public function __construct(
        private readonly SdgCoverageService $coverage,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->actor($request);

        return response()->json($this->coverage->overview($user));
    }

    public function show(Request $request, int $goal): JsonResponse
    {
        $user = $this->actor($request);

        if ($goal < SdgCoverageService::GOAL_MIN || $goal > SdgCoverageService::GOAL_MAX) {
            abort(404, 'Unknown Sustainable Development Goal.');
        }

        $publishedOnly = $request->boolean('published');

        return response()->json($this->coverage->goal($user, $goal, $publishedOnly));
    }

    private function actor(Request $request): \App\Models\User
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->hasCmsModule(CmsModule::POSTS) || $user->hasCmsModule(CmsModule::RESEARCH)),
            403,
            'You do not have access to this module.',
        );

        return $user;
    }
}
