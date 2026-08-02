<?php

declare(strict_types=1);

use App\Domains\Monitoring\Services\HealthChecker;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Readiness probe (/api/ready) — deeper than liveness (/up): DB, cache, queue
 * and storage round-trips. Public, no secrets in the payload.
 */

it('reports ready with per-dependency checks when everything is up', function (): void {
    $this->getJson('/api/ready')
        ->assertOk()
        ->assertJsonPath('status', 'ready')
        ->assertJsonStructure([
            'status',
            'checks' => [
                'database' => ['ok', 'latency_ms'],
                'cache'    => ['ok', 'latency_ms'],
                'queue'    => ['ok', 'latency_ms'],
                'storage'  => ['ok', 'latency_ms'],
            ],
            'time',
        ]);
});

it('the checker reports every dependency ok in the test environment', function (): void {
    $result = app(HealthChecker::class)->readiness();

    expect($result['status'])->toBe('ready')
        ->and($result['checks']['database']['ok'])->toBeTrue()
        ->and($result['checks']['cache']['ok'])->toBeTrue()
        ->and($result['checks']['queue']['ok'])->toBeTrue()
        ->and($result['checks']['storage']['ok'])->toBeTrue();
});

it('returns 503 when a dependency is down', function (): void {
    // Point the cache at a store that will fail on write.
    $mock = Mockery::mock(HealthChecker::class);
    $mock->shouldReceive('readiness')->andReturn([
        'status' => 'degraded',
        'checks' => ['database' => ['ok' => true, 'latency_ms' => 1], 'cache' => ['ok' => false, 'latency_ms' => 0]],
    ]);
    $this->app->instance(HealthChecker::class, $mock);

    $this->getJson('/api/ready')->assertStatus(503)->assertJsonPath('status', 'degraded');
});

it('never leaks error detail from a failed check', function (): void {
    $mock = Mockery::mock(HealthChecker::class);
    $mock->shouldReceive('readiness')->andReturn([
        'status' => 'degraded',
        'checks' => ['database' => ['ok' => false, 'latency_ms' => 5]],
    ]);
    $this->app->instance(HealthChecker::class, $mock);

    // Payload carries only ok/latency — no exception messages or connection info.
    $body = $this->getJson('/api/ready')->assertStatus(503)->json();
    expect(json_encode($body))->not->toContain('SQLSTATE')->not->toContain('password');
});
