<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $user  = $request->user();
        abort_if($user === null, 403);

        // Limit: max 5 tokens per user
        if ($user->tokens()->count() >= 5) {
            return response()->json(['error' => 'Maximální počet tokenů (5) byl dosažen.'], 422);
        }

        $token = $user->createToken($validated['name'], ['read']);

        return response()->json([
            'token'      => $token->plainTextToken,
            'name'       => $validated['name'],
            'created_at' => now()->toIso8601String(),
        ], 201);
    }

    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $user->tokens()->where('id', $tokenId)->delete();

        return back()->with('status', 'API token byl odstraněn.');
    }
}
