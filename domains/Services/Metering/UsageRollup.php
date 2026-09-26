<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Metering;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Onhost\Domain\Services\Models\ServiceUsageSample;

/**
 * Days and months from the raw samples, and the owner's retention (decision 12, TASK-0023 metering-core): raw samples
 * 45 days, daily rows 400 days, monthly rows for ever (`onhost.metering.retention`).
 *
 * A day's value is the largest reading of that day. That is right for every metric the watch takes today: disk, files,
 * memory and mail are levels, and traffic is a counter that grows through the month. A day on which nothing could be
 * read keeps no number (quality `unavailable`), never 0. `samples_total`/`samples_measured` say how much of the day
 * the number stands on. The SQL is plain GROUP BY with max/count/sum, the same on SQLite and PostgreSQL.
 */
final class UsageRollup
{
    /** Rows written per upsert and deleted per prune statement: keeps each statement short on a big table. */
    private const CHUNK = 500;

    private const DELETE_CHUNK = 5000;

    /** Raw rows are never pruned below this (the rollup of yesterday needs them). */
    private const MIN_RAW_DAYS = 3;

    /** Daily rows are never pruned below this (a month is rebuilt from its days). */
    private const MIN_DAILY_DAYS = 70;

    /**
     * Rolls the last `$days` complete days into day rows and rebuilds the months they belong to.
     *
     * @return array{days:int, months:int}
     */
    public function rollup(int $days = 2, ?CarbonInterface $now = null): array
    {
        $today = CarbonImmutable::instance($now ?? now())->startOfDay();
        $months = [];
        $rolled = 0;
        for ($back = max(1, $days); $back >= 1; $back--) {
            $day = $today->subDays($back);
            $this->rollDay($day);
            $rolled++;
            $months[$day->format('Y-m')] = $day->startOfMonth();
        }
        foreach ($months as $month) {
            $this->rollMonth($month);
        }

        return ['days' => $rolled, 'months' => count($months)];
    }

    /**
     * Deletes raw samples and daily rows older than the retention; monthly rows stay. Raw samples go by whole days, and
     * each such day is rolled into its day and month rows first: when the rollup was switched off or failing for longer
     * than the raw retention, its days are kept as history instead of being deleted unseen.
     *
     * @return array{raw:int, daily:int}
     */
    public function prune(?CarbonInterface $now = null): array
    {
        $now = CarbonImmutable::instance($now ?? now());
        $rawBefore = $now->subDays(self::rawDays())->startOfDay();
        $this->rollBefore($rawBefore);

        return [
            'raw' => $this->pruneOlder(ServiceUsageSample::GRANULARITY_SAMPLE, $rawBefore),
            'daily' => $this->pruneOlder(ServiceUsageSample::GRANULARITY_DAY, $now->subDays(self::dailyDays())),
        ];
    }

    /** Rolls every day that still has raw samples before `$before` (normally just the one day leaving the retention). */
    private function rollBefore(CarbonImmutable $before): void
    {
        $oldest = ServiceUsageSample::query()->where('granularity', ServiceUsageSample::GRANULARITY_SAMPLE)->where('window_start', '<', $before)->min('window_start');
        if ($oldest === null) {
            return;
        }
        $months = [];
        for ($day = CarbonImmutable::parse((string) $oldest)->startOfDay(); $day->lessThan($before); $day = $day->addDay()) {
            if (ServiceUsageSample::query()->where('granularity', ServiceUsageSample::GRANULARITY_SAMPLE)->where('window_start', '>=', $day)->where('window_start', '<', $day->addDay())->exists()) {
                $this->rollDay($day);
                $months[$day->format('Y-m')] = $day->startOfMonth();
            }
        }
        foreach ($months as $month) {
            $this->rollMonth($month);
        }
    }

    public static function rawDays(): int
    {
        return max(self::MIN_RAW_DAYS, (int) config('onhost.metering.retention.raw_days', 45));
    }

    public static function dailyDays(): int
    {
        return max(self::MIN_DAILY_DAYS, (int) config('onhost.metering.retention.daily_days', 400));
    }

