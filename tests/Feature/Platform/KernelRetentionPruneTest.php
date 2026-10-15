<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * A1: the kernel logs have a retention. provider_calls older than onhost.provider_calls.retention_days (90) go in batches,
 * relayed outbox rows go through model:prune, expired idempotency keys go; an unrelayed outbox row survives at any age.
 */

function a1ProviderCall(string $id, Carbon $at): void
{
    DB::table('provider_calls')->insert([
        'id' => $id, 'provider' => 'probe', 'instance_key' => 'probe-1', 'action' => 'probe.read', 'method' => 'GET',
        'path' => '/probe', 'http_status' => 200, 'ok' => true, 'duration_ms' => 1, 'created_at' => $at,
    ]);
}

it('prunes provider_calls past 90 days in batches and keeps the recent ones', function () {
    $this->travelTo(Carbon::parse('2026-10-15 03:00:00', 'UTC'));
    config(['onhost.retention.prune_batch' => 2]);
    foreach (range(1, 5) as $i) {
        a1ProviderCall('pc_a1_old_'.$i, now()->subDays(91));
    }
    a1ProviderCall('pc_a1_edge', now()->subDays(89));
    a1ProviderCall('pc_a1_new', now()->subDay());

    $this->artisan('onhost:provider-calls:prune')->assertSuccessful();

    expect(DB::table('provider_calls')->where('id', 'like', 'pc_a1_%')->orderBy('id')->pluck('id')->all())
        ->toBe(['pc_a1_edge', 'pc_a1_new']);
});

it('reads the provider_calls retention from config', function () {
    $this->travelTo(Carbon::parse('2026-10-15 03:00:00', 'UTC'));
    config(['onhost.provider_calls.retention_days' => 10]);
    a1ProviderCall('pc_a1_cfg_old', now()->subDays(11));
    a1ProviderCall('pc_a1_cfg_new', now()->subDays(9));

    $this->artisan('onhost:provider-calls:prune')->assertSuccessful();

    expect(DB::table('provider_calls')->where('id', 'like', 'pc_a1_cfg_%')->pluck('id')->all())->toBe(['pc_a1_cfg_new']);
});

it('prunes only relayed outbox messages past retention; an unrelayed one survives', function () {
    $this->travelTo(Carbon::parse('2026-10-15 03:10:00', 'UTC'));
    $outbox = app(OutboxPublisher::class);
    $relayedOld = $outbox->publish(GenericEvent::of('a1.prune.probe', 'probe', 'old', []))->id;
    $relayedNew = $outbox->publish(GenericEvent::of('a1.prune.probe', 'probe', 'new', []))->id;
    $unrelayed = $outbox->publish(GenericEvent::of('a1.prune.probe', 'probe', 'pending', []))->id;
    OutboxMessage::query()->whereKey($relayedOld)->update(['published_at' => now()->subDays(31), 'created_at' => now()->subDays(31)]);
    OutboxMessage::query()->whereKey($relayedNew)->update(['published_at' => now()->subDays(2)]);
    OutboxMessage::query()->whereKey($unrelayed)->update(['published_at' => null, 'available_at' => now()->subDays(400), 'created_at' => now()->subDays(400)]);

    $this->artisan('model:prune', ['--model' => [OutboxMessage::class]])->assertSuccessful();

    expect(OutboxMessage::query()->whereKey($relayedOld)->exists())->toBeFalse()
        ->and(OutboxMessage::query()->whereKey($relayedNew)->exists())->toBeTrue()
        ->and(OutboxMessage::query()->whereKey($unrelayed)->exists())->toBeTrue();
});

it('prunes expired idempotency keys and keeps live ones', function () {
    $this->travelTo(Carbon::parse('2026-10-15 03:05:00', 'UTC'));
    config(['onhost.retention.prune_batch' => 1]);
    foreach (['a1-exp-1' => now()->subHour(), 'a1-exp-2' => now()->subDays(3), 'a1-live' => now()->addHour()] as $key => $expires) {
        DB::table('idempotency_keys')->insert(['key' => $key, 'scope' => 'a1-probe', 'created_at' => now()->subDays(4), 'expires_at' => $expires]);
    }

    $this->artisan('onhost:idempotency:prune')->assertSuccessful();

    expect(DB::table('idempotency_keys')->where('scope', 'a1-probe')->pluck('key')->all())->toBe(['a1-live']);
});

it('schedules the three retention jobs', function () {
    $commands = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())->map(fn ($e) => (string) $e->command)->implode("\n");
    expect($commands)->toContain('onhost:provider-calls:prune')
        ->and($commands)->toContain('onhost:idempotency:prune')
        ->and($commands)->toContain('model:prune');
});
