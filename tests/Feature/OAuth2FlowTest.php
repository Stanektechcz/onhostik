<?php

declare(strict_types=1);

use App\Domains\Developer\Models\OAuthApplication;
use App\Domains\Developer\Models\OAuthRefreshToken;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * OAuth2 authorization-code grant (+ PKCE + refresh), built on the existing
 * oauth_applications registry and issuing Sanctum access tokens.
 */

/** @return array{0: OAuthApplication, 1: string} */
function oauthApp(User $owner, array $overrides = []): array
{
    $secret = 'secret-' . Str::random(24);

    $app = OAuthApplication::create(array_merge([
        'customer_id'        => $owner->customer->id,
        'name'               => 'Test App',
        'client_id'          => Str::uuid()->toString(),
        'client_secret_hash' => bcrypt($secret),
        'redirect_uris'      => ['https://client.example/callback'],
        'is_active'          => true,
    ], $overrides));

    return [$app, $secret];
}

function codeFromRedirect(string $location): ?string
{
    parse_str((string) parse_url($location, PHP_URL_QUERY), $q);

    return $q['code'] ?? null;
}

// ── consent screen ────────────────────────────────────────────────────────────

it('requires login to reach the consent screen', function (): void {
    [$app] = oauthApp(customerUser());

    $this->get(route('oauth.authorize', [
        'response_type' => 'code',
        'client_id'     => $app->client_id,
        'redirect_uri'  => 'https://client.example/callback',
    ]))->assertRedirect(); // → login
});

it('shows the consent screen for a valid request', function (): void {
    $user  = customerUser();
    [$app] = oauthApp($user);

    $this->actingAs($user)->get(route('oauth.authorize', [
        'response_type' => 'code',
        'client_id'     => $app->client_id,
        'redirect_uri'  => 'https://client.example/callback',
        'scope'         => 'read',
        'state'         => 'xyz',
    ]))->assertOk()->assertSee('Test App');
});

it('rejects an unregistered redirect_uri without redirecting to it', function (): void {
    $user  = customerUser();
    [$app] = oauthApp($user);

    $this->actingAs($user)->get(route('oauth.authorize', [
        'response_type' => 'code',
        'client_id'     => $app->client_id,
        'redirect_uri'  => 'https://evil.example/steal',
    ]))->assertStatus(400);
});

it('redirects with access_denied when the user denies', function (): void {
    $user  = customerUser();
    [$app] = oauthApp($user);

    $res = $this->actingAs($user)->post(route('oauth.authorize.deny'), [
        'client_id'    => $app->client_id,
        'redirect_uri' => 'https://client.example/callback',
        'state'        => 'xyz',
    ]);

    $res->assertRedirect();
    expect($res->headers->get('Location'))->toContain('error=access_denied')->toContain('state=xyz');
});

// ── confidential client (client_secret) ────────────────────────────────────────

it('completes the authorization_code flow with a client secret', function (): void {
    $user           = customerUser();
    [$app, $secret] = oauthApp($user);

    $approve = $this->actingAs($user)->post(route('oauth.authorize.approve'), [
        'client_id'    => $app->client_id,
        'redirect_uri' => 'https://client.example/callback',
        'scope'        => 'read',
        'state'        => 'xyz',
    ]);
    $approve->assertRedirect();

    $code = codeFromRedirect((string) $approve->headers->get('Location'));
    expect($code)->not->toBeNull();

    $token = $this->postJson(route('oauth.token'), [
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => 'https://client.example/callback',
        'client_id'     => $app->client_id,
        'client_secret' => $secret,
    ])->assertOk()
      ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'refresh_token', 'scope']);

    // The issued access token is a working Sanctum bearer token. Clear the
    // consent session first so we genuinely exercise the bearer path.
    $this->app['auth']->forgetGuards();

    $this->withToken($token->json('access_token'))
        ->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

it('rejects a bad client secret at the token endpoint', function (): void {
    $user  = customerUser();
    [$app] = oauthApp($user);

    $approve = $this->actingAs($user)->post(route('oauth.authorize.approve'), [
        'client_id'    => $app->client_id,
        'redirect_uri' => 'https://client.example/callback',
        'scope'        => 'read',
    ]);
    $code = codeFromRedirect((string) $approve->headers->get('Location'));

    $this->postJson(route('oauth.token'), [
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => 'https://client.example/callback',
        'client_id'     => $app->client_id,
        'client_secret' => 'wrong',
    ])->assertStatus(401)->assertJsonPath('error', 'invalid_client');
});

