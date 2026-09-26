<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Metering;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceUsageSample;

/**
 * Keeps every usage reading as a raw sample (TASK-0023 metering-core). One row per service, metric and hour: a rerun in
 * the same hour replaces the reading instead of adding a second one. Nothing here touches `usage_events` or the
 * `MeteringService` — those are billing, and a sample is never billed.
 */
final class UsageRecorder
{
    /**
     * @param  list<UsageReading>  $readings
     * @return int rows written
     */
    public function record(Service $service, array $readings, CarbonInterface $at): int
    {
        if ($readings === []) {
            return 0;
        }
        $hour = CarbonImmutable::instance($at)->startOfHour();
        $rows = [];
        foreach ($readings as $reading) {
            $rows[$reading->metric] = [
                'service_id' => $service->id, 'organization_id' => $service->organization_id, 'provider_instance_id' => $service->provider_instance_id,
                'metric' => $reading->metric, 'granularity' => ServiceUsageSample::GRANULARITY_SAMPLE, 'window_start' => $hour, 'window_end' => $hour->addHour(),
                'value' => $reading->value, 'unit' => $reading->unit, 'limit_value' => $reading->limit, 'limit_kind' => $reading->limitKind, 'quality' => $reading->quality,
                'reason' => $reading->reason, 'source' => $reading->source === null ? null : substr($reading->source, 0, 40), 'scope' => $reading->scope,
                'samples_total' => 1, 'samples_measured' => $reading->value === null ? 0 : 1, 'observed_at' => CarbonImmutable::instance($at),
                'dedupe_key' => "{$service->id}:{$reading->metric}:sample:".$hour->format('Y-m-d\TH'),
                'created_at' => now(),
            ];
        }
        ServiceUsageSample::query()->upsert(array_values($rows), ['dedupe_key'], ['value', 'unit', 'limit_value', 'limit_kind', 'quality', 'reason', 'source', 'samples_measured', 'observed_at']);

        return count($rows);
    }

    /** The newest reading of the metric that has a number (any granularity), or null when it was never measured. */
    public function latestMeasured(Service $service, string $metric): ?ServiceUsageSample
    {
        return ServiceUsageSample::query()->where('service_id', $service->id)->where('metric', $metric)->whereNotNull('value')
            ->orderByDesc('window_start')->orderByDesc('id')->first();
    }

    public function hasMeasured(Service $service, string $metric): bool
    {
        return ServiceUsageSample::query()->where('service_id', $service->id)->where('metric', $metric)->whereNotNull('value')->exists();
    }
}
