<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if (! $user->canAccessCms()) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $user->tokens()->where('name', 'cms')->delete();

        $lifetimeDays = (int) config('cms.token_lifetime_days', 14);
        $expiresAt = $lifetimeDays > 0 ? now()->addDays($lifetimeDays) : null;
        $token = $user->createToken('cms', ['cms:access'], $expiresAt)->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => self::userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        return response()->json(self::userPayload($user));
    }

    /**
     * @return array<string, mixed>
     */
    public static function userPayload(User $user): array
    {
        $links = $user->profile_links;
        if (! is_array($links)) {
            $links = [];
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
            'must_change_password' => $user->mustChangePassword(),
            'cms_modules' => $user->resolvedCmsModules(),
            'job_title' => $user->job_title,
            'position' => $user->position,
            'bio' => $user->bio,
            'profile_photo_url' => $user->profile_photo_url,
            'cover_photo_url' => $user->cover_photo_url,
            'profile_links' => array_values(array_filter($links, function ($row) {
                return is_array($row)
                    && filled($row['label'] ?? null)
                    && filled($row['url'] ?? null);
            })),
        ];
    }
}
