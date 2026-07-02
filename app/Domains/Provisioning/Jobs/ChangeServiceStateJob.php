<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Jobs;

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\DriverResolver;
use App\Notifications\ServiceSuspendedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

/**
 * Suspend / unsuspend a service through the (mock) driver — queued,
 * idempotent, fully task-tracked like every other external operation.
 */
final class ChangeServiceStateJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public int $serviceId,
        public string $operation, // suspend | unsuspend
        public ?string $reason = null,
    ) {
        if (!in_array($operation, ['suspend', 'unsuspend'], true)) {
            throw new InvalidArgumentException("Unsupported operation [{$operation}].");
        }

        $this->onQueue(Config::string('provisioning.queues.default', 'provisioning'));
    }

    public function handle(DriverResolver $drivers): void
    {
        $service = Service::find($this->serviceId);

        if ($service === null) {
            return;
        }

        // Idempotency: already in the requested state.
        $target = $this->operation === 'suspend' ? ServiceStatus::Suspended : ServiceStatus::Active;

        if ($service->status === $target) {
            return;
        }

        $task = $service->provisioningTasks()->create([
            'operation'    => $this->operation,
            'status'       => TaskStatus::Running,
            'attempts'     => 1,
            'max_attempts' => 3,
            'payload'      => ['reason' => $this->reason],
            'started_at'   => now(),
        ]);

        $driver = $drivers->forService($service);
        $result = $this->operation === 'suspend' ? $driver->suspend($service) : $driver->unsuspend($service);

        if ($result->success) {
            $service->update($this->operation === 'suspend'
                ? ['status' => ServiceStatus::Suspended, 'suspended_at' => now(), 'suspension_reason' => $this->reason]
                : ['status' => ServiceStatus::Active, 'suspended_at' => null, 'suspension_reason' => null]);

            $task->update(['status' => TaskStatus::Success, 'result' => $result->metadata, 'finished_at' => now()]);

            activity('provisioning')
                ->performedOn($service)
                ->withProperties(['operation' => $this->operation, 'task_id' => $task->id, 'mock' => true])
                ->log("service.{$this->operation}ed");

            // Notify customer on suspension (non-fatal)
            if ($this->operation === 'suspend') {
                try {
                    $service->customer?->user?->notify(
                        new ServiceSuspendedNotification($service, $this->reason ?? 'overdue_invoice')
                    );
                } catch (\Throwable) {}
            }

            return;
        }

        $task->update([
            'status'        => TaskStatus::Failed,
            'error_message' => $result->errorMessage,
            'finished_at'   => now(),
        ]);

        activity('provisioning')
            ->performedOn($service)
            ->withProperties(['operation' => $this->operation, 'task_id' => $task->id, 'error' => $result->errorMessage])
            ->log('provisioning.failed');
    }
}
