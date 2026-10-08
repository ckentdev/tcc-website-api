<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class StudentController extends Controller
{
    public function auth(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'userID' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:128'],
        ]);

        $response = Http::timeout(15)
            ->acceptJson()
            ->asJson()
            ->post('https://tccauth-production.up.railway.app/api/getauth', [
                'userID' => $validated['userID'],
                'password' => $validated['password'],
            ]);

        if ($response->successful()) {
            return response()->json($response->json() ?? ['ok' => true]);
        }

        return response()->json([
            'message' => 'Authentication failed.',
        ], $response->status() >= 400 && $response->status() < 600 ? $response->status() : 502);
    }
}
