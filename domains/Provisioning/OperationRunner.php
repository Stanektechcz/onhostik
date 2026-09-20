<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Carbon\Carbon;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\OperationAttempt;
use Onhost\Domain\Provisioning\Workflow\Step;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Observability\Tracer;
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
        private readonly Tracer $tracer,
        private readonly Authorizer $authorizer,
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
            // a run started by a person asks again before each new privileged step (H315): a role revoked an hour ago must not
            // send the next mutation. A poll is not a mutation — a task already sent is followed to its end and stays on record.
            if ($operation->external_handle === null && ($refusal = $this->authorizationLost($operation)) !== null) {
                // the detail keys stay clear of the redactor's vocabulary (anything with "auth" is masked) so staff can read why the run stopped
                return $this->fail($operation, $workflow, $context, $refusal, false, ['access_revoked' => true, 'required_permission' => $operation->authorized_permission, 'step' => $step->label()]);
            }
            $attempt = $this->beginAttempt($operation, $step);
            Context::add('operation', $operation->id);
            try {
                $handle = $operation->external_handle;
                $result = $this->tracer->span('operation.step '.$step->label(), ['onhost.operation' => $operation->id, 'onhost.workflow' => class_basename((string) $operation->workflow), 'onhost.step' => $operation->step, 'onhost.service_id' => $operation->service_id, 'onhost.poll' => $handle !== null], fn () => $handle !== null
                    ? $step->poll($context, AsyncHandle::fromArray($handle))
                    : $step->run($context)); // one span per step (audit §5q-2)
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
                        // our deadline passing stops nothing at the panel (H327): the task may still finish there, so the
                        // handle stays on the row and a retry has to acknowledge it instead of silently starting a second copy
                        return $this->fail($operation, $workflow, $context, 'provider task exceeded its timeout; its final state at the provider is not confirmed', false, ['vendor_task_unconfirmed' => true, 'vendor_task' => $operation->external_handle]);
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

    /** Why the person who started this run may no longer continue it, or null when they may (system and staff-CLI runs have no user to revoke). */
    private function authorizationLost(Operation $operation): ?string
    {
        if ($operation->actor_type !== 'user' || $operation->actor_id === null || $operation->authorized_permission === null) {
            return null;
        }
        $user = User::query()->find($operation->actor_id);
        if ($user === null || ! $user->isActive()) {
            return 'the account that started this operation is no longer active; no further step was sent to the provider';
        }
        $scope = match (true) {
            $operation->authorized_scope === 'global' => CommandScope::global(), // a staff run: the role is held globally and was checked globally
            // with the project the service belongs to: a role held in that project covers it, and without it every multi-step run
            // started by a project member was stopped at its second step as "permission revoked"
            $operation->service_id !== null => CommandScope::resource((string) $operation->service_id, (string) $operation->organization_id, Service::query()->whereKey((string) $operation->service_id)->value('project_id')),
            default => CommandScope::organization((string) $operation->organization_id),
        };
        // queue workers live for hours and the authorizer keeps a principal's bindings for the life of its instance: without
        // dropping them here the answer would be the one from when the worker first met this user, not today's
        $this->authorizer->forget($user);
        if (! $this->authorizer->can($user, (string) $operation->authorized_permission, $scope)) {
            return "the permission {$operation->authorized_permission} was revoked while the operation was running; no further step was sent to the provider";
        }

        return null;
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
