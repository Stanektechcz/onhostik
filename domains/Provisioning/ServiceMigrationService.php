<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use App\Http\Presenters\Presenters;
use Carbon\CarbonImmutable;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflows\GameMigrationWorkflow;
use Onhost\Domain\Provisioning\Workflows\VpsMigrationWorkflow;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Service migrations between nodes as one entry point (audit §5g-2, §5h-2/3, §5i): every family with a saga is
 * moved the same way — staff name a target (or the scheduler picks one), optionally inside a window the customer may
 * move; a node is evacuated server by server; the schedule and the outcome live in `tags.migration` so the panel
 * shows them. Game servers move by backup + archive (GameMigrationWorkflow, also across panels); VPS by the cluster's
 * live migration (VpsMigrationWorkflow); families without a saga are refused with a clear reason.
 */
final class ServiceMigrationService
{
    public const WORKFLOWS = ['game' => GameMigrationWorkflow::class, 'cloud' => VpsMigrationWorkflow::class];

    public const EVACUATE_LIMIT = 50;

    public const MAX_WINDOW_DAYS = 30;

    public function __construct(private readonly OperationService $operations, private readonly OperationsBoard $board, private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox) {}

    public static function supports(Service $service): bool
    {
        return isset(self::WORKFLOWS[$service->family]);
    }

    /** The schedule / outcome of a migration as the customer sees it (`tags.migration`), with the live operation state. */
    public static function status(Service $service): ?array
    {
        $tag = data_get($service->tags, 'migration');
        if (! is_array($tag) || $tag === []) {
            return null;
        }
        $operation = ! empty($tag['operation_id']) ? Operation::query()->find((string) $tag['operation_id']) : null;

        return $tag + ['operation_state' => $operation?->state, 'step_label' => $operation?->step_label, 'state' => $tag['state'] ?? ($operation === null ? 'scheduled' : match ($operation->state) {
            Operation::SUCCEEDED => 'finished', Operation::FAILED => 'failed', Operation::PENDING => (int) $operation->step > 0 ? 'running' : 'scheduled', Operation::CANCELLED => 'cancelled', default => 'running',
        })];
    }

    /** @param  array<string,mixed>  $extra */
    public static function markSchedule(Service $service, string $state, array $extra = []): void
    {
        $tags = (array) $service->tags;
        $tags['migration'] = array_replace((array) ($tags['migration'] ?? []), ['state' => $state, $state.'_at' => now()->toIso8601String()], $extra);
        $service->forceFill(['tags' => $tags])->save();
    }

    /** `$collaboratorPolicy`: `strict` stops a migration that cannot carry a collaborator with the same permissions, `drop` moves without them (H341). */
    public function start(Service $service, ?string $targetNodeId, ?string $reason, CommandContext $context, ?CarbonImmutable $windowFrom = null, ?CarbonImmutable $windowTo = null, string $collaboratorPolicy = 'strict'): Operation
    {
        $workflow = self::WORKFLOWS[$service->family] ?? null;
        if ($workflow === null) {
            throw new DomainError('migration_unsupported', "Services of the {$service->family} family move by a backup and a new order for now; game servers and VPS migrate as a saga.", 422, ['family' => $service->family]);
        }
        if (! in_array($service->state, [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED], true)) {
            throw new DomainError('service_state_invalid', "A migration is not allowed while the service is {$service->state}.", 409, ['state' => $service->state]);
        }
        if ($service->primaryBinding() === null || $service->provider_instance_id === null) {
            throw new DomainError('service_not_provisioned', 'The service has no resource on a node yet.', 409);
        }
        if (Operation::query()->where('service_id', $service->id)->whereIn('state', [Operation::PENDING, Operation::RUNNING, Operation::WAITING])->exists()) {
            throw new DomainError('operation_in_progress', 'Another operation is still running on this service; wait for it to finish.', 409);
        }
        $targetNodeId = $targetNodeId !== null && trim($targetNodeId) !== '' ? trim($targetNodeId) : null;
        if ($targetNodeId !== null && Node::query()->where(fn ($q) => $q->whereKey($targetNodeId)->orWhere('name', $targetNodeId))->doesntExist()) {
            throw new DomainError('node_unknown', "Target node {$targetNodeId} does not exist.", 422, ['field' => 'target_node_id']);
        }
        $window = $windowFrom !== null || $windowTo !== null;
        if ($window) {
            $windowFrom ??= CarbonImmutable::now();
            $windowTo ??= $windowFrom->addDay();
            if ($windowTo->lessThanOrEqualTo($windowFrom)) {
                throw new DomainError('migration_window_invalid', 'The window must end after it starts.', 422, ['field' => 'window_to']);
            }
            if ($windowFrom->diffInDays($windowTo, true) > self::MAX_WINDOW_DAYS) {
                throw new DomainError('migration_window_invalid', 'The window may span at most '.self::MAX_WINDOW_DAYS.' days.', 422, ['field' => 'window_to']);
            }
        }
        $scoped = $context->withScope($service->organization_id, $service->project_id);
        $operation = $this->operations->start($workflow, "smig:{$service->id}:".now()->format('YmdHis.u'), ['target_node_id' => $targetNodeId, 'reason' => $reason, 'collaborator_policy' => $collaboratorPolicy === 'drop' ? 'drop' : 'strict', 'window' => $window ? ['from' => $windowFrom->toIso8601String(), 'to' => $windowTo->toIso8601String()] : null], $scoped, $service->id, $service->organization_id, null, $service->provider_instance_id, null, ! $window);
        $schedule = ['operation_id' => $operation->id, 'state' => $window ? 'scheduled' : 'running', 'target' => $targetNodeId, 'reason' => $reason, 'kind' => $workflow::kind()];
        if ($window) {
            $startsAt = $windowFrom->isPast() ? CarbonImmutable::now() : $windowFrom;
            $operation->forceFill(['next_run_at' => $startsAt])->save();
            $schedule += ['from' => $windowFrom->toIso8601String(), 'to' => $windowTo->toIso8601String(), 'starts_at' => $startsAt->toIso8601String(), 'chosen_at' => null];
            $this->outbox->publish(GenericEvent::of('service.migration.scheduled', 'service', $service->id, ['label' => $service->label ?: $service->name, 'from' => $schedule['from'], 'to' => $schedule['to'], 'starts_at' => $schedule['starts_at'], 'reason' => $reason], $service->organization_id));
        }
        $service->forceFill(['tags' => array_replace((array) $service->tags, ['migration' => $schedule])])->save();
        $this->audit->record($scoped, 'service.migration.start', 'succeeded', ['target_node_id' => $targetNodeId, 'reason' => $reason, 'operation_id' => $operation->id, 'window' => $window ? [$windowFrom->toIso8601String(), $windowTo->toIso8601String()] : null], 'service', $service->id);

        return $operation;
    }

