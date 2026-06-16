<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Contracts;

use App\Domains\Monitoring\Enums\MonitorStatus;
use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Monitoring\Models\MonitorCheck;
use App\Domains\Monitoring\Models\MonitorIncident;
use App\Domains\Provisioning\Models\Service;

/**
 * Monitoring backend contract (internal mock now, Uptime Kuma later).
 * Same operational rules as provisioning drivers: idempotent, logged,
 * never called with real HTTP unless the provider's gates are open.
 */
interface MonitoringProviderInterface
{
    /** Create (or return the existing) monitor for a service. Idempotent. */
    public function createMonitor(Service $service): Monitor;

    /** Run one check and persist it. */
    public function checkMonitor(Monitor $monitor): MonitorCheck;

    /** Remove the monitor on the backend + locally. */
    public function deleteMonitor(Monitor $monitor): void;

    public function getStatus(Monitor $monitor): MonitorStatus;

    /** @return list<MonitorIncident> open incidents, newest first */
    public function getIncidents(Monitor $monitor): array;
}
