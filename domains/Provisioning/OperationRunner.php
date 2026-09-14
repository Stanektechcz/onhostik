<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Carbon\Carbon;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\OperationAttempt;
use Onhost\Domain\Provisioning\Workflow\Step;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Redaction\Redactor;
use Onhost\Platform\Resilience\RetryPolicy;
use Onhost\Providers\Contracts\AsyncHandle;
use Throwable;

/**
 * Executes operations step by step. Never assumes an HTTP timeout means the
 * remote operation failed: transient failures are retried with backoff and every
 * step must be idempotent (read-before-create). Terminal failures trigger the
 * workflow compensation and an `operation.failed` event (ticket + order state).
 */
final class OperationRunner
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly Container $container,
        private readonly OutboxPublisher $outbox,
        private readonly FreezeSwitch $freeze,
        private readonly Redactor $redactor,
    ) {}

    /** Run as many steps as possible within the time budget. Returns the operation's new state. */
    public function tick(Operation $operation, int $budgetSeconds = 60): string
    {
        $operation = Operation::query()->findOrFail($operation->id);
        if ($operation->isTerminal() || $operation->state === Operation::FAILED) {
            return $operation->state;
        }
        if ($this->freeze->isFrozen() && $operation->step === 0 && $operation->state === Operation::PENDING) {
            $operation->forceFill(['next_run_at' => now()->addMinutes(5)])->save();

            return $operation->state;
        }
        if (! $this->claim($operation)) {
            return $operation->refresh()->state;
        }

        /** @var Workflow $workflow */
        $workflow = $this->container->make($operation->workflow);
        $context = $this->context($operation);
        $steps = $workflow->steps($operation);
        $deadline = microtime(true) + $budgetSeconds;

        while (microtime(true) < $deadline) {
            $operation->refresh();
            if ($operation->step >= count($steps)) {
                return $this->succeed($operation);
            }
            $step = $steps[$operation->step];
            $attempt = $this->beginAttempt($operation, $step);
            try {
                $handle = $operation->external_handle;
                $result = $handle !== null
                    ? $step->poll($context, AsyncHandle::fromArray($handle))
                    : $step->run($context);
            } catch (ProviderException $e) {
                $result = StepResult::fail($e->getMessage(), $e->isRetryable(), $e->toArray(), $e->retryAfterSeconds);
            } catch (Throwable $e) {
                $result = StepResult::fail(get_class($e).': '.$e->getMessage(), false, ['trace' => mb_substr($e->getTraceAsString(), 0, 1500)]);
            }
            $context = $this->context($operation->refresh()); // steps may have changed the service/bindings

            switch ($result->outcome) {
                case StepResult::DONE:
                case StepResult::SKIP:
                    $this->finishAttempt($attempt, 'ok');
                    $next = $operation->step + 1;
                    $operation->withContext($result->context)->forceFill([
                        'step' => $next, 'external_handle' => null, 'state' => Operation::RUNNING,
                        'step_label' => $steps[$next] ?? null ? $steps[$next]->label() : $operation->step_label,
                    ])->save();
                    $context = $this->context($operation);

                    continue 2;
                case StepResult::WAIT:
                    $this->finishAttempt($attempt, 'wait');
                    $operation->withContext($result->context)->forceFill([
                        'state' => Operation::WAITING, 'external_handle' => $result->handle?->toArray(),
                        'next_run_at' => now()->addSeconds($result->handle?->pollIntervalSeconds ?? 5),
                    ])->save();
                    if ($this->handleTimedOut($operation)) {
                        return $this->fail($operation, $workflow, $context, 'provider task exceeded its timeout', false, []);
                    }

                    return Operation::WAITING;
                default:
                    $this->finishAttempt($attempt, $result->retryable ? 'retry' : 'fail', $result->error);
                    $policy = RetryPolicy::provisioning();
                    $elapsed = $operation->queued_at ? now()->diffInSeconds($operation->queued_at, true) : 0;
                    if ($result->retryable && $policy->canRetry($operation->attempts, (int) $elapsed)) {
                        $delay = $policy->delayForAttempt($operation->attempts, $result->retryAfterSeconds);
                        $operation->forceFill(['state' => Operation::PENDING, 'next_run_at' => now()->addSeconds($delay), 'error' => $this->errorPayload($result->error, $result->detail, true)])->save();

                        return Operation::PENDING;
                    }

                    return $this->fail($operation, $workflow, $context, (string) $result->error, $result->retryable, $result->detail);
            }
        }
        // Budget exhausted mid-way: leave it PENDING for the next tick.
        $operation->forceFill(['state' => Operation::PENDING, 'next_run_at' => now()])->save();

        return Operation::PENDING;
    }

    private function claim(Operation $operation): bool
    {
        return DB::transaction(function () use ($operation) {
            $fresh = Operation::query()->lockForUpdate()->findOrFail($operation->id);
            if (! in_array($fresh->state, [Operation::PENDING, Operation::WAITING], true)) {
                return false;
            }
            if ($fresh->state === Operation::WAITING && $fresh->next_run_at !== null && $fresh->next_run_at->isFuture()) {
                return false;
            }
            $fresh->forceFill(['state' => Operation::RUNNING, 'started_at' => $fresh->started_at ?? now(), 'attempts' => $fresh->attempts + ($fresh->external_handle === null ? 1 : 0)])->save();
            $operation->setRawAttributes($fresh->getAttributes(), true);

            return true;
        });
    }

    private function context(Operation $operation): StepContext
    {
        $service = $operation->service_id ? Service::query()->find($operation->service_id) : null;
        $actor = new CommandContext($operation->actor_type, $operation->actor_id, $operation->organization_id, correlationId: $operation->correlation_id);

        return new StepContext($operation, $service, $this->providers, $this->container, $actor);
    }

    private function beginAttempt(Operation $operation, Step $step): OperationAttempt
    {
        return OperationAttempt::query()->create([
            'operation_id' => $operation->id, 'attempt' => $operation->attempts, 'step' => $operation->step, 'step_label' => $step->label(), 'started_at' => now(),
        ]);
    }

    private function finishAttempt(OperationAttempt $attempt, string $outcome, ?string $error = null): void
    {
        $attempt->forceFill(['finished_at' => now(), 'outcome' => $outcome, 'error' => $error === null ? null : mb_substr($this->redactor->redactString($error), 0, 500)])->save();
    }

    private function succeed(Operation $operation): string
    {
        $operation->forceFill(['state' => Operation::SUCCEEDED, 'finished_at' => now(), 'external_handle' => null, 'result' => $operation->context, 'error' => null])->save();
        $this->outbox->publish(GenericEvent::of('operation.succeeded', 'operation', $operation->id, ['kind' => $operation->kind, 'service_id' => $operation->service_id, 'order_item_id' => $operation->order_item_id, 'domain_id' => $operation->domain_id, 'result' => $this->redactor->redact($operation->context)], $operation->organization_id));

        return Operation::SUCCEEDED;
    }

    private function fail(Operation $operation, Workflow $workflow, StepContext $context, string $error, bool $retryable, array $detail): string
    {
        $operation->forceFill(['state' => Operation::FAILED, 'finished_at' => now(), 'error' => $this->errorPayload($error, $detail, $retryable)])->save();
        try {
            $workflow->compensate($context);
        } catch (Throwable $e) {
            $operation->forceFill(['error' => array_merge($operation->error ?? [], ['compensation_error' => $this->redactor->redactString($e->getMessage())])])->save();
        }
        $this->outbox->publish(GenericEvent::of('operation.failed', 'operation', $operation->id, ['kind' => $operation->kind, 'service_id' => $operation->service_id, 'order_item_id' => $operation->order_item_id, 'domain_id' => $operation->domain_id, 'step' => $operation->step, 'step_label' => $operation->step_label, 'error' => $operation->error], $operation->organization_id));

        return Operation::FAILED;
    }

    private function handleTimedOut(Operation $operation): bool
    {
        $handle = $operation->external_handle;
        if ($handle === null || $operation->started_at === null) {
            return false;
        }
        $timeout = (int) ($handle['timeout'] ?? 21600);
        // only the waits of the current run count: a manual retry starts the clock again (earlier runs may have waited for hours)
        $waitingSince = $operation->attempts()->where('outcome', 'wait')->where('started_at', '>=', $operation->started_at)->orderBy('started_at')->value('started_at');

        return $waitingSince !== null && now()->diffInSeconds(Carbon::parse($waitingSince), true) > $timeout;
    }

    /** @param array<string,mixed> $detail @return array<string,mixed> */
    private function errorPayload(?string $error, array $detail, bool $retryable): array
    {
        return ['message' => mb_substr($this->redactor->redactString((string) $error), 0, 500), 'retryable' => $retryable, 'detail' => $this->redactor->redact($detail), 'at' => now()->toISOString()];
    }
}
