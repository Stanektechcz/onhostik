<?php

declare(strict_types=1);

use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\Models\BackupPolicy;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Web\BackupScheduler;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * A slot that could not run is a backup the customer paid for and did not get (Brain cards H434, H435, H446). The
 * scheduler counted such a slot as "skipped" and said nothing — an hourly plan on a site whose backup takes longer
 * than an hour misses every single slot in silence, and the customer finds out on the day they need it. Since the
 * hourly backup add-on really works now, that is a plan somebody can buy.
 */

it('writes down a slot that could not run, and tells somebody when it keeps happening', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    BackupPolicy::query()->create(['service_id' => $service->id, 'product_key' => 'backup-hourly', 'schedule' => ['frequency' => 'hourly'], 'retention' => ['days' => 30, 'generations' => 720], 'offsite' => false, 'restore_test' => ['cadence' => 'monthly'], 'state' => 'active']);
    // a backup of this site is still running when the next slot comes: every further slot is refused by the service lock
    Operation::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'kind' => 'service.action', 'workflow' => ServiceActionWorkflow::class,
        'state' => Operation::RUNNING, 'step' => 0, 'steps_total' => 1, 'actor_type' => 'system', 'idempotency_key' => 'long-backup', 'correlation_id' => 'c1',
        'desired' => ['action' => 'backup', 'service_id' => $service->id], 'context' => [], 'queue' => 'q', 'queued_at' => now()->subHours(2), 'next_run_at' => now(), 'retry_until' => now()->addHours(4)]);

    $scheduler = app(BackupScheduler::class);
    $slots = [];
    foreach ([0, 1, 2] as $hour) { // three slots in a row, each its own hour
        $this->travelTo(now()->startOfHour()->addHours($hour + 1)->addMinutes(2));
        $stats = $scheduler->tick();
        $slots[] = $stats['missed'];
    }

    expect($slots)->toBe([1, 1, 1]); // three different slots, each counted once
    $health = BackupScheduler::health($service->fresh());
    expect($health['missed'])->toBe(3)->and($health['frequency'])->toBe('hourly')->and($health['last_error'])->toContain('operation_in_progress');

    app(OutboxPublisher::class)->relayPending();
    $told = Notification::query()->where('event', 'service.backup.schedule.stalled')->get();
    expect($told)->not->toBeEmpty()
        ->and($told->pluck('audience')->unique()->all())->toContain('customer')
        ->and($told->first()->body)->toContain('3×');
});

it('counts one miss per slot, and a slot that runs clears the count', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    BackupPolicy::query()->create(['service_id' => $service->id, 'product_key' => 'backup-hourly', 'schedule' => ['frequency' => 'hourly'], 'retention' => ['days' => 30, 'generations' => 720], 'offsite' => false, 'restore_test' => [], 'state' => 'active']);
    $blocker = Operation::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'kind' => 'service.action', 'workflow' => ServiceActionWorkflow::class,
        'state' => Operation::RUNNING, 'step' => 0, 'steps_total' => 1, 'actor_type' => 'system', 'idempotency_key' => 'long-backup-2', 'correlation_id' => 'c2',
        'desired' => ['action' => 'backup', 'service_id' => $service->id], 'context' => [], 'queue' => 'q', 'queued_at' => now()->subHours(2), 'next_run_at' => now(), 'retry_until' => now()->addHours(4)]);
    $scheduler = app(BackupScheduler::class);

    $this->travelTo(now()->startOfHour()->addHour()->addMinutes(2));
    expect($scheduler->tick()['missed'])->toBe(1);
    expect($scheduler->tick()['missed'])->toBe(0); // the same slot looked at again is not a second miss
    expect(BackupScheduler::health($service->fresh())['missed'])->toBe(1);

    // the long backup finished: the next slot runs and the count starts again from nothing
    $blocker->forceFill(['state' => Operation::SUCCEEDED])->save();
    $this->travelTo(now()->addHour());
    expect($scheduler->tick()['started'])->toBe(1);
    $health = BackupScheduler::health($service->fresh());
    expect($health['missed'])->toBe(0)->and($health['last_run_at'])->not->toBeNull();
});

it('never carries one service\'s missed slot over to another', function () {
    // the loop reads its schedule inside its own try: a throw used to leave the previous service's schedule in the variable
    [, $org] = $this->customerWithOrganization();
    $blocked = featureWebService($org, 'ispconfig');
    $healthy = featureWebService($org, 'aapanel');
    BackupPolicy::query()->create(['service_id' => $blocked->id, 'product_key' => 'backup-hourly', 'schedule' => ['frequency' => 'hourly'], 'retention' => ['days' => 30, 'generations' => 720], 'offsite' => false, 'restore_test' => [], 'state' => 'active']);
    Operation::query()->create(['service_id' => $blocked->id, 'organization_id' => $org->id, 'kind' => 'service.action', 'workflow' => ServiceActionWorkflow::class,
        'state' => Operation::RUNNING, 'step' => 0, 'steps_total' => 1, 'actor_type' => 'system', 'idempotency_key' => 'long-backup-3', 'correlation_id' => 'c3',
        'desired' => ['action' => 'backup', 'service_id' => $blocked->id], 'context' => [], 'queue' => 'q', 'queued_at' => now()->subHours(2), 'next_run_at' => now(), 'retry_until' => now()->addHours(4)]);

    $this->travelTo(now()->startOfHour()->addHour()->addMinutes(2));
    expect(app(BackupScheduler::class)->tick()['missed'])->toBe(1);
    expect(BackupScheduler::health($blocked->fresh())['missed'])->toBe(1)
        ->and(BackupScheduler::health($healthy->fresh())['missed'] ?? 0)->toBe(0); // the other site keeps its own record
});
