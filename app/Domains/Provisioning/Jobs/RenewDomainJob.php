<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Jobs;

use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\ProvisioningTask;
use App\Domains\Provisioning\Services\DriverResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Config;

/**
 * Renews a domain via the WEDOS registrar.
 * Idempotent: skips if a renewal ProvisioningTask for this domain
 * already succeeded in the past 90 days.
 */
final class RenewDomainJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $domainRegistrationId,
    ) {
        $this->onQueue(Config::string('provisioning.queues.high', 'provisioning-high'));
    }

    public function handle(DriverResolver $drivers): void
    {
        $domain = DomainRegistration::find($this->domainRegistrationId);

        if ($domain === null || $domain->service === null) {
            return;
        }

        // Idempotency: skip if renewed in the past 90 days
        $recentRenewal = ProvisioningTask::query()
            ->where('service_id', $domain->service_id)
            ->where('operation', 'renew_domain')
            ->where('status', TaskStatus::Success->value)
            ->where('finished_at', '>=', now()->subDays(90))
            ->exists();

        if ($recentRenewal) {
            return;
        }

        $task = ProvisioningTask::create([
            'service_id'   => $domain->service_id,
            'operation'    => 'renew_domain',
            'status'       => TaskStatus::Running,
            'attempts'     => 1,
            'max_attempts' => 3,
            'payload'      => ['domain' => $domain->fqdn()],
            'started_at'   => now(),
        ]);

        if (config('provisioning.mock_mode', true) === true) {
            // Mock: extend expiry by 1 year
            $domain->update([
                'expires_at' => $domain->expires_at?->addYear() ?? now()->addYear(),
            ]);

            $task->update(['status' => TaskStatus::Success, 'finished_at' => now()]);

            activity('provisioning')
                ->performedOn($domain->service)
                ->withProperties(['domain' => $domain->fqdn(), 'mock' => true, 'task_id' => $task->id])
                ->log('domain.renewed');

            return;
        }

        try {
            $registrar = $drivers->registrar();
            $result    = $registrar->renewDomain($domain->fqdn(), 1);

            if ($result->success) {
                $domain->update([
                    'expires_at' => $domain->expires_at?->addYear() ?? now()->addYear(),
                ]);

                $task->update(['status' => TaskStatus::Success, 'finished_at' => now()]);

                activity('provisioning')
                    ->performedOn($domain->service)
                    ->withProperties(['domain' => $domain->fqdn(), 'task_id' => $task->id])
                    ->log('domain.renewed');
            } else {
                $task->update([
                    'status'        => TaskStatus::Failed,
                    'error_message' => $result->errorMessage,
                    'finished_at'   => now(),
                ]);
            }
        } catch (\Throwable $e) {
            $task->update([
                'status'        => TaskStatus::Failed,
                'error_message' => mb_substr($e->getMessage(), 0, 500),
                'finished_at'   => now(),
            ]);

            throw $e;
        }
    }
}
