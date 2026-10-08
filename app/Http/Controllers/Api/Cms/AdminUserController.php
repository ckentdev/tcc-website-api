<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CmsModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->orderByDesc('is_admin')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_admin', 'cms_modules', 'created_at']);

        return response()->json(['data' => $users]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'cms_modules' => ['required', 'array', 'min:1'],
            'cms_modules.*' => ['string', 'in:'.implode(',', CmsModule::ALL)],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_admin' => false,
            'cms_modules' => CmsModule::normalize($validated['cms_modules']),
        ]);

        return response()->json($this->userPayload($user), JsonResponse::HTTP_CREATED);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['sometimes', 'nullable', 'string', Password::defaults()],
            'cms_modules' => ['sometimes', 'array', 'min:1'],
            'cms_modules.*' => ['string', 'in:'.implode(',', CmsModule::ALL)],
        ]);

        if ($user->is_admin && $request->has('cms_modules')) {
            abort(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Administrator accounts have access to all modules.');
        }

        if (array_key_exists('name', $validated)) {
            $user->name = $validated['name'];
        }

        if (array_key_exists('email', $validated)) {
            $user->email = $validated['email'];
        }

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        if (! $user->is_admin && array_key_exists('cms_modules', $validated)) {
            $user->cms_modules = CmsModule::normalize($validated['cms_modules']);
        }

        $user->save();

        return response()->json($this->userPayload($user));
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            abort(JsonResponse::HTTP_FORBIDDEN, 'Use My Account to change your own password.');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json(['message' => 'Password reset successfully.']);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            abort(JsonResponse::HTTP_FORBIDDEN, 'You cannot delete your own account.');
        }

        $adminEmail = strtolower((string) config('cms.admin_email'));
        if ($adminEmail !== '' && strtolower($user->email) === $adminEmail) {
            abort(JsonResponse::HTTP_FORBIDDEN, 'The primary administrator account cannot be deleted.');
        }

        if ($user->is_admin && User::query()->where('is_admin', true)->count() <= 1) {
            abort(JsonResponse::HTTP_FORBIDDEN, 'At least one administrator must remain.');
        }

        $user->delete();

        return response()->json(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
            'cms_modules' => $user->resolvedCmsModules(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
