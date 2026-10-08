<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:120'],
            'profile_links' => ['nullable', 'array', 'max:12'],
            'profile_links.*.label' => ['required', 'string', 'max:80'],
            'profile_links.*.url' => ['required', 'url', 'max:2048'],
            'profile_photo_url' => ['nullable', 'string', 'max:2048'],
            'cover_photo_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $user->name = $validated['name'];
        $user->job_title = $validated['job_title'] ?? null;
        $user->position = $validated['position'] ?? null;
        $user->bio = filled($validated['bio'] ?? null) ? trim((string) $validated['bio']) : null;
        $user->profile_photo_url = filled($validated['profile_photo_url'] ?? null)
            ? $validated['profile_photo_url']
            : null;
        $user->cover_photo_url = filled($validated['cover_photo_url'] ?? null)
            ? $validated['cover_photo_url']
            : null;
        $user->profile_links = isset($validated['profile_links'])
            ? array_values($validated['profile_links'])
            : null;
        $user->save();

        return response()->json(AuthController::userPayload($user));
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors' => ['current_password' => ['The current password is incorrect.']],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $defaultPassword = (string) config('cms.default_author_password', '12345678');
        if ($defaultPassword !== '' && $validated['password'] === $defaultPassword) {
            throw ValidationException::withMessages([
                'password' => ['Choose a different password than the temporary default.'],
            ]);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json([
            'message' => 'Password updated.',
            'user' => AuthController::userPayload($user->fresh()),
        ]);
    }
}
