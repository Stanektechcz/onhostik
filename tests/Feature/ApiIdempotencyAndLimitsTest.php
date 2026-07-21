<?php

declare(strict_types=1);

use App\Domains\Support\Models\SupportTicket;
use App\Models\IdempotencyKey;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Phase J: Idempotency-Key on API writes (J136), per-token rate limiting
 * (J134) and the health endpoint (J142).
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

/** @return array{0: \App\Models\User, 1: string} */
function idempotencyApiUser(): array
{
    $user  = customerUser();
    $token = $user->createToken('test', ['*'])->plainTextToken;

    return [$user, $token];
}

// ── J142: health endpoint ─────────────────────────────────────────────────────

it('reports healthy when the database is reachable', function (): void {
    $this->getJson('/api/up')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database', true);
});

it('needs no authentication for the health probe', function (): void {
    // A health check that needs credentials is one more thing to break at 3am.
    $this->getJson('/api/up')->assertOk();
});

// ── J136: Idempotency-Key ─────────────────────────────────────────────────────

it('passes through when no idempotency key is supplied', function (): void {
    [, $token] = idempotencyApiUser();

    $this->withToken($token)
        ->postJson('/api/v1/support/tickets', ['subject' => 'Bez klíče', 'message' => 'Text zprávy pro test.'])
        ->assertSuccessful();

    expect(IdempotencyKey::count())->toBe(0);
});

it('replays the stored response for a repeated key instead of acting twice', function (): void {
    [, $token] = idempotencyApiUser();

    $payload = ['subject' => 'Duplicitní požadavek', 'message' => 'Klient zopakoval požadavek po timeoutu.'];

    $first = $this->withToken($token)
        ->withHeader('Idempotency-Key', 'key-abc-123')
        ->postJson('/api/v1/support/tickets', $payload)
        ->assertSuccessful();

    $second = $this->withToken($token)
        ->withHeader('Idempotency-Key', 'key-abc-123')
        ->postJson('/api/v1/support/tickets', $payload)
        ->assertSuccessful();

    // One ticket, and the second call returns the first one's response.
    expect(SupportTicket::where('subject', 'Duplicitní požadavek')->count())->toBe(1)
        ->and($second->json())->toEqual($first->json())
        ->and($second->headers->get('Idempotent-Replay'))->toBe('true');
});

it('rejects the same key used with a different body', function (): void {
    [, $token] = idempotencyApiUser();

    $this->withToken($token)
        ->withHeader('Idempotency-Key', 'key-conflict')
        ->postJson('/api/v1/support/tickets', ['subject' => 'První', 'message' => 'Původní zpráva pro test.'])
        ->assertSuccessful();

    // Silently replaying here would hide a genuine client bug.
    $this->withToken($token)
        ->withHeader('Idempotency-Key', 'key-conflict')
        ->postJson('/api/v1/support/tickets', ['subject' => 'Druhý', 'message' => 'Úplně jiná zpráva pro test.'])
        ->assertStatus(422);

    expect(SupportTicket::where('subject', 'Druhý')->exists())->toBeFalse();
});

