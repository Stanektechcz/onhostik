<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Jobs;

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Models\ProvisioningTask;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\DriverResolver;
use App\Domains\Provisioning\Services\ServiceActivationHooks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Config;

/**
 * Provisions a webhosting service via the (mock) aaPanel driver.
 *
 * Lifecycle contract:
 *  - exactly one ProvisioningTask row per logical create operation; retries
 *    reuse the row (attempts++), keeping the full history reviewable,
 *  - idempotent: an already active/provisioned service short-circuits,
 *  - a simulated failure consumes its `simulate_failure` flag, so the
 *    admin "retry" path then succeeds — the full failure → manual retry →
 *    success loop is exercisable end to end in mock mode,
 *  - attempts beyond max_attempts park the task in ManualReview.
 */
final class ProvisionHostingServiceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public int $serviceId,
    ) {
        $this->onQueue(Config::string('provisioning.queues.high', 'provisioning-high'));
    }

    public function handle(DriverResolver $drivers): void
    {
        $service = Service::find($this->serviceId);

        if ($service === null) {
            return;
        }

        // Idempotency: never provision twice.
        if ($service->external_id !== null && $service->status === ServiceStatus::Active) {
            return;
        }

        $task = $this->resolveTask($service);

        if ($task === null) {
            return; // already succeeded, or parked beyond retry limits
        }

        $task->update([
            'status'     => TaskStatus::Running,
            'attempts'   => $task->attempts + 1,
            'started_at' => $task->started_at ?? now(),
        ]);

        activity('provisioning')
            ->performedOn($service)
            ->withProperties(['task_id' => $task->id, 'operation' => 'create', 'attempt' => $task->attempts])
            ->log('provisioning.started');

        /** @var array<string, mixed> $config */
        $config = $task->payload ?? [];

        $result = $drivers->forService($service)->create($service, $config);

        if ($result->success) {
            $service->update([
                'external_id' => $result->externalId,
                'status'      => ServiceStatus::Active,
            ]);

            $service->server?->increment('current_services');

            $task->update([
                'status'      => TaskStatus::Success,
                'result'      => $this->sanitizeResult($result->metadata, $result->credentials),
                'finished_at' => now(),
            ]);

            activity('provisioning')
                ->performedOn($service)
                ->withProperties(['task_id' => $task->id, 'external_id' => $result->externalId, 'mock' => true])
                ->log('provisioning.succeeded');

            // Monitoring + default backup policy ride on successful activation.
            app(ServiceActivationHooks::class)->handle($service);

            return;
        }

        // ---- failure path -------------------------------------------------
        $task->update([
            'status'              => TaskStatus::Failed,
            'error_message'       => $result->errorMessage,
            'external_request_id' => $result->externalRequestId,
            'finished_at'         => now(),
            // A simulated failure fires once; the manual retry then succeeds.
            'payload'             => array_diff_key($config, ['simulate_failure' => true]),
        ]);

        if ($service->status !== ServiceStatus::Active) {
            $service->update(['status' => ServiceStatus::Failed]);
        }

        activity('provisioning')
            ->performedOn($service)
            ->withProperties(['task_id' => $task->id, 'error' => $result->errorMessage, 'mock' => true])
            ->log('provisioning.failed');
    }

    private function resolveTask(Service $service): ?ProvisioningTask
    {
        $task = ProvisioningTask::query()
            ->where('service_id', $service->id)
            ->where('operation', 'create')
            ->latest('id')
            ->first();

        if ($task === null) {
            $itemConfig = $service->orderItem->config ?? [];

            return ProvisioningTask::create([
                'service_id'   => $service->id,
                'operation'    => 'create',
                'status'       => TaskStatus::Pending,
                'attempts'     => 0,
                'max_attempts' => 3,
                'payload'      => $itemConfig,
            ]);
        }

        if ($task->status === TaskStatus::Success) {
            return null;
        }

        if ($task->attempts >= $task->max_attempts) {
            if ($task->status !== TaskStatus::ManualReview) {
                $task->update(['status' => TaskStatus::ManualReview]);

                activity('provisioning')
                    ->performedOn($service)
                    ->withProperties(['task_id' => $task->id, 'reason' => 'max_attempts_exceeded'])
                    ->log('provisioning.manual_review');
            }

            return null;
        }

        return $task;
    }

    /**
     * Credentials are never persisted into the task result — only non-secret
     * identifiers survive (the mock panel password is delivered out of band
     * in the real flow).
     *
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    private function sanitizeResult(array $metadata, array $credentials): array
    {
        return [
            ...$metadata,
            'panel_username' => $credentials['panel_username'] ?? null,
        ];
    }
}
