<?php

declare(strict_types=1);

use App\Http\Support\ApiContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Onhost\Domain\Organizations\Models\Organization;
use Symfony\Component\Yaml\Yaml;

/*
 * TASK-0076 (D7): limiter keys, failed-auth throttle, stable pagination, API version policy.
 */

beforeEach(function () {
    RateLimiter::clear('probe:127.0.0.1');
});

it('keeps one probes bucket per address however the bearer is rotated', function () {
    config()->set('onhost.api.probes_rate_limit_per_minute', 3);
    foreach (['aaaaaaaaaaaaaaaa1', 'bbbbbbbbbbbbbbbb2', 'cccccccccccccccc3'] as $bearer) {
        expect($this->withToken($bearer)->postJson('/v1/probes/results', [])->status())->not->toBe(429);
    }
    $this->withToken('dddddddddddddddd4')->postJson('/v1/probes/results', [])->assertStatus(429);
});

it('throttles the Discord interactions endpoint per address', function () {
    config()->set('onhost.api.callbacks_rate_limit_per_minute', 2);
    foreach ([1, 2] as $_) {
        expect($this->postJson('/v1/integrations/discord/interactions', [])->status())->not->toBe(429);
    }
    $this->postJson('/v1/integrations/discord/interactions', [])->assertStatus(429);
});

it('counts failed authentications against the address, whatever token was sent', function () {
    config()->set('onhost.api.failed_auth_per_minute', 3);
    foreach (['t1', 't2', 't3'] as $bearer) {
        $this->withToken($bearer)->getJson('/v1/me')->assertUnauthorized();
    }
    $this->withToken('t4')->getJson('/v1/me')->assertStatus(429)->assertHeader('Retry-After');
});

it('does not count successful requests as failed authentications', function () {
    config()->set('onhost.api.failed_auth_per_minute', 2);
    $this->actingAs($this->customer())->getJson('/v1/me')->assertOk();
    $this->actingAs($this->customer())->getJson('/v1/me')->assertOk();
    $this->actingAs($this->customer())->getJson('/v1/me')->assertOk();
});

it('pages rows with equal created_at in a stable order (id breaks the tie)', function () {
    $at = Carbon::parse('2026-01-01 10:00:00');
    foreach (range(1, 7) as $i) {
        $org = Organization::query()->create(['slug' => 'pg-'.$i, 'name' => 'Pg '.$i, 'owner_user_id' => 'usr_pg', 'country' => 'CZ', 'customer_class' => 'b2b']);
        $org->forceFill(['created_at' => $at])->saveQuietly();
    }
    $api = app(ApiContext::class);
    $seen = [];
    foreach ([0, 3, 6] as $offset) {
        $request = Request::create('/x', 'GET', ['limit' => 3, 'offset' => $offset]);
        $page = $api->paginate($request, Organization::query()->where('slug', 'like', 'pg-%'), fn ($o) => $o->id)->getData(true);
        $seen = array_merge($seen, $page['data']);
    }
    $expected = Organization::query()->where('slug', 'like', 'pg-%')->pluck('id')->sort()->reverse()->values()->all();
    expect($seen)->toBe($expected);
});

it('sends Deprecation, Sunset and Link headers for a configured route pattern only', function () {
    config()->set('onhost.api.deprecations', [
        ['path' => 'v1/stock', 'deprecated_at' => '2026-10-01T00:00:00Z', 'sunset_at' => '2027-04-01T00:00:00Z', 'link' => 'https://docs.onhost.cz/api/migration'],
    ]);
    $response = $this->getJson('/v1/stock');
    $response->assertHeader('Deprecation', '@'.Carbon::parse('2026-10-01T00:00:00Z')->timestamp);
    $response->assertHeader('Sunset', 'Thu, 01 Apr 2027 00:00:00 GMT');
    expect($response->headers->get('Link'))->toContain('<https://docs.onhost.cz/api/migration>; rel="deprecation"');
    $other = $this->getJson('/v1/reseller/tiers');
    expect($other->headers->has('Deprecation'))->toBeFalse()->and($other->headers->has('Sunset'))->toBeFalse();
    expect($other->headers->get('X-API-Version'))->toBe((string) config('onhost.api.version'));
});

it('reports the configured API version in the OpenAPI document', function () {
    config()->set('onhost.api.version', '7.3.1');
    $relative = 'storage/framework/testing/openapi-'.uniqid().'.yaml';
    Artisan::call('onhost:openapi', ['--out' => $relative]);
    $path = base_path($relative);
    try {
        expect(Yaml::parseFile($path)['info']['version'])->toBe('7.3.1');
    } finally {
        @unlink($path);
    }
});

it('gives the console relay fetches a configurable timeout', function () {
    $source = (string) file_get_contents(base_path('infra/console-relay/server.mjs'));
    expect($source)->toContain('AbortController')->and($source)->toContain('RELAY_FETCH_TIMEOUT_MS')->and($source)->not->toContain('await fetch(`')->and($source)->toContain('await r.json()');
});

it('does not lock out a logged-out client that polls without any credentials', function () {
    config()->set('onhost.api.failed_auth_per_minute', 2);
    foreach (range(1, 6) as $_) {
        $this->getJson('/v1/me')->assertUnauthorized();
    }
    // and a few bad bearers still lock only the bearer guesser
    foreach (['g1', 'g2'] as $bearer) {
        $this->withToken($bearer)->getJson('/v1/me')->assertUnauthorized();
    }
    $this->withToken('g3')->getJson('/v1/me')->assertStatus(429);
});

it('defaults the failed-authentication ceiling to 60 a minute', function () {
    expect(config('onhost.api.failed_auth_per_minute'))->toBe(60);
});

it('skips a deprecation rule with an invalid date instead of failing the response', function () {
    config()->set('onhost.api.deprecations', [
        ['path' => 'v1/stock', 'deprecated_at' => 'not a date', 'sunset_at' => 'also bad'],
    ]);
    Log::spy();
    $response = $this->getJson('/v1/stock');
    expect($response->status())->toBe(200)->and($response->headers->has('Deprecation'))->toBeFalse();
    Log::shouldHaveReceived('warning')->once();
});
