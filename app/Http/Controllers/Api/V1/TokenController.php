<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    /** Valid abilities that can be granted to a PAT. */
    public const ALLOWED_ABILITIES = ['read', 'write:tickets', 'write:credit', 'write:orders', 'manage:tokens'];

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        abort_unless($user->tokenCan('manage:tokens') || $user->tokenCan('read'), 403, 'Token nemá oprávnění vytvářet nové tokeny.');

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:80'],
            'abilities'   => ['sometimes', 'array'],
            'abilities.*' => ['string', 'in:' . implode(',', self::ALLOWED_ABILITIES)],
        ]);

        if ($user->tokens()->count() >= 5) {
            return response()->json(['error' => 'Maximální počet tokenů (5) byl dosažen.'], 422);
        }

        $abilities = $validated['abilities'] ?? ['read'];
        if (! in_array('read', $abilities, true)) {
            $abilities[] = 'read';
        }

        $token = $user->createToken($validated['name'], $abilities);

        return response()->json([
            'token'      => $token->plainTextToken,
            'name'       => $validated['name'],
            'abilities'  => $abilities,
            'created_at' => now()->toIso8601String(),
        ], 201);
    }

    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $deleted = $user->tokens()->where('id', $tokenId)->delete();

        if ($deleted === 0) {
            return response()->json(['error' => 'Token nenalezen.'], 404);
        }

        return response()->json(['message' => 'Token byl odstraněn.']);
    }
}
