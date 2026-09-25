<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\BackupPolicy;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\Web\BackupScheduler;

/*
 * TASK-0024 (backup-ops), owner decision 18: backups as often and as long as the price list sells them.
 *
 * Two plans sell `backup_frequency: '1h'` (managed-woo, shop-growth), a key the scheduler does not know — so they were
 * backed up once a day, at midnight. And every sub-daily plan kept 7 generations whatever its `backup_days`: "Zálohy 30
 * dní" on managed-wp was 42 hours of history, on shop-peak (15 min, 90 days) 105 minutes. Both corrections change
 * existing services, so they sit behind the rule `backups.as_sold` (off until staff switch it on), and
 * `onhost:backups:frequency-plan` shows first, read-only, whom they would change.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake();
    $this->travelTo(now()->startOfDay()->setTime(3, 5));
});

function backupAsSoldSwitch(bool $on): void
{
    app(AutomationLedger::class)->setEnabled(BackupScheduler::AS_SOLD_RULE, $on, 'test');
}

/** A web or managed service carrying `$entitlements` (as the plan sells them) on the lab ISPConfig. */
function backupAsSoldService(Organization $org, array $entitlements, string $family = 'web'): Service
{
    $first = Service::query()->where('organization_id', $org->id)->where('product_key', 'web-hosting')->orderBy('created_at')->first();
    if ($first === null) {
        $service = featureWebService($org, 'ispconfig');
    } else { // a second site on the same panel needs a site id of its own (a binding is unique per instance and remote id)
        $binding = $first->primaryBinding();
        $service = $first->replicate(['id', 'name_prefix'])->fill(['name' => 'Webhosting '.Str::random(4), 'hostname' => Str::lower(Str::random(6)).'.cz']);
        $service->save();
        ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $binding->provider_instance_id, 'remote_type' => $binding->remote_type, 'remote_id' => (string) random_int(2000, 9999),
            'remote_node' => $binding->remote_node, 'meta' => $binding->meta, 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "as-sold:{$service->id}", 'adapter_version' => '1.0.0']);
    }
    $base = array_diff_key((array) $service->entitlements, array_flip(['backup_frequency', 'backup_days', 'backup_generations'])); // never another service's backup terms
    $service->forceFill(['family' => $family, 'entitlements' => array_merge($base, $entitlements)])->save();
    app(ServiceFeatures::class)->forget($service);

    return $service->fresh();
}

/** @return array<string,mixed> the entitlements of the plan's newest version as CatalogSeeder writes them */
function backupAsSoldPlan(string $planKey): array
{
    $plan = Plan::query()->where('key', $planKey)->firstOrFail();

    return (array) PlanVersion::query()->where('plan_id', $plan->id)->orderByDesc('created_at')->firstOrFail()->entitlements;
}

it('backs every web and managed plan up as often as it is sold, once the rule is on', function (string $planKey, string $family, int $minutes, string $slot) {
    $this->seed(CatalogSeeder::class);
    [, $org] = $this->customerWithOrganization();
    $service = backupAsSoldService($org, backupAsSoldPlan($planKey), $family);
    backupAsSoldSwitch(true);

    $schedule = app(BackupScheduler::class)->scheduleFor($service);

    expect($schedule['minutes'])->toBe($minutes)
        ->and($schedule['slot'])->toBe(now()->format('Ymd').$slot);
})->with([
    'start' => ['start', 'web', 1440, '0230'],
    'standard' => ['standard', 'web', 1440, '0230'],
    'profi' => ['profi', 'web', 1440, '0230'],
    'custom' => ['custom', 'web', 1440, '0230'],
    'managed-wp (6h)' => ['managed-wp', 'managed', 360, '0000'],
    'managed-woo (1h)' => ['managed-woo', 'managed', 60, '0300'],
    'shop-start (6h)' => ['shop-start', 'managed', 360, '0000'],
    'shop-growth (1h)' => ['shop-growth', 'managed', 60, '0300'],
    'shop-peak (15m)' => ['shop-peak', 'managed', 15, '0300'],
]);

