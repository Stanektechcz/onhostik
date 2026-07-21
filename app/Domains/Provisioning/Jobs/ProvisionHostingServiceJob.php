<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Jobs;

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Models\ProvisioningTask;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\DriverResolver;
use App\Domains\Provisioning\Services\ServiceActivationHooks;
use App\Domains\Shared\Support\LogContext;
use App\Models\User;
use App\Notifications\ProvisioningFailedNotification;
use App\Services\WebhookDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Config;

/**
 * Provisions a service via its resolved driver (aaPanel webhosting or
 * Proxmox VPS — whichever DriverResolver::forService() returns).
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

    public function handle(DriverResolver $drivers, WebhookDispatcher $webhooks): void
    {
        $service = Service::find($this->serviceId);

        if ($service === null) {
            return;
        }

        // Audit M166: stamp every log line in this job with who/what/where, so
        // a failure at 02:00 answers "whose service, which server?" from the
        // log line itself instead of a grep expedition.
        LogContext::with([
            'service_id'  => $service->id,
            'customer_id' => $service->customer_id,
            'server_id'   => $service->server_id,
            'driver'      => $service->provisioning_driver?->value,
        ], fn () => $this->provision($service, $drivers, $webhooks));
    }

    private function provision(Service $service, DriverResolver $drivers, WebhookDispatcher $webhooks): void
    {
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
            // Pending task: driver submitted an async task (e.g. Proxmox UPID).
            // Store the task metadata and dispatch a polling job.
            if ($result->isPendingTask()) {
                $task->update([
                    'status' => TaskStatus::Running,
                    'result' => array_merge(
                        $this->sanitizeResult($result->metadata, $result->credentials),
                        ['pending_task' => true],
                    ),
                ]);

                CheckProxmoxTaskStatusJob::dispatch($service->id, $task->id);

                activity('provisioning')
                    ->performedOn($service)
                    ->withProperties(['task_id' => $task->id, 'upid' => $result->metadata['upid'] ?? null])
                    ->log('provisioning.proxmox_task_submitted');

                return;
            }

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

            $webhooks->dispatch('service.provisioned', [
                'service_id'   => $service->id,
                'external_id'  => $result->externalId,
                'service_type' => $service->product?->type?->value,
            ]);

            return;
        }

        // ---- failure path -------------------------------------------------
        $willAutoRetry = $task->attempts < $task->max_attempts;

        $task->update([
            'status'              => $willAutoRetry ? TaskStatus::Retrying : TaskStatus::Failed,
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
            ->withProperties(['task_id' => $task->id, 'error' => $result->errorMessage, 'will_auto_retry' => $willAutoRetry])
            ->log('provisioning.failed');

        if ($willAutoRetry) {
            // Exponential back-off: 30 s, 60 s, 120 s … capped at 15 min.
            $delaySecs = min(30 * (2 ** ($task->attempts - 1)), 900);

            self::dispatch($service->id)->delay(now()->addSeconds($delaySecs));

            activity('provisioning')
                ->performedOn($service)
                ->withProperties(['task_id' => $task->id, 'delay_seconds' => $delaySecs, 'attempt' => $task->attempts])
                ->log('provisioning.auto_retry_scheduled');

            return;
        }

        // All retries exhausted → already parked to ManualReview by resolveTask()
        $webhooks->dispatch('service.failed', [
            'service_id'   => $service->id,
            'operation'    => $task->operation,
            'error'        => $result->errorMessage,
            'attempts'     => $task->attempts,
        ]);
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

                // Notify all admins once — idempotent since status guard prevents repeat.
                $notification = new ProvisioningFailedNotification($task, $service);
                User::whereHas('roles', static fn ($q) => $q->where('name', 'admin'))
                    ->get()
                    ->each(static fn (User $admin) => $admin->notify($notification));
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
