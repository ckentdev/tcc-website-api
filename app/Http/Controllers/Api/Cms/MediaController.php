<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessCms(), 403);

        $request->validate([
            'file' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
        ]);

        $path = $request->file('file')->store('cms', 'public');
        // Use the request host (incl. port) so SPA dev (Vite proxy) and artisan serve don’t rely on APP_URL
        // (which often omits :8000 and produces broken <img src> in the editor).
        $publicPath = str_replace('\\', '/', $path);
        $url = rtrim($request->getSchemeAndHttpHost(), '/').'/storage/'.$publicPath;

        return response()->json([
            'path' => $path,
            'url' => $url,
        ], JsonResponse::HTTP_CREATED);
    }

    public function storeDocument(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->canAccessCms(), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:20480'],
        ]);

        $uploaded = $request->file('file');
        $path = $uploaded->store('cms/documents', 'public');
        $publicPath = str_replace('\\', '/', $path);
        $url = rtrim($request->getSchemeAndHttpHost(), '/').'/storage/'.$publicPath;

        return response()->json([
            'path' => $path,
            'url' => $url,
            'name' => $uploaded->getClientOriginalName(),
        ], JsonResponse::HTTP_CREATED);
    }
}
