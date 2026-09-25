<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Services\Metering\UsageReading;
use Onhost\Domain\Services\Metering\UsageRecorder;
use Onhost\Domain\Services\Metering\UsageRollup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceUsageSample;

/*
 * The samples behind every usage number (owner decision 12, TASK-0023 metering-core): raw readings are rolled into one row
 * per day and one per month, raw rows are kept 45 days, daily rows 400 days and monthly rows for ever. A day on which
 * nothing could be read stays "not measured" — its value is nothing, not 0.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** One stored row, written directly (the retention test needs rows of any age and granularity). */
function usageRollupRow(Service $service, string $granularity, CarbonImmutable $start, ?int $value = 1): ServiceUsageSample
{
    return ServiceUsageSample::query()->create([
        'service_id' => $service->id, 'organization_id' => $service->organization_id, 'provider_instance_id' => $service->provider_instance_id, 'metric' => 'disk', 'granularity' => $granularity,
        'window_start' => $start, 'window_end' => $start->addHour(), 'value' => $value, 'unit' => 'bytes', 'limit_value' => 100, 'limit_kind' => 'hard', 'quality' => $value === null ? 'unavailable' : 'measured',
        'scope' => 'service', 'samples_total' => 1, 'samples_measured' => $value === null ? 0 : 1, 'observed_at' => $start, 'dedupe_key' => $service->id.':disk:'.$granularity.':'.$start->toIso8601String(),
    ]);
}

it('rolls raw samples into days (the largest reading, how many were measured) and months', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $recorder = app(UsageRecorder::class);
    $now = CarbonImmutable::parse('2026-09-20 10:00:00');
    $dayOne = $now->subDays(2)->startOfDay(); // 18th: two readings and one the panel could not give
    $recorder->record($service, [UsageReading::measured('disk', 10, 100, 'quotas')], $dayOne->addHours(1));
    $recorder->record($service, [UsageReading::measured('disk', 30, 100, 'quotas')], $dayOne->addHours(5));
    $recorder->record($service, [UsageReading::unavailable('disk', 100, 'not_reported', 'quotas')], $dayOne->addHours(9));
    $recorder->record($service, [UsageReading::measured('disk', 25, 100, 'quotas')], $dayOne->addHours(9)); // a rerun in the same hour replaces, never duplicates
    $dayTwo = $now->subDay()->startOfDay(); // 19th: nothing could be read at all
    $recorder->record($service, [UsageReading::unavailable('disk', 100, 'not_reported', 'quotas')], $dayTwo->addHours(3));

    $result = app(UsageRollup::class)->rollup(2, $now);

    expect($result)->toMatchArray(['days' => 2, 'months' => 1]);
    $days = ServiceUsageSample::query()->where('granularity', ServiceUsageSample::GRANULARITY_DAY)->orderBy('window_start')->get();
    expect($days)->toHaveCount(2)
        ->and($days[0]->value)->toBe(30)->and($days[0]->samples_total)->toBe(3)->and($days[0]->samples_measured)->toBe(3)->and($days[0]->quality)->toBe('measured')
        ->and($days[1]->value)->toBeNull()->and($days[1]->quality)->toBe('unavailable')->and($days[1]->samples_measured)->toBe(0);
    $month = ServiceUsageSample::query()->where('granularity', ServiceUsageSample::GRANULARITY_MONTH)->sole();
    expect($month->value)->toBe(30)->and($month->samples_total)->toBe(4)->and($month->samples_measured)->toBe(3)
        ->and($month->window_start->toDateString())->toBe('2026-09-01')->and($month->window_end->toDateString())->toBe('2026-10-01');

    // run twice: the same rows, refreshed
    app(UsageRollup::class)->rollup(2, $now);
    expect(ServiceUsageSample::query()->where('granularity', '!=', ServiceUsageSample::GRANULARITY_SAMPLE)->count())->toBe(3);
});

