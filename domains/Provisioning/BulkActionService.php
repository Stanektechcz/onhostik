<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Illuminate\Support\Collection;
use Onhost\Domain\Provisioning\Models\BulkJob;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Throwable;

/**
 * Bulk staff actions (audit §5e-7): a PHP-version rollout, a security baseline, a certificate re-issue or a backup
 * across every active service a filter selects — one job, one operation per service through the ordinary action
 * workflow (so every step is audited, retried and reported like a customer's own click), and a report staff can
 * follow. Only actions that are safe to repeat are allowed; anything that destroys data stays per service.
 */
final class BulkActionService
{
    public const ACTIONS = ['php.set', 'security.set', 'ssl.issue', 'https.force', 'index.set', 'proxies.set', 'backup', 'http3.set', 'php.settings', 'wp.update'];

    public const MAX_SERVICES = 500;

    public function __construct(private readonly ServiceService $services, private readonly AuditRecorder $audit) {}

    /**
     * @param  array{provider_instance_id?:string, node_id?:string, organization_id?:string, product_key?:string, family?:string, service_ids?:list<string>}  $filter
     * @param  array<string,mixed>  $params
     */
    public function start(array $filter, string $action, array $params, CommandContext $context, ?string $reason = null, ?string $authorizedPermission = null): BulkJob
    {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new DomainError('bulk_action_not_allowed', 'This action is not offered in bulk: '.implode(', ', self::ACTIONS).'.', 422, ['field' => 'action']);
        }
        $services = $this->select($filter);
        if ($services->isEmpty()) {
            throw new DomainError('bulk_filter_empty', 'The filter selects no active service.', 422, ['field' => 'filter']);
        }
        $job = BulkJob::query()->create(['action' => $action, 'params' => $params, 'filter' => $filter, 'state' => 'running', 'total' => $services->count(), 'items' => [], 'actor_id' => $context->actorId, 'reason' => $reason]);
        $items = [];
        $refused = 0;
        foreach ($services as $service) {
            try {
                $operation = $this->services->requestAction($service, $action, $context->withScope($service->organization_id, $service->project_id), "bulk:{$job->id}:{$service->id}", $params, authorizedPermission: $authorizedPermission, authorizedScope: $authorizedPermission === null ? null : 'global'); // the staff command was checked globally (H315)
                $items[] = ['service_id' => $service->id, 'organization_id' => $service->organization_id, 'label' => $service->label ?: $service->hostname ?: $service->name, 'operation_id' => $operation->id, 'error' => null];
            } catch (Throwable $e) {
                $refused++;
                $items[] = ['service_id' => $service->id, 'organization_id' => $service->organization_id, 'label' => $service->label ?: $service->hostname ?: $service->name, 'operation_id' => null, 'error' => $e instanceof DomainError ? $e->error : 'error', 'message' => mb_substr($e->getMessage(), 0, 300)];
            }
        }
        $job->forceFill(['items' => $items, 'refused' => $refused])->save();
        $this->audit->record($context, 'provisioning.bulk.start', 'succeeded', ['job' => $job->id, 'action' => $action, 'filter' => $filter, 'total' => $services->count(), 'refused' => $refused], 'bulk_job', $job->id);

        return $this->refresh($job);
    }

    /** Recounts the job from its operations (done, failed, running) and closes it once nothing is in flight. */
    public function refresh(BulkJob $job): BulkJob
    {
        $ids = array_values(array_filter(array_map(fn (array $i) => $i['operation_id'] ?? null, (array) $job->items)));
        $states = $ids === [] ? collect() : Operation::query()->whereIn('id', $ids)->get(['id', 'state', 'error'])->keyBy('id');
        $items = array_map(function (array $item) use ($states) {
            $op = $item['operation_id'] ? $states->get($item['operation_id']) : null;
            $item['state'] = $op?->state ?? ($item['operation_id'] ? 'UNKNOWN' : 'REFUSED');
            if ($op !== null && $op->state === Operation::FAILED) {
                $item['message'] = (string) ($op->error['message'] ?? 'failed');
            }

            return $item;
        }, (array) $job->items);
        $inFlight = count(array_filter($items, fn (array $i) => in_array($i['state'], [Operation::PENDING, Operation::RUNNING, Operation::WAITING], true)));
        $job->forceFill(['items' => $items, 'state' => $inFlight === 0 ? 'finished' : 'running', 'finished_at' => $inFlight === 0 ? ($job->finished_at ?? now()) : null])->save();

        return $job;
    }

    /** @return array<string,mixed> */
    public function present(BulkJob $job): array
    {
        $items = (array) $job->items;
        $count = fn (string $state) => count(array_filter($items, fn (array $i) => ($i['state'] ?? '') === $state));

        return [
            'id' => $job->id, 'action' => $job->action, 'params' => $job->params, 'filter' => $job->filter, 'state' => $job->state, 'reason' => $job->reason, 'actor_id' => $job->actor_id,
            'total' => $job->total, 'refused' => $job->refused, 'succeeded' => $count(Operation::SUCCEEDED), 'failed' => $count(Operation::FAILED), 'cancelled' => $count(Operation::CANCELLED),
            'running' => count(array_filter($items, fn (array $i) => in_array($i['state'] ?? '', [Operation::PENDING, Operation::RUNNING, Operation::WAITING], true))),
            'items' => $items, 'created_at' => $job->created_at?->toIso8601String(), 'finished_at' => $job->finished_at?->toIso8601String(),
        ];
    }

    /** @param  array<string,mixed>  $filter @return \Illuminate\Support\Collection<int, Service> */
    public function select(array $filter): Collection
    {
        $query = Service::query()->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->orderBy('created_at');
        if (! empty($filter['provider_instance_id']) && ProviderInstance::query()->whereKey((string) $filter['provider_instance_id'])->doesntExist()) {
            $filter['provider_instance_id'] = ProviderInstance::query()->where('key', (string) $filter['provider_instance_id'])->value('id') ?? $filter['provider_instance_id']; // the console names instances by key
        }
        $any = false;
        foreach (['provider_instance_id', 'node_id', 'organization_id', 'product_key', 'family'] as $key) {
            if (! empty($filter[$key])) {
                $query->where($key, (string) $filter[$key]);
                $any = true;
            }
        }
        if (! empty($filter['service_ids']) && is_array($filter['service_ids'])) {
            $query->whereIn('id', array_values(array_map('strval', $filter['service_ids'])));
            $any = true;
        }
        if (! $any) {
            throw new DomainError('bulk_filter_required', 'Name what the action applies to: an instance, a node, a customer, a product, a family or a list of services.', 422, ['field' => 'filter']);
        }

        return $query->limit(self::MAX_SERVICES)->get();
    }
}
