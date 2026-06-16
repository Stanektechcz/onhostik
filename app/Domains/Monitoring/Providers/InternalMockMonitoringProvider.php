<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Providers;

use App\Domains\Monitoring\Contracts\MonitoringProviderInterface;
use App\Domains\Monitoring\Enums\MonitorStatus;
use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Monitoring\Models\MonitorCheck;
use App\Domains\Monitoring\Models\MonitorIncident;
use App\Domains\Provisioning\Models\Service;

/**
 * Internal MOCK monitoring — no network I/O, deterministic "up" results.
 */
final class InternalMockMonitoringProvider implements MonitoringProviderInterface
{
    public function createMonitor(Service $service): Monitor
    {
        $target = $service->label ?? "service-{$service->id}";

        $monitor = Monitor::firstOrCreate(
            ['service_id' => $service->id],
            [
                'name'           => "HTTP {$target}",
                'type'           => 'http',
                'target'         => 'https://' . $target,
                'provider'       => 'internal_mock',
                'status'         => MonitorStatus::Up,
                'is_active'      => true,
                'last_check_at'  => now(),
                'uptime_percent' => '99.95',
                'external_id'    => 'MOCK-MON-' . $service->id,
            ],
        );

        if ($monitor->wasRecentlyCreated) {
            $this->checkMonitor($monitor);
        }

        return $monitor;
    }

    public function checkMonitor(Monitor $monitor): MonitorCheck
    {
        $check = $monitor->checks()->create([
            'status'      => MonitorStatus::Up->value,
            'response_ms' => random_int(18, 90),
            'checked_at'  => now(),
        ]);

        $monitor->update([
            'status'        => MonitorStatus::Up,
            'last_check_at' => now(),
        ]);

        return $check;
    }

    public function deleteMonitor(Monitor $monitor): void
    {
        $monitor->delete();
    }

    public function getStatus(Monitor $monitor): MonitorStatus
    {
        return $monitor->status;
    }

    /** @return list<MonitorIncident> */
    public function getIncidents(Monitor $monitor): array
    {
        return array_values($monitor->incidents()
            ->whereNull('resolved_at')
            ->latest('started_at')
            ->get()
            ->all());
    }
}
