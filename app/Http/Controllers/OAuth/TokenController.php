<?php

declare(strict_types=1);

namespace App\Http\Controllers\OAuth;

use App\Domains\Developer\Services\OAuthException;
use App\Domains\Developer\Services\OAuthGrantService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OAuth2 token endpoint (POST /oauth/token). Public — no session — authenticated
 * by client credentials or PKCE. Supports authorization_code and refresh_token
 * grants. Errors follow RFC 6749 §5.2 ({"error": "...", ...}).
 */
final class TokenController extends Controller
{
    public function __construct(private readonly OAuthGrantService $grants) {}

    public function issue(Request $request): JsonResponse
    {
        try {
            $tokens = match ((string) $request->input('grant_type')) {
                'authorization_code' => $this->authorizationCode($request),
                'refresh_token'      => $this->refreshToken($request),
                default              => throw new OAuthException('unsupported_grant_type', 'Nepodporovaný grant_type.'),
            };
        } catch (OAuthException $e) {
            return response()->json(['error' => $e->error, 'error_description' => $e->description], $e->status);
        }

        // Access tokens must never be cached by intermediaries (RFC 6749 §5.1).
        return response()->json($tokens)
            ->header('Cache-Control', 'no-store')
            ->header('Pragma', 'no-cache');
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string, scope: string}
     */
    private function authorizationCode(Request $request): array
    {
        $this->require($request, ['code', 'redirect_uri', 'client_id']);

        return $this->grants->redeemCode(
            clientId: (string) $request->input('client_id'),
            rawCode: (string) $request->input('code'),
            redirectUri: (string) $request->input('redirect_uri'),
            clientSecret: $this->input($request, 'client_secret'),
            codeVerifier: $this->input($request, 'code_verifier'),
        );
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string, scope: string}
     */
    private function refreshToken(Request $request): array
    {
        $this->require($request, ['refresh_token', 'client_id']);

        return $this->grants->refresh(
            clientId: (string) $request->input('client_id'),
            rawRefreshToken: (string) $request->input('refresh_token'),
            clientSecret: $this->input($request, 'client_secret'),
        );
    }

    /** @param list<string> $keys */
    private function require(Request $request, array $keys): void
    {
        foreach ($keys as $key) {
            if ($this->input($request, $key) === null) {
                throw new OAuthException('invalid_request', "Chybí parametr: {$key}.");
            }
        }
    }

    private function input(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
