<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Services;

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Server;

/**
 * Free capacity across the server fleet, per driver (audit E52).
 *
 * ServerSelector already picks the least-loaded server and shouts once the
 * fleet is full — but by then a paid order is already landing on an
 * over-committed box. This reports the headroom BEFORE that happens, so
 * capacity is a planning input rather than an incident.
 *
 * Load is counted live from the services table for the same reason
 * ServerSelector does it: `Server::current_services` drifts upward because it
 * is incremented on provision and never decremented on terminate.
 */
final class FleetCapacityReport
{
    /**
     * @return list<array{
     *   driver: string, servers: int, used: int, capacity: int|null,
     *   free: int|null, used_percent: float|null, unlimited: bool
     * }>
     */
    public function perDriver(): array
    {
        $out = [];

        foreach (ProvisioningDriver::cases() as $driver) {
            $servers = Server::query()
                ->where('driver', $driver->value)
                ->where('status', 'active')
                ->withCount(['services as live_services_count' => function ($q): void {
                    $q->whereNotIn('status', [
                        ServiceStatus::Terminated->value,
                        ServiceStatus::Failed->value,
                    ]);
                }])
                ->get();

            if ($servers->isEmpty()) {
                continue;
            }

            $used = 0;
            $capacity = 0;
            $unlimited = false;

            foreach ($servers as $server) {
                $used += (int) ($server->live_services_count ?? 0);

                if ($server->max_services === null) {
                    // One uncapped server makes the whole driver uncapped —
                    // reporting a percentage would be meaningless.
                    $unlimited = true;

                    continue;
                }

                $capacity += (int) $server->max_services;
            }

            $out[] = [
                'driver'       => $driver->value,
                'servers'      => $servers->count(),
                'used'         => $used,
                'capacity'     => $unlimited ? null : $capacity,
                'free'         => $unlimited ? null : max(0, $capacity - $used),
                'used_percent' => ($unlimited || $capacity === 0)
                    ? null
                    : round($used / $capacity * 100, 1),
                'unlimited'    => $unlimited,
            ];
        }

        return $out;
    }

    /**
     * Drivers at or above the warning threshold — the ones worth an alert.
     *
     * @return list<array{driver: string, used: int, capacity: int|null, free: int|null, used_percent: float|null, servers: int, unlimited: bool}>
     */
    public function breaching(?float $thresholdPercent = null): array
    {
        $threshold = $thresholdPercent ?? (float) config('provisioning.capacity_warn_percent', 80);

        return array_values(array_filter(
            $this->perDriver(),
            static fn (array $row): bool => $row['used_percent'] !== null && $row['used_percent'] >= $threshold,
        ));
    }
}