it('prunes by the owner\'s retention: raw 45 days, daily 400 days, monthly for ever', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $now = CarbonImmutable::parse('2026-09-20 10:00:00');
    $oldRaw = usageRollupRow($service, ServiceUsageSample::GRANULARITY_SAMPLE, $now->subDays(46));
    $keptRaw = usageRollupRow($service, ServiceUsageSample::GRANULARITY_SAMPLE, $now->subDays(44));
    $oldDay = usageRollupRow($service, ServiceUsageSample::GRANULARITY_DAY, $now->subDays(401)->startOfDay());
    $keptDay = usageRollupRow($service, ServiceUsageSample::GRANULARITY_DAY, $now->subDays(399)->startOfDay());
    $ancientMonth = usageRollupRow($service, ServiceUsageSample::GRANULARITY_MONTH, $now->subYears(5)->startOfMonth(), null);

    expect(app(UsageRollup::class)->prune($now))->toBe(['raw' => 1, 'daily' => 1]);
    expect(ServiceUsageSample::query()->whereKey([$keptRaw->id, $keptDay->id, $ancientMonth->id])->count())->toBe(3)
        ->and(ServiceUsageSample::query()->whereKey($oldRaw->id)->exists())->toBeFalse()->and(ServiceUsageSample::query()->whereKey($oldDay->id)->exists())->toBeFalse()
        // the pruned raw row's day was rolled first: its day row stays (daily retention) instead of the day being lost
        ->and(ServiceUsageSample::query()->where('granularity', ServiceUsageSample::GRANULARITY_DAY)->where('dedupe_key', $service->id.':disk:day:'.$now->subDays(46)->format('Y-m-d'))->exists())->toBeTrue();
    expect(app(UsageRollup::class)->prune($now))->toBe(['raw' => 0, 'daily' => 0]); // idempotent
    expect(config('onhost.metering.retention'))->toMatchArray(['raw_days' => 45, 'daily_days' => 400]);
});

it('runs the rollup and the prune from the scheduler, and a switched-off rule records a skip', function () {
    $this->artisan('onhost:metering:rollup', ['--days' => 1])->assertSuccessful();
    $this->artisan('onhost:metering:prune')->assertSuccessful();
    $ledger = app(AutomationLedger::class);
    expect($ledger->last('metering.rollup')['stats'])->toHaveKeys(['days', 'months'])->and($ledger->last('metering.prune')['stats'])->toHaveKeys(['raw', 'daily']);

    $ledger->setEnabled('metering.prune', false, 'test');
    $this->artisan('onhost:metering:prune')->expectsOutputToContain('switched off')->assertSuccessful();
    expect($ledger->last('metering.prune')['stats'])->toBe(['skipped' => 1]);
});

it('never prunes below the retention floor, even when configured lower', function () {
    config(['onhost.metering.retention.raw_days' => 1, 'onhost.metering.retention.daily_days' => 5]);

    expect(UsageRollup::rawDays())->toBe(3)->and(UsageRollup::dailyDays())->toBe(70);

    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $now = CarbonImmutable::parse('2026-09-20 10:00:00');
    $recentRaw = usageRollupRow($service, ServiceUsageSample::GRANULARITY_SAMPLE, $now->subDays(2));
    $recentDay = usageRollupRow($service, ServiceUsageSample::GRANULARITY_DAY, $now->subDays(60)->startOfDay());

    expect(app(UsageRollup::class)->prune($now))->toBe(['raw' => 0, 'daily' => 0])
        ->and(ServiceUsageSample::query()->whereKey([$recentRaw->id, $recentDay->id])->count())->toBe(2);
});

it('rolls a day into its day and month rows before pruning its raw samples, so a stalled rollup loses nothing', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $recorder = app(UsageRecorder::class);
    $now = CarbonImmutable::parse('2026-09-20 10:00:00');
    $day = $now->subDays(50)->startOfDay(); // never rolled: the rollup was switched off or failing for weeks
    $recorder->record($service, [UsageReading::measured('disk', 10, 100, 'quotas')], $day->addHours(2));
    $recorder->record($service, [UsageReading::measured('disk', 40, 100, 'quotas')], $day->addHours(7));
    expect(ServiceUsageSample::query()->where('granularity', '!=', ServiceUsageSample::GRANULARITY_SAMPLE)->count())->toBe(0);

    expect(app(UsageRollup::class)->prune($now)['raw'])->toBe(2);

    $kept = ServiceUsageSample::query()->where('granularity', ServiceUsageSample::GRANULARITY_DAY)->sole();
    expect($kept->value)->toBe(40)->and($kept->samples_total)->toBe(2)->and($kept->window_start->toDateString())->toBe($day->toDateString())
        ->and(ServiceUsageSample::query()->where('granularity', ServiceUsageSample::GRANULARITY_MONTH)->sole()->value)->toBe(40)
        ->and(ServiceUsageSample::query()->where('granularity', ServiceUsageSample::GRANULARITY_SAMPLE)->count())->toBe(0);
});
