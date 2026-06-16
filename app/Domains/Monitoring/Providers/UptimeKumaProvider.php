<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Providers;

use App\Domains\Monitoring\Contracts\MonitoringProviderInterface;
use App\Domains\Monitoring\Enums\MonitorStatus;
use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Monitoring\Models\MonitorCheck;
use App\Domains\Monitoring\Models\MonitorIncident;
use App\Domains\Provisioning\Exceptions\ProvisioningException;
use App\Domains\Provisioning\Models\Service;

/**
 * Uptime Kuma provider — PLACEHOLDER slot, dry-run only.
 *
 * The real implementation (Kuma REST/socket API) arrives when monitoring
 * goes live; until then every write refuses with a clear message and reads
 * fall back to local data, so nothing can touch a real Kuma instance.
 */
final class UptimeKumaProvider implements MonitoringProviderInterface
{
    public function createMonitor(Service $service): Monitor
    {
        throw new ProvisioningException(
            'Uptime Kuma provider is a placeholder — use internal_mock monitoring.',
            driver: 'uptime_kuma',
            retryable: false,
        );
    }

    public function checkMonitor(Monitor $monitor): MonitorCheck
    {
        throw new ProvisioningException(
            'Uptime Kuma provider is a placeholder — use internal_mock monitoring.',
            driver: 'uptime_kuma',
            retryable: false,
        );
    }

    public function deleteMonitor(Monitor $monitor): void
    {
        throw new ProvisioningException(
            'Uptime Kuma provider is a placeholder — use internal_mock monitoring.',
            driver: 'uptime_kuma',
            retryable: false,
        );
    }

    public function getStatus(Monitor $monitor): MonitorStatus
    {
        return $monitor->status; // local read-only fallback
    }

    /** @return list<MonitorIncident> */
    public function getIncidents(Monitor $monitor): array
    {
        return array_values($monitor->incidents()->whereNull('resolved_at')->latest('started_at')->get()->all());
    }
}
