<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Jobs;

use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\ProvisioningTask;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\DriverResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * Registers a domain via the (mock) WEDOS registrar for a hosting service.
 *
 * Same lifecycle contract as ProvisionHostingServiceJob: one reusable
 * ProvisioningTask per logical operation, idempotent re-runs, one-shot
 * simulated failures, ManualReview beyond max attempts.
 */
final class RegisterDomainJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public int $serviceId,
        public string $fqdn,
    ) {
        $this->onQueue(Config::string('provisioning.queues.high', 'provisioning-high'));
    }

    public function handle(DriverResolver $drivers): void
    {
        $service = Service::find($this->serviceId);

        if ($service === null) {
            return;
        }

        // Idempotency: this domain is already registered for this service.
        $existing = $service->domainRegistration;

        if ($existing !== null && $existing->wedos_domain_id !== null) {
            return;
        }

        $task = $this->resolveTask($service);

        if ($task === null) {
            return;
        }

        $task->update([
            'status'     => TaskStatus::Running,
            'attempts'   => $task->attempts + 1,
            'started_at' => $task->started_at ?? now(),
        ]);

        activity('domain')
            ->performedOn($service)
            ->withProperties(['task_id' => $task->id, 'domain' => $this->fqdn, 'attempt' => $task->attempts])
            ->log('domain.registration_started');

        /** @var array<string, mixed> $options */
        $options = $task->payload ?? [];

        $result = $drivers->registrar()->registerDomain($this->fqdn, $options);

        if ($result->success) {
            $expiresAt = is_string($result->metadata['expires_at'] ?? null)
                ? Carbon::parse($result->metadata['expires_at'])
                : now()->addYear();

            [$sld, $tld] = array_pad(explode('.', $this->fqdn, 2), 2, '');

            DomainRegistration::updateOrCreate(
                ['service_id' => $service->id],
                [
                    'domain'          => $sld,
                    'tld'             => $tld,
                    'registrar'       => 'wedos',
                    'registered_at'   => now(),
                    'expires_at'      => $expiresAt,
                    'auto_renew'      => true,
                    'nameservers'     => $result->metadata['nameservers'] ?? [],
                    'wedos_domain_id' => $result->externalId,
                ],
            );

            $task->update([
                'status'      => TaskStatus::Success,
                'result'      => [...$result->metadata, 'external_id' => $result->externalId],
                'finished_at' => now(),
            ]);

            activity('domain')
                ->performedOn($service)
                ->withProperties(['task_id' => $task->id, 'domain' => $this->fqdn, 'external_id' => $result->externalId, 'mock' => true])
                ->log('domain.registered');

            return;
        }

        // ---- failure path -------------------------------------------------
        $task->update([
            'status'              => TaskStatus::Failed,
            'error_message'       => $result->errorMessage,
            'external_request_id' => $result->externalRequestId,
            'finished_at'         => now(),
            'payload'             => array_diff_key($options, ['simulate_failure' => true]),
        ]);

        activity('domain')
            ->performedOn($service)
            ->withProperties(['task_id' => $task->id, 'domain' => $this->fqdn, 'error' => $result->errorMessage, 'mock' => true])
            ->log('domain.registration_failed');
    }

    private function resolveTask(Service $service): ?ProvisioningTask
    {
        $task = ProvisioningTask::query()
            ->where('service_id', $service->id)
            ->where('operation', 'register_domain')
            ->latest('id')
            ->first();

        if ($task === null) {
            $itemConfig = $service->orderItem->config ?? [];

            return ProvisioningTask::create([
                'service_id'   => $service->id,
                'operation'    => 'register_domain',
                'status'       => TaskStatus::Pending,
                'attempts'     => 0,
                'max_attempts' => 3,
                'payload'      => [
                    'domain'           => $this->fqdn,
                    'simulate_failure' => ($itemConfig['simulate_failure'] ?? false) === true,
                ],
            ]);
        }

        if ($task->status === TaskStatus::Success) {
            return null;
        }

        if ($task->attempts >= $task->max_attempts) {
            if ($task->status !== TaskStatus::ManualReview) {
                $task->update(['status' => TaskStatus::ManualReview]);

                activity('domain')
                    ->performedOn($service)
                    ->withProperties(['task_id' => $task->id, 'reason' => 'max_attempts_exceeded'])
                    ->log('domain.manual_review');
            }

            return null;
        }

        return $task;
    }
}
