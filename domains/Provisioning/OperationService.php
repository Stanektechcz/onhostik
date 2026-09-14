<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Provisioning\Jobs\RunOperation;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/** Creates, retries and cancels operations; dispatches the runner job on the provider queue. */
final class OperationService
{
    public function __construct(
        private readonly BusDispatcher $bus,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
        private readonly FreezeSwitch $freeze,
    ) {}

    /**
     * @param  class-string<Workflow>  $workflow
     * @param  array<string,mixed>  $desired
     */
    public function start(string $workflow, string $idempotencyKey, array $desired, CommandContext $actor, ?string $serviceId = null, ?string $organizationId = null, ?string $orderItemId = null, ?string $providerInstanceId = null, ?string $domainId = null, bool $dispatch = true): Operation
    {
        if (! is_subclass_of($workflow, Workflow::class)) {
            throw new DomainError('workflow_invalid', "{$workflow} is not a Workflow", 500);
        }
        $existing = Operation::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }
        $instance = app($workflow);
        $steps = $instance->steps(new Operation(['desired' => $desired, 'context' => [], 'service_id' => $serviceId]));
        $operation = Operation::query()->create([
            'organization_id' => $organizationId,
            'service_id' => $serviceId,
            'order_item_id' => $orderItemId,
            'domain_id' => $domainId,
            'kind' => $workflow::kind(),
            'workflow' => $workflow,
            'state' => Operation::PENDING,
            'step' => 0,
            'steps_total' => count($steps),
            'step_label' => $steps[0]?->label(),
            'actor_type' => $actor->actorType,
            'actor_id' => $actor->actorId,
            'idempotency_key' => $idempotencyKey,
            'correlation_id' => $actor->correlationId ?? CommandContext::currentCorrelationId(),
            'desired' => $desired,
            'context' => [],
            'provider_instance_id' => $providerInstanceId,
            'queue' => $instance->queue(new Operation(['desired' => $desired, 'provider_instance_id' => $providerInstanceId])),
            'queued_at' => now(),
            'next_run_at' => now(),
            'retry_until' => now()->addSeconds(config('onhost.provisioning.retry_until_seconds', 6 * 3600)),
        ]);
        $this->audit->record($actor->withScope($organizationId), 'operation.start', 'succeeded', ['kind' => $operation->kind, 'idempotency_key' => $idempotencyKey], 'operation', $operation->id);
        $this->outbox->publish(GenericEvent::of('operation.started', 'operation', $operation->id, ['kind' => $operation->kind, 'service_id' => $serviceId], $organizationId));
        if ($dispatch) {
            $this->dispatch($operation);
        }

        return $operation;
    }

    public function dispatch(Operation $operation, int $delaySeconds = 0): void
    {
        $job = new RunOperation($operation->id);
        $job->onQueue($operation->queue);
        if ($delaySeconds > 0) {
            $job->delay(now()->addSeconds($delaySeconds));
        }
        $this->bus->dispatch($job);
    }

    public function retry(Operation $operation, CommandContext $actor, ?string $reason = null): Operation
    {
        if ($operation->state !== Operation::FAILED) {
            throw new DomainError('operation_not_failed', "Operation {$operation->id} is {$operation->state}; only FAILED operations can be retried.", 409);
        }
        if ($this->freeze->isFrozen()) {
            throw new DomainError('provisioning_frozen', 'Provisioning is frozen by an incident switch.', 423);
        }
        // A failed operation was compensated (the workflow removed what it had created), so the retry starts the workflow
        // over: step outputs such as remote ids are forgotten, scheduling and idempotent creates are repeated safely.
        $operation->forceFill(['state' => Operation::PENDING, 'error' => null, 'step' => 0, 'step_label' => null, 'context' => [], 'external_handle' => null, 'attempts' => 0, 'started_at' => null, 'next_run_at' => now(), 'retry_until' => now()->addHours(6), 'finished_at' => null])->save();
        $this->audit->record($actor->withScope($operation->organization_id), 'operation.retry', 'succeeded', ['reason' => $reason, 'kind' => $operation->kind], 'operation', $operation->id);
        $this->dispatch($operation);

        return $operation;
    }

    public function cancel(Operation $operation, CommandContext $actor, string $reason): Operation
    {
        return DB::transaction(function () use ($operation, $actor, $reason) {
            $operation = Operation::query()->lockForUpdate()->findOrFail($operation->id);
            if ($operation->isTerminal()) {
                return $operation;
            }
            if ($operation->state === Operation::RUNNING) {
                throw new DomainError('operation_running', 'A running step cannot be cancelled; wait for it to finish.', 409);
            }
            $operation->forceFill(['state' => Operation::CANCELLED, 'finished_at' => now(), 'error' => ['message' => "cancelled: {$reason}", 'by' => $actor->actorId]])->save();
            $this->audit->record($actor->withScope($operation->organization_id), 'operation.cancel', 'succeeded', ['reason' => $reason], 'operation', $operation->id);
            $this->outbox->publish(GenericEvent::of('operation.cancelled', 'operation', $operation->id, ['reason' => $reason, 'kind' => $operation->kind, 'service_id' => $operation->service_id], $operation->organization_id));

            return $operation;
        });
    }

    /** Operations due to run (scheduler tick): PENDING/WAITING with next_run_at in the past. */
    public function dispatchDue(int $limit = 200): int
    {
        $count = 0;
        $due = Operation::query()->whereIn('state', [Operation::PENDING, Operation::WAITING])->where('next_run_at', '<=', now())->orderBy('next_run_at')->limit($limit)->get();
        foreach ($due as $operation) {
            $this->dispatch($operation);
            $count++;
        }

        return $count;
    }

    /** Stuck detection: RUNNING for longer than the step budget => back to PENDING for a fresh attempt (worker crash). */
    public function recoverStuck(int $olderThanMinutes = 30): int
    {
        return Operation::query()->where('state', Operation::RUNNING)->where('started_at', '<', now()->subMinutes($olderThanMinutes))
            ->update(['state' => Operation::PENDING, 'next_run_at' => now()]);
    }
}