it('keeps today\'s schedule for existing services while backups.as_sold is off', function () {
    [, $org] = $this->customerWithOrganization();
    $woo = backupAsSoldService($org, ['backup_frequency' => '1h', 'backup_days' => 30], 'managed');

    expect(app(AutomationLedger::class)->enabled(BackupScheduler::AS_SOLD_RULE))->toBeFalse();
    $schedule = app(BackupScheduler::class)->scheduleFor($woo);

    expect($schedule['frequency'])->toBe('1h')->and($schedule['minutes'])->toBe(1440)
        ->and($schedule['slot'])->toBe(now()->format('Ymd').'0000'); // the unknown key's old path, byte for byte
});

it('lets a managed-woo customer choose hourly once the rule is on', function () {
    [$user, $org] = $this->customerWithOrganization();
    $woo = backupAsSoldService($org, ['backup_frequency' => '1h', 'backup_days' => 30], 'managed');
    $url = "/v1/services/{$woo->id}/backups/schedule";

    $this->actingAs($user, 'sanctum')->withHeader('Idempotency-Key', 'as-sold-1')->putJson($url, ['frequency' => 'hourly'])
        ->assertUnprocessable()->assertJsonPath('error', 'backup_frequency_above_plan'); // today's answer, rule off

    backupAsSoldSwitch(true);
    $this->actingAs($user, 'sanctum')->withHeader('Idempotency-Key', 'as-sold-2')->putJson($url, ['frequency' => 'hourly'])->assertOk();
    $this->actingAs($user, 'sanctum')->withHeader('Idempotency-Key', 'as-sold-3')->putJson($url, ['frequency' => '15m'])
        ->assertUnprocessable()->assertJsonPath('error', 'backup_frequency_above_plan'); // still never above what is sold

    expect(BackupPolicy::query()->where('service_id', $woo->id)->sole()->schedule['frequency'])->toBe('hourly');
});

/** 40 six-hourly backups over the ten days before today, as the platform's own sets (no remote id). */
function backupAsSoldHistory(Service $service): void
{
    foreach (range(1, 10) as $day) {
        foreach ([0, 6, 12, 18] as $hour) {
            $at = now()->startOfDay()->subDays($day)->addHours($hour);
            Backup::query()->create(['service_id' => $service->id, 'organization_id' => $service->organization_id, 'kind' => 'scheduled', 'state' => 'completed', 'protected' => false,
                'started_at' => $at, 'finished_at' => $at->copy()->addMinutes(5), 'retention_until' => $at->copy()->addDays(30), 'size_bytes' => 1000]);
        }
    }
}

it('keeps one backup a day for backup_days beyond the generation cap once the rule is on', function () {
    [, $org] = $this->customerWithOrganization();
    $wp = backupAsSoldService($org, ['backup_frequency' => '6h', 'backup_days' => 30], 'managed');
    backupAsSoldHistory($wp);
    backupAsSoldSwitch(true);

    app(BackupScheduler::class)->tick();

    $kept = Backup::query()->where('service_id', $wp->id)->where('state', 'completed')->orderByDesc('started_at')->get();
    // the seven newest (all of yesterday, three of the day before) and the last one of every older day
    expect($kept)->toHaveCount(15)
        ->and($kept->map(fn (Backup $b) => $b->started_at->format('Y-m-d'))->unique()->count())->toBe(10)
        ->and($kept->skip(7)->every(fn (Backup $b) => $b->started_at->hour === 18))->toBeTrue();
});

it('prunes to the generation cap exactly as before while the rule is off', function () {
    [, $org] = $this->customerWithOrganization();
    $wp = backupAsSoldService($org, ['backup_frequency' => '6h', 'backup_days' => 30], 'managed');
    backupAsSoldHistory($wp);

    app(BackupScheduler::class)->tick();

    expect(Backup::query()->where('service_id', $wp->id)->where('state', 'completed')->count())->toBe(7);
});

/** The next tick, with the backups the last one asked for settled (the queue is faked, so they would stay in flight). */
function backupAsSoldTickAgain(): void
{
    Operation::query()->whereIn('state', [Operation::PENDING, Operation::RUNNING, Operation::WAITING])->update(['state' => Operation::SUCCEEDED]);
    app(BackupScheduler::class)->tick();
}

