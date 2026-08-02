<?php

declare(strict_types=1);

use App\Domains\Monitoring\Services\SchedulerHeartbeat;
use Illuminate\Support\Facades\Cache;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Scheduler heartbeat — detects a dead cron (scheduled jobs silently stopped).
 */

it('is stale when the scheduler has never run', function (): void {
    expect(app(SchedulerHeartbeat::class)->lastRunAt())->toBeNull()
        ->and(app(SchedulerHeartbeat::class)->isStale())->toBeTrue();
});

it('records a heartbeat and is then fresh', function (): void {
    $hb = app(SchedulerHeartbeat::class);
    $hb->record();

    expect($hb->lastRunAt())->not->toBeNull()
        ->and($hb->isStale())->toBeFalse();
});

it('is stale once the last run is older than the threshold', function (): void {
    Cache::forever('scheduler:last_run', now()->subMinutes(10)->timestamp);

    expect(app(SchedulerHeartbeat::class)->isStale(5))->toBeTrue();
});

it('the scheduler:heartbeat command records a run', function (): void {
    $this->artisan('scheduler:heartbeat')->assertExitCode(0);

    expect(app(SchedulerHeartbeat::class)->isStale())->toBeFalse();
});

it('surfaces scheduler freshness on the admin system-health page', function (): void {
    app(SchedulerHeartbeat::class)->record();

    $this->actingAs(adminUser())
        ->get(route('admin.system.index'))
        ->assertOk()
        ->assertSee('scheduler');
});
