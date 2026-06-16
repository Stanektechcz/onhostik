<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Services;

use App\Domains\Monitoring\Contracts\MonitoringProviderInterface;
use App\Domains\Monitoring\Providers\InternalMockMonitoringProvider;
use App\Domains\Monitoring\Providers\UptimeKumaProvider;

final class MonitoringResolver
{
    public function provider(): MonitoringProviderInterface
    {
        // Global mock mode always wins; Uptime Kuma is a reserved slot.
        if (config('provisioning.mock_mode', true) === true) {
            return app(InternalMockMonitoringProvider::class);
        }

        return app(UptimeKumaProvider::class);
    }
}
