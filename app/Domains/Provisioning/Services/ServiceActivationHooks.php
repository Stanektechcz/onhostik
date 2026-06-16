<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Services;

use App\Domains\Backups\Models\BackupPolicy;
use App\Domains\Monitoring\Services\MonitoringResolver;
use App\Domains\Provisioning\Models\Service;
use Throwable;

/**
 * Post-activation side effects: every freshly provisioned hosting service
 * gets a monitor and a default backup policy. Both creations are
 * idempotent (firstOrCreate) and never break provisioning on failure —
 * they log and move on.
 */
final class ServiceActivationHooks
{
    public function __construct(
        private readonly MonitoringResolver $monitoring,
    ) {}

    public function handle(Service $service): void
    {
        try {
            $monitor = $this->monitoring->provider()->createMonitor($service);

            if ($monitor->wasRecentlyCreated) {
                activity('monitoring')
                    ->performedOn($service)
                    ->withProperties(['monitor_id' => $monitor->id, 'mock' => true])
                    ->log('monitoring.monitor_created');
            }
        } catch (Throwable $e) {
            report($e);
        }

        try {
            $policy = BackupPolicy::firstOrCreate(
                ['service_id' => $service->id],
                ['frequency' => 'daily', 'retention_days' => 14, 'provider' => 'local_mock', 'is_active' => true],
            );

            if ($policy->wasRecentlyCreated) {
                activity('backup')
                    ->performedOn($service)
                    ->withProperties(['backup_policy_id' => $policy->id, 'mock' => true])
                    ->log('backup.policy_created');
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