it('scopes idempotency keys per token', function (): void {
    /*
     | Asserted at the storage layer rather than with two HTTP calls: within a
     | single test the auth guard caches the first resolved token, so a second
     | withToken() call would not actually switch identity. That is a harness
     | artefact — each real request builds a fresh application — but it makes
     | the two-call version test the harness instead of the code.
     |
     | What must hold is that the SAME key under a DIFFERENT token is a
     | separate record and cannot replay the other client's response.
     */
    IdempotencyKey::create([
        'token_id' => 1, 'idempotency_key' => 'shared', 'method' => 'POST',
        'path' => 'api/v1/support/tickets', 'request_hash' => str_repeat('a', 64),
    ]);

    IdempotencyKey::create([
        'token_id' => 2, 'idempotency_key' => 'shared', 'method' => 'POST',
        'path' => 'api/v1/support/tickets', 'request_hash' => str_repeat('a', 64),
    ]);

    expect(IdempotencyKey::where('idempotency_key', 'shared')->count())->toBe(2);

    // …while the same token reusing the key is refused by the unique index.
    expect(fn () => IdempotencyKey::create([
        'token_id' => 1, 'idempotency_key' => 'shared', 'method' => 'POST',
        'path' => 'api/v1/support/tickets', 'request_hash' => str_repeat('a', 64),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('does not store a key for a failed request so it can be retried', function (): void {
    [, $token] = idempotencyApiUser();

    $this->withToken($token)
        ->withHeader('Idempotency-Key', 'key-after-failure')
        ->postJson('/api/v1/support/tickets', ['subject' => '']) // invalid
        ->assertStatus(422);

    // The same key must work once the client fixes the payload.
    $this->withToken($token)
        ->withHeader('Idempotency-Key', 'key-after-failure')
        ->postJson('/api/v1/support/tickets', ['subject' => 'Opraveno', 'message' => 'Po opravě požadavku.'])
        ->assertSuccessful();

    expect(SupportTicket::where('subject', 'Opraveno')->exists())->toBeTrue();
});

it('rejects an over-long idempotency key', function (): void {
    [, $token] = idempotencyApiUser();

    $this->withToken($token)
        ->withHeader('Idempotency-Key', str_repeat('x', 200))
        ->postJson('/api/v1/support/tickets', ['subject' => 'Dlouhý klíč', 'message' => 'Text zprávy pro test.'])
        ->assertStatus(400);
});

it('leaves GET requests alone', function (): void {
    [, $token] = idempotencyApiUser();

    $this->withToken($token)
        ->withHeader('Idempotency-Key', 'get-key')
        ->getJson('/api/v1/services')
        ->assertSuccessful();

    expect(IdempotencyKey::count())->toBe(0);
});

// ── J134: per-token rate limiting ─────────────────────────────────────────────

it('honours a per-token rate limit override', function (): void {
    [$user, $plain] = idempotencyApiUser();
    $tokenId = $user->tokens()->first()->id;

    // Squeeze this token down so it is trivially exhaustible.
    DB::table('api_token_rate_limits')->insert([
        'token_id'            => $tokenId,
        'requests_per_minute' => 1,
        'requests_per_hour'   => 100,
        'requests_per_day'    => 1000,
        'is_active'           => true,
        'created_at'          => now(),
        'updated_at'          => now(),
    ]);

    $this->withToken($plain)->getJson('/api/v1/services')->assertSuccessful();
    $this->withToken($plain)->getJson('/api/v1/services')->assertStatus(429);
});

it('keys the limiter on the token, not the user', function (): void {
    // Two integrations belonging to one customer must not share a budget —
    // otherwise a single noisy script starves the rest.
    $user   = customerUser();
    $tokenA = $user->createToken('integration-a', ['*'])->accessToken;
    $tokenB = $user->createToken('integration-b', ['*'])->accessToken;

    $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('api');

    $keyFor = function ($token) use ($limiter, $user) {
        $request = \Illuminate\Http\Request::create('/api/v1/services');
        $request->setUserResolver(fn () => tap($user, fn ($u) => $u->withAccessToken($token)));

        return $limiter($request)->key;
    };

    expect($keyFor($tokenA))->not->toBe($keyFor($tokenB))
        ->and($keyFor($tokenA))->toContain((string) $tokenA->id);
});

it('does not fall over when the caller is session-authenticated', function (): void {
    /*
     | Regression: the limiter read $token->id unconditionally. A stateful
     | (cookie) request resolves to a Sanctum TransientToken, which has no id,
     | so reading it raised an ErrorException and 500'd the request — before
     | the route was ever reached.
     */
    $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('api');

    $user    = customerUser();
    $request = \Illuminate\Http\Request::create('/api/v1/services');
    $request->setUserResolver(
        fn () => tap($user, fn ($u) => $u->withAccessToken(new \Laravel\Sanctum\TransientToken())),
    );

    $limit = $limiter($request);

    expect($limit->key)->toBe('user:' . $user->id);
});

it('tells the client how much budget is left', function (): void {
    [, $token] = idempotencyApiUser();

    $response = $this->withToken($token)->getJson('/api/v1/services');

    expect($response->headers->get('X-RateLimit-Limit'))->not->toBeNull()
        ->and($response->headers->get('X-RateLimit-Remaining'))->not->toBeNull();
});