it('lets the daily keepers kept under the rule live out their retention once it is switched off', function () {
    [, $org] = $this->customerWithOrganization();
    $wp = backupAsSoldService($org, ['backup_frequency' => '6h', 'backup_days' => 30], 'managed');
    backupAsSoldHistory($wp);
    $completed = fn () => Backup::query()->where('service_id', $wp->id)->where('state', 'completed');
    backupAsSoldSwitch(true);
    app(BackupScheduler::class)->tick();
    expect($completed()->count())->toBe(15);

    // switched off: nothing that was kept as sold goes at once, however many rows sit beyond the generation cap
    backupAsSoldSwitch(false);
    backupAsSoldTickAgain();
    expect($completed()->count())->toBe(15);

    // what the rule did not keep is still trimmed to the cap, at most 50 a tick: 60 new backups today push 59 rows beyond it
    foreach (range(0, 59) as $i) {
        $at = now()->startOfDay()->addMinutes(3 * $i);
        Backup::query()->create(['service_id' => $wp->id, 'organization_id' => $org->id, 'kind' => 'scheduled', 'state' => 'completed', 'protected' => false,
            'started_at' => $at, 'finished_at' => $at->copy()->addMinute(), 'retention_until' => $at->copy()->addDays(30), 'size_bytes' => 1000]);
    }
    backupAsSoldTickAgain();
    expect($completed()->count())->toBe(75 - 50);
    backupAsSoldTickAgain();
    // the seven newest and the keepers of the ten past days beyond them
    expect($completed()->count())->toBe(17)
        ->and($completed()->orderByDesc('started_at')->get()->skip(7)->every(fn (Backup $b) => $b->started_at->hour === 18))->toBeTrue();

    // and they leave through their own retention date, never earlier
    $this->travel(25)->days();
    backupAsSoldTickAgain();
    $left = $completed()->orderByDesc('started_at')->get()->skip(7);
    $goneKeepers = Backup::query()->where('service_id', $wp->id)->where('state', 'deleted')->get()->filter(fn (Backup $b) => $b->started_at->hour === 18);
    expect($left)->toHaveCount(5)
        ->and($left->every(fn (Backup $b) => $b->retention_until->isFuture()))->toBeTrue()
        ->and($goneKeepers)->toHaveCount(5)
        ->and($goneKeepers->every(fn (Backup $b) => Carbon::parse((string) $b->meta['deleted_at'])->gte($b->retention_until)))->toBeTrue();
});

it('resolves every backup_frequency on sale to a frequency the scheduler knows', function () {
    $this->seed(CatalogSeeder::class);

    $sold = PlanVersion::query()->get()->map(fn (PlanVersion $v) => data_get($v->entitlements, 'backup_frequency'))->filter(fn ($f) => is_string($f))->unique()->values();

    expect($sold)->not->toBeEmpty();
    foreach ($sold as $frequency) {
        expect(array_key_exists(BackupScheduler::normalizeFrequency($frequency, true), BackupScheduler::FREQUENCIES))->toBeTrue("'{$frequency}' is sold and means nothing to the scheduler");
    }
});

it('lists in the frequency plan what the rule would change, and writes nothing', function () {
    [, $org] = $this->customerWithOrganization();
    $woo = backupAsSoldService($org, ['backup_frequency' => '1h', 'backup_days' => 30], 'managed');
    $start = backupAsSoldService($org, ['backup_days' => 7, 'backup_generations' => 7]);
    Backup::query()->create(['service_id' => $woo->id, 'organization_id' => $org->id, 'kind' => 'scheduled', 'state' => 'completed', 'protected' => false,
        'started_at' => now()->subHour(), 'finished_at' => now()->subHour(), 'retention_until' => now()->addDays(30), 'size_bytes' => 2 * 1024 * 1024 * 1024]);
    $counts = fn () => [Operation::query()->count(), Backup::query()->count(), BackupPolicy::query()->count(), Service::query()->whereNotNull('tags->backup_schedule')->count()];
    $before = $counts();

    expect(Artisan::call('onhost:backups:frequency-plan'))->toBe(0);
    $out = Artisan::output();

    expect($out)->toContain($woo->id)->toContain('1h')->toContain('hourly')->toContain('7 d')->toContain('30 d')->toContain('GB')
        ->toContain('1 service(s) change')->toContain('rule backups.as_sold: off')->toContain('nothing was changed')
        ->and($counts())->toBe($before)
        ->and(app(AutomationLedger::class)->enabled(BackupScheduler::AS_SOLD_RULE))->toBeFalse();
    expect(collect(explode("\n", $out))->first(fn (string $line) => str_contains($line, $start->id)))->toContain('daily'); // listed, unchanged
});
