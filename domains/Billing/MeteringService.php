<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing;

use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Billing\Models\UsageEvent;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/**
 * Usage collection (blueprint §49.3): one event per whole hour (VPS/VDS/DB/apps) or
 * day (game servers) while the service is active. Events are unique per
 * service+metric+interval, so the collector can run any number of times.
 */
final class MeteringService
{
    public const HOURLY_FAMILIES = ['cloud', 'data', 'apps'];

    public const DAILY_FAMILIES = ['game'];

    /** @return array{events:int, services:int} */
    public function collect(?CarbonInterface $until = null): array
    {
        $until = ($until ?? now())->copy();
        $metered = Product::query()->whereIn('billing_model', ['hourly', 'daily', 'metered'])->pluck('billing_model', 'key');
        $stats = ['events' => 0, 'services' => 0];
        $services = Service::query()->whereIn('product_key', $metered->keys())->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED, ServiceStateMachine::RESIZING])->whereNotNull('activated_at')->get();
        foreach ($services as $service) {
            $model = (string) $metered->get($service->product_key);
            $daily = $model === 'daily' || in_array($service->family, self::DAILY_FAMILIES, true);
            $metric = $daily ? 'game_days' : ($service->family === 'apps' ? 'app_hours' : 'vm_hours');
            $step = $daily ? 'day' : 'hour';
            $last = UsageEvent::query()->where('service_id', $service->id)->where('metric', $metric)->max('period_end');
            $cursor = $last ? Carbon::parse($last) : $service->activated_at->copy()->{$daily ? 'startOfDay' : 'startOfHour'}();
            $stop = $until->copy()->{$daily ? 'startOfDay' : 'startOfHour'}();
            $created = 0;
            while ($cursor < $stop && $created < 24 * 31) {
                $end = $cursor->copy()->{$daily ? 'addDay' : 'addHour'}();
                if ($this->store($service, $metric, '1', $step, $cursor, $end)) {
                    $created++;
                }
                if ((int) ($service->entitlements['ipv4'] ?? 0) >= 1 && $this->store($service, 'ipv4_hours', $daily ? '24' : '1', 'hour', $cursor, $end)) {
                    $created++;
                }
                $cursor = $end;
            }
            if ($created > 0) {
                $stats['services']++;
                $stats['events'] += $created;
            }
        }

        return $stats;
    }

    /** Explicit metrics from collectors (traffic, tokens, storage). Returns false when the interval was already recorded. */
    public function record(Service $service, string $metric, string $quantity, string $unit, CarbonInterface $periodStart, CarbonInterface $periodEnd, ?string $source = null): bool
    {
        return $this->store($service, $metric, $quantity, $unit, $periodStart, $periodEnd, $source);
    }

    private function store(Service $service, string $metric, string $quantity, string $unit, CarbonInterface $start, CarbonInterface $end, ?string $source = 'collector'): bool
    {
        try {
            DB::transaction(fn () => UsageEvent::query()->create([ // a savepoint: a duplicate never aborts the caller's transaction on PostgreSQL
                'service_id' => $service->id, 'organization_id' => $service->organization_id, 'metric' => $metric, 'quantity' => $quantity, 'unit' => $unit,
                'period_start' => $start, 'period_end' => $end, 'source' => $source, 'dedupe_key' => "{$service->id}:{$metric}:".$start->toIso8601String(), 'rated' => false,
            ]));

            return true;
        } catch (QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'unique')) {
                return false;
            }
            throw $e;
        }
    }
}
