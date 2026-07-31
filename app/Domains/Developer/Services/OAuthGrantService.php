<?php

declare(strict_types=1);

namespace App\Domains\Developer\Services;

use App\Domains\Developer\Models\OAuthApplication;
use App\Domains\Developer\Models\OAuthAuthorizationCode;
use App\Domains\Developer\Models\OAuthRefreshToken;
use App\Http\Controllers\Api\V1\TokenController;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The OAuth2 authorization-code grant, implemented on top of the existing
 * oauth_applications registry and Sanctum. Supports PKCE (S256/plain) for
 * public clients and client-secret auth for confidential clients, plus refresh
 * with rotation.
 *
 * Nothing here logs or returns a client secret / raw code / raw refresh token
 * beyond the single response that hands it to the client; only hashes are
 * stored.
 */
final class OAuthGrantService
{
    /** @return list<string> */
    public function allowedScopes(): array
    {
        return TokenController::ALLOWED_ABILITIES;
    }

    /**
     * Validate and normalise a requested scope string against the allowed set.
     *
     * @return list<string>
     */
    public function resolveScopes(?string $requested): array
    {
        $scopes = array_values(array_filter(preg_split('/\s+/', trim((string) $requested)) ?: []));

        if ($scopes === []) {
            return ['read'];
        }

        $invalid = array_diff($scopes, $this->allowedScopes());

        if ($invalid !== []) {
            throw new OAuthException('invalid_scope', 'Neznámý scope: ' . implode(', ', $invalid));
        }

        return array_values(array_unique($scopes));
    }

    /**
     * Whether a redirect URI is exactly one registered for the application.
     */
    public function redirectUriAllowed(OAuthApplication $app, string $redirectUri): bool
    {
        return in_array($redirectUri, $app->redirect_uris ?? [], true);
    }

    /**
     * Mint a single-use authorization code. Returns the RAW code (only the hash
     * is stored).
     *
     * @param list<string> $scopes
     */
    public function issueCode(
        OAuthApplication $app,
        User $user,
        string $redirectUri,
        array $scopes,
        ?string $codeChallenge,
        ?string $codeChallengeMethod,
    ): string {
        $raw = Str::random(80);

        OAuthAuthorizationCode::create([
            'oauth_application_id'  => $app->id,
            'user_id'               => $user->id,
            'code_hash'             => hash('sha256', $raw),
            'redirect_uri'          => $redirectUri,
            'scopes'                => $scopes,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => $codeChallenge === null ? null : ($codeChallengeMethod ?: 'plain'),
            'expires_at'            => now()->addMinutes((int) config('oauth.authorization_code_ttl_minutes', 10)),
        ]);

        return $raw;
    }

    /**
     * Exchange an authorization code for tokens (grant_type=authorization_code).
     *
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string, scope: string}
     */
    public function redeemCode(
        string $clientId,
        string $rawCode,
        string $redirectUri,
        ?string $clientSecret,
        ?string $codeVerifier,
    ): array {
        $app = $this->activeApp($clientId);

        $code = OAuthAuthorizationCode::where('code_hash', hash('sha256', $rawCode))->first();

        if ($code === null || ! $code->isUsable() || $code->oauth_application_id !== $app->id) {
            throw new OAuthException('invalid_grant', 'Autorizační kód je neplatný nebo vypršel.');
        }

        if (! hash_equals($code->redirect_uri, $redirectUri)) {
            throw new OAuthException('invalid_grant', 'redirect_uri neodpovídá.');
        }

        $this->authenticateClient($app, $code->code_challenge, $code->code_challenge_method, $clientSecret, $codeVerifier);

        // Single-use: burn the code before issuing tokens.
        $code->forceFill(['used_at' => now()])->save();

        return $this->issueTokens($app, (int) $code->user_id, $code->scopes);
    }

    /**
     * Renew tokens (grant_type=refresh_token) with rotation.
     *
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string, scope: string}
     */
    public function refresh(string $clientId, string $rawRefreshToken, ?string $clientSecret): array
    {
        $app = $this->activeApp($clientId);

        $refresh = OAuthRefreshToken::where('token_hash', hash('sha256', $rawRefreshToken))->first();

        if ($refresh === null || ! $refresh->isUsable() || $refresh->oauth_application_id !== $app->id) {
            throw new OAuthException('invalid_grant', 'Refresh token je neplatný, vypršel nebo byl odvolán.');
        }

        // Confidential clients still authenticate on refresh; public (PKCE)
        // clients are identified by possession of the refresh token itself.
        if ($clientSecret !== null && ! Hash::check($clientSecret, $app->client_secret_hash)) {
            throw new OAuthException('invalid_client', 'Neplatný client secret.', 401);
        }

        return DB::transaction(function () use ($app, $refresh): array {
            // Rotate: revoke this refresh token and drop the access token it backed.
            $refresh->forceFill(['revoked_at' => now()])->save();

            if ($refresh->access_token_id !== null) {
                PersonalAccessToken::where('id', $refresh->access_token_id)->delete();
            }

            return $this->issueTokens($app, (int) $refresh->user_id, $refresh->scopes);
        });
    }

    private function activeApp(string $clientId): OAuthApplication
    {
        $app = OAuthApplication::where('client_id', $clientId)->where('is_active', true)->first();

        if ($app === null) {
            throw new OAuthException('invalid_client', 'Neznámý nebo neaktivní client_id.', 401);
        }

        return $app;
    }

    private function authenticateClient(
        OAuthApplication $app,
        ?string $codeChallenge,
        ?string $codeChallengeMethod,
        ?string $clientSecret,
        ?string $codeVerifier,
    ): void {
        if ($codeChallenge !== null) {
            if ($codeVerifier === null) {
                throw new OAuthException('invalid_request', 'Chybí code_verifier (PKCE).');
            }

            $computed = $codeChallengeMethod === 'S256'
                ? rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=')
                : $codeVerifier;

            if (! hash_equals($codeChallenge, $computed)) {
                throw new OAuthException('invalid_grant', 'PKCE ověření selhalo.');
            }

            return;
        }

        // No PKCE → confidential client, must present a valid secret.
        if ($clientSecret === null || ! Hash::check($clientSecret, $app->client_secret_hash)) {
            throw new OAuthException('invalid_client', 'Neplatný client secret.', 401);
        }
    }

    /**
     * @param list<string> $scopes
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string, scope: string}
     */
    private function issueTokens(OAuthApplication $app, int $userId, array $scopes): array
    {
        $user = User::findOrFail($userId);

        $accessTtl  = (int) config('oauth.access_token_ttl_minutes', 60);
        $expiresAt  = Carbon::now()->addMinutes($accessTtl);

        $newToken = $user->createToken('oauth:' . $app->name, $scopes, $expiresAt);

        $app->forceFill(['last_used_at' => now()])->save();

        $rawRefresh = Str::random(80);

        OAuthRefreshToken::create([
            'oauth_application_id' => $app->id,
            'user_id'              => $user->id,
            'token_hash'           => hash('sha256', $rawRefresh),
            'scopes'               => $scopes,
            'access_token_id'      => $newToken->accessToken->getKey(),
            'expires_at'           => now()->addDays((int) config('oauth.refresh_token_ttl_days', 30)),
        ]);

        return [
            'access_token'  => $newToken->plainTextToken,
            'token_type'    => 'Bearer',
            'expires_in'    => $accessTtl * 60,
            'refresh_token' => $rawRefresh,
            'scope'         => implode(' ', $scopes),
        ];
    }
}
