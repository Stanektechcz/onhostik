<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Carbon\Carbon;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\IdentityCommandAuthorizer;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\OperationAttempt;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
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
        // a parked run is looked at when its next look is due, not at every re-dispatch of its queue job (RunOperation
        // re-dispatches a PENDING run within ten minutes whatever next_run_at says) — review round 2, HIGH-1
        if (self::parkedUntilLater($operation)) {
            return $operation->state;
        }
        // TASK-0045: an operation of a contained (or disabled) instance waits, whoever started it; it neither runs nor fails
        if (($contained = $this->containedInstance($operation)) !== null) {
            return $this->parkUnclaimed($operation, $contained);
        }
        if (! $this->claim($operation)) {
            return $operation->refresh()->state;
        }

        /** @var Workflow $workflow */
        $workflow = $this->container->make($operation->workflow);
        $context = $this->context($operation);
        $steps = $workflow->steps($operation);
        $deadline = microtime(true) + $budgetSeconds;
        $ranThisTick = false; // a tick that completed a step keeps the attempt the claim counted, even when a later step parks

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
            $refusalMark = $this->providers->refusalMark(); // did the registry refuse a contained panel during this step? (review MEDIUM-B)
            Context::add('operation', $operation->id);
            try {
                $handle = $operation->external_handle;
                $result = $this->tracer->span('operation.step '.$step->label(), ['onhost.operation' => $operation->id, 'onhost.workflow' => class_basename((string) $operation->workflow), 'onhost.step' => $operation->step, 'onhost.service_id' => $operation->service_id, 'onhost.poll' => $handle !== null], fn () => $handle !== null
                    ? $step->poll($context, AsyncHandle::fromArray($handle))
                    : $step->run($context)); // one span per step (audit §5q-2)
            } catch (InstanceContained $e) {
                // a step reached a contained instance (a second panel of a migration, say): the run is parked where it stands,
                // as if it had not been started — not a failed attempt, no compensation, nothing sent to that panel (TASK-0045)
                $this->finishAttempt($attempt, 'parked', $e->getMessage());

                $operation->refresh();

                return $this->park($operation, $e, $operation->external_handle === null && ! $ranThisTick);
            } catch (ProviderException $e) {
                $result = StepResult::fail($e->getMessage(), $e->isRetryable(), $e->toArray(), $e->retryAfterSeconds);
            } catch (Throwable $e) {
                $result = StepResult::fail(get_class($e).': '.$e->getMessage(), false, ['trace' => mb_substr($e->getTraceAsString(), 0, 1500)]);
            }
            $context = $this->context($operation->refresh()); // steps may have changed the service/bindings
            $refusal = $result->outcome === StepResult::FAIL ? $this->containmentCause($result, $refusalMark) : null;
            if ($refusal === null) {
                // the step got past the containment (or failed for a reason of its own): the park record closes only now, so a
                // run parked behind a contained SECOND panel keeps one record and its back-off while its parks repeat (round 2)
                $this->unpark($operation);
            }

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
                    $ranThisTick = true;

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
                    // a step that turned the refusal into its own failure — with the refusal's detail (`fail(…, $e->extra)`), or its
                    // message (`catch (DomainError|Throwable)` → `fail('…: '.$e->getMessage())`): parked all the same. A contained panel
                    // is a pause the owner chose, never a reason to fail and compensate (TASK-0045, review B; cause rule: round 2)
                    if ($refusal !== null) {
                        $this->finishAttempt($attempt, 'parked', $result->error);

                        return $this->park($operation, $refusal, $operation->external_handle === null && ! $ranThisTick, (bool) ($result->detail['rerun_step'] ?? false));
                    }
                    $swallowed = $this->providers->refusedSince($refusalMark); // refused on the way, but not why the step failed
                    $this->finishAttempt($attempt, $result->retryable ? 'retry' : 'fail', $result->error.($swallowed === null ? '' : ' · a contained panel was refused during this step ('.$swallowed->instanceKey.': '.$swallowed->instanceState.')'));
                    $policy = RetryPolicy::provisioning();
                    // the time the run spent parked behind a containment is not its own (review MEDIUM-F)
                    $elapsed = $operation->queued_at ? max(0, now()->diffInSeconds($operation->queued_at, true) - self::parkedSeconds($operation)) : 0;
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

    // ── TASK-0045: a contained panel stays contained ──
    /**
     * How long a parked operation waits before it asks again whether its instance is still contained: 15 minutes, doubled
     * with every further park of the same containment, never more than PARK_MAX_MINUTES (review MEDIUM-F: a permanently
     * disabled instance was asked every few minutes for ever). The doctor names runs parked for more than a week.
     */
    public const PARK_MINUTES = 15;

    public const PARK_MAX_MINUTES = 360;

    /** The refusal the registry would give this operation's own instance, or null when it may run. */
    private function containedInstance(Operation $operation): ?InstanceContained
    {
        $service = $operation->service_id !== null ? Service::query()->find($operation->service_id) : null;
        // the same instance the steps ask for first (StepContext::instance())
        $id = data_get($operation->context, 'provider_instance_id') ?? data_get($operation->desired, 'provider_instance_id') ?? $operation->provider_instance_id ?? $service?->provider_instance_id;
        $instance = $id === null ? null : ProviderInstance::query()->find($id);
        if ($instance === null) {
            return null;
        }
        try {
            $this->providers->refuseContained($instance);
        } catch (InstanceContained $e) {
            return $e;
        }

        return null;
    }

    /**
     * Parks a run this worker has NOT claimed (review MEDIUM-E): under the row lock, and only while it is PENDING or WAITING —
     * a run another worker holds (RUNNING) is left alone, and one that ended meanwhile (CANCELLED, SUCCEEDED) is not rewritten.
     */
    private function parkUnclaimed(Operation $operation, InstanceContained $why): string
    {
        return DB::transaction(function () use ($operation, $why) {
            $fresh = Operation::query()->lockForUpdate()->findOrFail($operation->id);
            if (! in_array($fresh->state, [Operation::PENDING, Operation::WAITING], true)) {
                return (string) $fresh->state;
            }

            return $this->park($fresh, $why);
        });
    }

    /**
     * Leaves the operation in the state it can be run from again (PENDING, or WAITING on the task a panel already has) and
     * looks again later (see PARK_MINUTES). `$uncount` gives back the attempt the claim counted: a parked run tried nothing.
     * `$restartStep`: the step asked to start again after the lift (its handle names work that never ran — the game transfer).
     * `context._parked` keeps `since` (this containment), `count` (parks in it) and `seconds` (all earlier ones, closed by unpark()).
     */
    private function park(Operation $operation, InstanceContained $why, bool $uncount = false, bool $restartStep = false): string
    {
        $parked = (array) data_get($operation->context, '_parked', []);
        $count = (int) ($parked['count'] ?? 0) + 1;
        $wait = min(self::PARK_MAX_MINUTES, self::PARK_MINUTES * 2 ** min(10, $count - 1));
        $handle = $restartStep ? null : $operation->external_handle;
        $state = $handle === null ? Operation::PENDING : Operation::WAITING;
        $operation->withContext(['_parked' => ['since' => $parked['since'] ?? now()->toIso8601String(), 'count' => $count, 'seconds' => (int) ($parked['seconds'] ?? 0)]])->forceFill([
            'state' => $state, 'external_handle' => $handle, 'next_run_at' => now()->addMinutes($wait),
            'attempts' => $uncount ? max(0, $operation->attempts - 1) : $operation->attempts,
            'error' => $this->errorPayload($why->getMessage(), ['contained' => true, 'instance' => $why->instanceKey !== '' ? $why->instanceKey : null, 'instance_state' => $why->instanceState, 'next_look_minutes' => $wait], true),
        ])->save();

        return $state;
    }

    /**
     * The refusal a failed step failed BECAUSE of, or null (review round 2, MEDIUM a): the step handed back the refusal's
     * detail, or its failure carries the refusal's own message (how every swallowing `catch` words it). A refusal the step
     * swallowed on the way — a best-effort cleanup on another panel — before it failed for a reason of its own is not the
     * cause: that run fails and compensates as it always did, and the attempt names the refusal.
     */
    private function containmentCause(StepResult $result, int $refusalMark): ?InstanceContained
    {
        $marked = InstanceContained::fromDetail($result->detail);
        if ($marked !== null) {
            return $marked;
        }
        $seen = $this->providers->refusedSince($refusalMark);

        return $seen !== null && str_contains((string) $result->error, $seen->getMessage()) ? $seen : null;
    }

    /** A parked run whose next look is not due yet: nothing is asked, nothing is counted (review round 2, HIGH-1). */
    private static function parkedUntilLater(Operation $operation): bool
    {
        return in_array($operation->state, [Operation::PENDING, Operation::WAITING], true) && ! empty(data_get($operation->context, '_parked.since'))
            && $operation->next_run_at !== null && $operation->next_run_at->isFuture();
    }

    /** The containment is over for this run: its length goes to `_parked.seconds`, which the timeouts leave out. */
    private function unpark(Operation $operation): void
    {
        $parked = (array) data_get($operation->context, '_parked', []);
        if (empty($parked['since'])) {
            return;
        }
        $seconds = (int) ($parked['seconds'] ?? 0) + (int) Carbon::parse((string) $parked['since'])->diffInSeconds(now(), true);
        $operation->withContext(['_parked' => ['since' => null, 'count' => 0, 'seconds' => $seconds]])->save();
    }

    /** Seconds this run spent parked behind a containment, the current one included. */
    private static function parkedSeconds(Operation $operation): int
    {
        $parked = (array) data_get($operation->context, '_parked', []);
        $open = empty($parked['since']) ? 0 : (int) Carbon::parse((string) $parked['since'])->diffInSeconds(now(), true);

        return (int) ($parked['seconds'] ?? 0) + $open;
    }
    // ── end TASK-0045 ──

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
            if (self::parkedUntilLater($fresh)) { // parked PENDING runs honour their next look too (round 2, HIGH-1)
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
        // TASK-0039 review round 1 (program §3 "staffMode persisted"): a run started in staff mode acts in it — OperationService::start
        // writes the flag from the starting context only, and StaffActor still asks whether the person is staff and active today
        $actor = new CommandContext($operation->actor_type, $operation->actor_id, $operation->organization_id, sessionId: self::tokenSession($operation), correlationId: $operation->correlation_id, staffMode: data_get($operation->desired, 'staff_mode') === true);

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
        OperationSecrets::onFinished($operation); // it has acted: the passwords it was given are no longer its business
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
        // ── TASK-0039 review round 2 (P0-09 "including queued operations") ──
        // a run started with an API token is asked on the token's view, as the bus asked when it started (one organization, no
        // global reach); the person's full view let the run go on after the token was revoked or had expired
        if (($session = self::tokenSession($operation)) !== null) {
            $user = IdentityCommandAuthorizer::asToken($user, new CommandContext('user', (string) $operation->actor_id, $operation->organization_id, sessionId: $session));
            if (! $user instanceof User) {
                return 'the API token that started this operation was revoked or has expired; no further step was sent to the provider';
            }
        }
        // ── end TASK-0039 ──
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

    // ── TASK-0039 review round 2 ──
    /** `token:<id>` when the run was started with an API token (OperationService::start writes `desired.token_id`), else null. */
    private static function tokenSession(Operation $operation): ?string
    {
        $tokenId = data_get($operation->desired, 'token_id');

        return $tokenId === null ? null : 'token:'.(is_scalar($tokenId) ? (string) $tokenId : ''); // anything unreadable decides nothing
    }
    // ── end TASK-0039 ──

    private function handleTimedOut(Operation $operation): bool
    {
        $handle = $operation->external_handle;
        if ($handle === null || $operation->started_at === null) {
            return false;
        }
        $timeout = (int) ($handle['timeout'] ?? 21600);
        // only the waits of the current run count: a manual retry starts the clock again (earlier runs may have waited for hours)
        $waitingSince = $operation->attempts()->where('outcome', 'wait')->where('started_at', '>=', $operation->started_at)->orderBy('started_at')->value('started_at');

        // a containment is not the task's time (review MEDIUM-F): the panel was not asked while the run was parked. Parked time
        // before this wait began is subtracted too — that errs on the side of waiting a little longer, never of failing early
        return $waitingSince !== null && now()->diffInSeconds(Carbon::parse($waitingSince), true) - self::parkedSeconds($operation) > $timeout;
    }

    /** @param array<string,mixed> $detail @return array<string,mixed> */
    private function errorPayload(?string $error, array $detail, bool $retryable): array
    {
        return ['message' => mb_substr($this->redactor->redactString((string) $error), 0, 500), 'retryable' => $retryable, 'detail' => $this->redactor->redact($detail), 'at' => now()->toISOString()];
    }
}