it('burns the authorization code after one use', function (): void {
    $user           = customerUser();
    [$app, $secret] = oauthApp($user);

    $approve = $this->actingAs($user)->post(route('oauth.authorize.approve'), [
        'client_id'    => $app->client_id,
        'redirect_uri' => 'https://client.example/callback',
        'scope'        => 'read',
    ]);
    $code = codeFromRedirect((string) $approve->headers->get('Location'));

    $payload = [
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => 'https://client.example/callback',
        'client_id'     => $app->client_id,
        'client_secret' => $secret,
    ];

    $this->postJson(route('oauth.token'), $payload)->assertOk();
    $this->postJson(route('oauth.token'), $payload)->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
});

// ── public client (PKCE) ────────────────────────────────────────────────────────

it('completes the authorization_code flow with PKCE and no secret', function (): void {
    $user  = customerUser();
    [$app] = oauthApp($user);

    $verifier  = Str::random(64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $approve = $this->actingAs($user)->post(route('oauth.authorize.approve'), [
        'client_id'             => $app->client_id,
        'redirect_uri'          => 'https://client.example/callback',
        'scope'                 => 'read write:tickets',
        'code_challenge'        => $challenge,
        'code_challenge_method' => 'S256',
    ]);
    $code = codeFromRedirect((string) $approve->headers->get('Location'));

    $this->postJson(route('oauth.token'), [
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => 'https://client.example/callback',
        'client_id'     => $app->client_id,
        'code_verifier' => $verifier,
    ])->assertOk()->assertJsonPath('scope', 'read write:tickets');
});

it('rejects a wrong PKCE verifier', function (): void {
    $user  = customerUser();
    [$app] = oauthApp($user);

    $challenge = rtrim(strtr(base64_encode(hash('sha256', Str::random(64), true)), '+/', '-_'), '=');

    $approve = $this->actingAs($user)->post(route('oauth.authorize.approve'), [
        'client_id'             => $app->client_id,
        'redirect_uri'          => 'https://client.example/callback',
        'code_challenge'        => $challenge,
        'code_challenge_method' => 'S256',
    ]);
    $code = codeFromRedirect((string) $approve->headers->get('Location'));

    $this->postJson(route('oauth.token'), [
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => 'https://client.example/callback',
        'client_id'     => $app->client_id,
        'code_verifier' => 'the-wrong-verifier-entirely',
    ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
});

// ── refresh ─────────────────────────────────────────────────────────────────────

it('refreshes tokens and rotates, revoking the old access token', function (): void {
    $user           = customerUser();
    [$app, $secret] = oauthApp($user);

    $approve = $this->actingAs($user)->post(route('oauth.authorize.approve'), [
        'client_id'    => $app->client_id,
        'redirect_uri' => 'https://client.example/callback',
        'scope'        => 'read',
    ]);
    $code = codeFromRedirect((string) $approve->headers->get('Location'));

    $first = $this->postJson(route('oauth.token'), [
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => 'https://client.example/callback',
        'client_id'     => $app->client_id,
        'client_secret' => $secret,
    ])->assertOk();

    $oldAccess = PersonalAccessToken::findToken($first->json('access_token'));
    expect($oldAccess)->not->toBeNull();

    $second = $this->postJson(route('oauth.token'), [
        'grant_type'    => 'refresh_token',
        'refresh_token' => $first->json('refresh_token'),
        'client_id'     => $app->client_id,
        'client_secret' => $secret,
    ])->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);

    // Old access token is gone; old refresh token is revoked.
    expect(PersonalAccessToken::query()->whereKey($oldAccess->getKey())->exists())->toBeFalse();
    expect($second->json('access_token'))->not->toBe($first->json('access_token'));

    // The revoked refresh token can no longer be used.
    $this->postJson(route('oauth.token'), [
        'grant_type'    => 'refresh_token',
        'refresh_token' => $first->json('refresh_token'),
        'client_id'     => $app->client_id,
        'client_secret' => $secret,
    ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
});

it('rejects an unsupported grant type', function (): void {
    [$app] = oauthApp(customerUser());

    $this->postJson(route('oauth.token'), [
        'grant_type' => 'password',
        'client_id'  => $app->client_id,
    ])->assertStatus(400)->assertJsonPath('error', 'unsupported_grant_type');
});
