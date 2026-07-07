<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Services;

use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Models\ServiceResourceUsage;
use Illuminate\Support\Collection;

final class ServiceResourceService
{
    /**
     * Record a usage snapshot for a service, updating live columns and historical log.
     *
     * @param array{cpu_percent?: int|null, ram_mb?: int|null, disk_gb?: int|null, bandwidth_gb?: int|null} $usage
     */
    public function recordUsage(Service $service, array $usage): ServiceResourceUsage
    {
        $service->update([
            'cpu_usage_percent'     => $usage['cpu_percent'] ?? null,
            'ram_usage_mb'          => $usage['ram_mb'] ?? null,
            'disk_usage_gb'         => $usage['disk_gb'] ?? null,
            'bandwidth_usage_gb'    => $usage['bandwidth_gb'] ?? null,
            'last_resource_check_at' => now(),
        ]);

        return ServiceResourceUsage::create([
            'service_id'   => $service->id,
            'cpu_percent'  => $usage['cpu_percent'] ?? null,
            'ram_mb'       => $usage['ram_mb'] ?? null,
            'disk_gb'      => $usage['disk_gb'] ?? null,
            'bandwidth_gb' => $usage['bandwidth_gb'] ?? null,
            'recorded_at'  => now(),
        ]);
    }

    /**
     * Returns list of resources that are over the alert threshold on this service.
     *
     * @return array<string, array{usage: int, limit: int, percent_used: float}>
     */
    public function checkAlerts(Service $service): array
    {
        $threshold = (int) ($service->resource_alert_threshold ?? 80);
        $alerts    = [];

        if ($service->cpu_limit_percent !== null && $service->cpu_usage_percent !== null) {
            $pct = ($service->cpu_usage_percent / $service->cpu_limit_percent) * 100;
            if ($pct >= $threshold) {
                $alerts['cpu'] = [
                    'usage'        => (int) $service->cpu_usage_percent,
                    'limit'        => (int) $service->cpu_limit_percent,
                    'percent_used' => round($pct, 1),
                ];
            }
        }

        if ($service->ram_limit_mb !== null && $service->ram_usage_mb !== null) {
            $pct = ($service->ram_usage_mb / $service->ram_limit_mb) * 100;
            if ($pct >= $threshold) {
                $alerts['ram'] = [
                    'usage'        => (int) $service->ram_usage_mb,
                    'limit'        => (int) $service->ram_limit_mb,
                    'percent_used' => round($pct, 1),
                ];
            }
        }

        if ($service->disk_limit_gb !== null && $service->disk_usage_gb !== null) {
            $pct = ($service->disk_usage_gb / $service->disk_limit_gb) * 100;
            if ($pct >= $threshold) {
                $alerts['disk'] = [
                    'usage'        => (int) $service->disk_usage_gb,
                    'limit'        => (int) $service->disk_limit_gb,
                    'percent_used' => round($pct, 1),
                ];
            }
        }

        if ($service->bandwidth_limit_gb !== null && $service->bandwidth_usage_gb !== null) {
            $pct = ($service->bandwidth_usage_gb / $service->bandwidth_limit_gb) * 100;
            if ($pct >= $threshold) {
                $alerts['bandwidth'] = [
                    'usage'        => (int) $service->bandwidth_usage_gb,
                    'limit'        => (int) $service->bandwidth_limit_gb,
                    'percent_used' => round($pct, 1),
                ];
            }
        }

        return $alerts;
    }

    /**
     * Returns services that have at least one resource over their alert threshold.
     *
     * @return Collection<int, Service>
     */
    public function overThresholdServices(): Collection
    {
        return Service::query()
            ->where(function ($q): void {
                $q->whereRaw('cpu_limit_percent IS NOT NULL AND cpu_usage_percent IS NOT NULL AND cpu_usage_percent >= CAST(cpu_limit_percent AS REAL) * resource_alert_threshold / 100')
                  ->orWhereRaw('ram_limit_mb IS NOT NULL AND ram_usage_mb IS NOT NULL AND ram_usage_mb >= CAST(ram_limit_mb AS REAL) * resource_alert_threshold / 100')
                  ->orWhereRaw('disk_limit_gb IS NOT NULL AND disk_usage_gb IS NOT NULL AND disk_usage_gb >= CAST(disk_limit_gb AS REAL) * resource_alert_threshold / 100')
                  ->orWhereRaw('bandwidth_limit_gb IS NOT NULL AND bandwidth_usage_gb IS NOT NULL AND bandwidth_usage_gb >= CAST(bandwidth_limit_gb AS REAL) * resource_alert_threshold / 100');
            })
            ->with('customer')
            ->get();
    }

    /**
     * Summary stats for admin dashboard.
     *
     * @return array{total_monitored: int, over_threshold: int, last_checked: \Carbon\Carbon|null}
     */
    public function stats(): array
    {
        $totalMonitored = Service::whereNotNull('last_resource_check_at')->count();
        $overThreshold  = $this->overThresholdServices()->count();
        $lastChecked    = Service::whereNotNull('last_resource_check_at')
            ->max('last_resource_check_at');

        return [
            'total_monitored' => $totalMonitored,
            'over_threshold'  => $overThreshold,
            'last_checked'    => $lastChecked ? \Carbon\Carbon::parse($lastChecked) : null,
        ];
    }
}