    /**
     * The customer moves the start inside the window staff gave (audit §5h-3); the operation simply waits for the new time.
     *
     * @return array<string,mixed> the schedule as the panel shows it
     */
    public function reschedule(Service $service, CarbonImmutable $startsAt, CommandContext $context): array
    {
        $schedule = (array) data_get($service->tags, 'migration', []);
        $operation = ! empty($schedule['operation_id']) ? Operation::query()->find((string) $schedule['operation_id']) : null;
        if ($operation === null || $operation->state !== Operation::PENDING || (int) $operation->step > 0 || empty($schedule['from'])) {
            throw new DomainError('migration_not_scheduled', 'No migration is waiting for a start time on this service.', 409);
        }
        $from = CarbonImmutable::parse((string) $schedule['from']);
        $to = CarbonImmutable::parse((string) $schedule['to']);
        if ($startsAt->lessThan($from) || $startsAt->greaterThan($to)) {
            throw new DomainError('migration_window_out_of_range', 'The start must lie within the window '.$from->toIso8601String().' – '.$to->toIso8601String().'.', 422, ['field' => 'starts_at', 'from' => $from->toIso8601String(), 'to' => $to->toIso8601String()]);
        }
        $startsAt = $startsAt->isPast() ? CarbonImmutable::now() : $startsAt;
        $operation->forceFill(['next_run_at' => $startsAt])->save();
        $schedule['starts_at'] = $startsAt->toIso8601String();
        $schedule['chosen_at'] = now()->toIso8601String();
        $service->forceFill(['tags' => array_replace((array) $service->tags, ['migration' => $schedule])])->save();
        $scoped = $context->withScope($service->organization_id, $service->project_id);
        $this->audit->record($scoped, 'service.migration.reschedule', 'succeeded', ['starts_at' => $schedule['starts_at'], 'operation_id' => $operation->id], 'service', $service->id);
        $this->outbox->publish(GenericEvent::of('service.migration.rescheduled', 'service', $service->id, ['label' => $service->label ?: $service->name, 'starts_at' => $schedule['starts_at']], $service->organization_id));

        return $schedule;
    }

    /**
     * Every active service of the node with a migration saga gets its own migration; the node is drained (and kept drained) first.
     *
     * @return array{node:string, drained:bool, started:list<array<string,mixed>>, skipped:list<array{service_id:string,label:string,error:string}>}
     */
    public function evacuate(Node $node, ?string $targetNodeId, ?string $reason, CommandContext $context, ?CarbonImmutable $windowFrom = null, ?CarbonImmutable $windowTo = null): array
    {
        $drained = false;
        if ($node->state === 'active') {
            $this->board->setState($node, 'draining', $reason ?? 'evacuation', $context, false, [], true);
            $drained = true;
        }
        $out = ['node' => $node->name, 'drained' => $drained, 'started' => [], 'skipped' => [], 'staying' => 0];
        // everything living on the node, not only the families with a saga of their own: a web hosting that cannot
        // be moved was not moved, not refused and not even listed, so the operator read `skipped: 0` and believed the
        // node was empty before they touched the hardware
        $services = Service::query()->where('node_id', $node->id)->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->orderBy('created_at')->limit(self::EVACUATE_LIMIT)->get();
        foreach ($services as $service) {
            try {
                $out['started'][] = Presenters::operation($this->start($service, $targetNodeId, $reason, $context, $windowFrom, $windowTo), true) + ['label' => $service->label ?: $service->name];
            } catch (DomainError $e) {
                $out['skipped'][] = ['service_id' => $service->id, 'label' => $service->label ?: $service->name, 'family' => $service->family, 'error' => $e->error, 'reason' => $e->getMessage()];
                $out['staying']++;
            }
        }
        $this->audit->record($context, 'node.evacuate', 'succeeded', ['node' => $node->name, 'target_node_id' => $targetNodeId, 'started' => count($out['started']), 'staying' => $out['staying'], 'skipped' => count($out['skipped'])], 'node', $node->id);

        return $out;
    }
}