    private function rollDay(CarbonImmutable $day): void
    {
        $groups = ServiceUsageSample::query()
            ->where('granularity', ServiceUsageSample::GRANULARITY_SAMPLE)
            ->where('window_start', '>=', $day)->where('window_start', '<', $day->addDay())
            ->groupBy('service_id', 'metric')
            ->selectRaw('service_id, metric, max(value) as value, count(*) as total, count(value) as measured, max(limit_value) as limit_value, max(unit) as unit, max(limit_kind) as limit_kind, max(organization_id) as organization_id, max(provider_instance_id) as provider_instance_id, max(scope) as scope, max(source) as source, max(observed_at) as observed_at')
            ->toBase()->get();
        $this->write($groups->all(), ServiceUsageSample::GRANULARITY_DAY, $day, $day->addDay(), $day->format('Y-m-d'));
    }

    private function rollMonth(CarbonImmutable $month): void
    {
        $groups = ServiceUsageSample::query()
            ->where('granularity', ServiceUsageSample::GRANULARITY_DAY)
            ->where('window_start', '>=', $month)->where('window_start', '<', $month->addMonth())
            ->groupBy('service_id', 'metric')
            ->selectRaw('service_id, metric, max(value) as value, sum(samples_total) as total, sum(samples_measured) as measured, max(limit_value) as limit_value, max(unit) as unit, max(limit_kind) as limit_kind, max(organization_id) as organization_id, max(provider_instance_id) as provider_instance_id, max(scope) as scope, max(source) as source, max(observed_at) as observed_at')
            ->toBase()->get();
        $this->write($groups->all(), ServiceUsageSample::GRANULARITY_MONTH, $month, $month->addMonth(), $month->format('Y-m'));
    }

    /** @param  list<object>  $groups */
    private function write(array $groups, string $granularity, CarbonImmutable $start, CarbonImmutable $end, string $label): void
    {
        $rows = [];
        foreach ($groups as $group) {
            $measured = (int) $group->measured;
            $rows[] = [
                'service_id' => (string) $group->service_id, 'organization_id' => (string) $group->organization_id, 'provider_instance_id' => $group->provider_instance_id === null ? null : (string) $group->provider_instance_id,
                'metric' => (string) $group->metric, 'granularity' => $granularity, 'window_start' => $start, 'window_end' => $end,
                'value' => $measured > 0 && $group->value !== null ? (int) $group->value : null, 'unit' => (string) $group->unit,
                'limit_value' => $group->limit_value === null ? null : (int) $group->limit_value, 'limit_kind' => (string) $group->limit_kind,
                'quality' => $measured > 0 ? UsageReading::MEASURED : UsageReading::UNAVAILABLE, 'reason' => $measured > 0 ? null : 'not_measured',
                'source' => $group->source === null ? null : (string) $group->source, 'scope' => (string) ($group->scope ?? 'service'),
                'samples_total' => (int) $group->total, 'samples_measured' => $measured, 'observed_at' => CarbonImmutable::parse((string) $group->observed_at),
                'dedupe_key' => "{$group->service_id}:{$group->metric}:{$granularity}:{$label}", 'created_at' => now(),
            ];
        }
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            ServiceUsageSample::query()->upsert($chunk, ['dedupe_key'], ['value', 'unit', 'limit_value', 'limit_kind', 'quality', 'reason', 'source', 'samples_total', 'samples_measured', 'observed_at']);
        }
    }

    private function pruneOlder(string $granularity, CarbonImmutable $before): int
    {
        $deleted = 0;
        do {
            $ids = ServiceUsageSample::query()->where('granularity', $granularity)->where('window_start', '<', $before)->orderBy('id')->limit(self::DELETE_CHUNK)->pluck('id')->all();
            if ($ids !== []) {
                $deleted += ServiceUsageSample::query()->whereIn('id', $ids)->delete();
            }
        } while (count($ids) === self::DELETE_CHUNK);

        return $deleted;
    }
}
