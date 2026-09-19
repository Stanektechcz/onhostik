<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Presenters\Presenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\BulkActionService;
use Onhost\Domain\Provisioning\CapacityBudget;
use Onhost\Domain\Provisioning\CapacityForecast;
use Onhost\Domain\Provisioning\CapacityPlanner;
use Onhost\Domain\Provisioning\Commands\CapacityCommand;
use Onhost\Domain\Provisioning\Commands\ProvisioningCommand;
use Onhost\Domain\Provisioning\FreezeSwitch;
use Onhost\Domain\Provisioning\IntegrationHealthProbe;
use Onhost\Domain\Provisioning\Models\BulkJob;
use Onhost\Domain\Provisioning\Models\CapacityRequest;
use Onhost\Domain\Provisioning\Models\IntegrationHealth;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\OperationAttempt;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Domain\Provisioning\Models\ResourceDrift;
use Onhost\Domain\Provisioning\OperationsBoard;
use Onhost\Domain\Provisioning\PlacementService;
use Onhost\Domain\Provisioning\ProviderInstanceService;
use Onhost\Domain\Provisioning\Scheduling\NodeRebalancer;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\ControlPlaneStatus;
use Onhost\Domain\Services\DeletionPolicy;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\SshKeyGrant;
use Onhost\Domain\Services\SshKeyLedger;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/** Integrations & provisioning surface (admin `#/sluzby`, `#/uzly`): operations, drift, provider health, capacity, freeze switch. */
final class ProvisioningController extends ApiController
{
    public function operations(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'provisioning.operation.read', CommandScope::global());
        $query = Operation::query();
        foreach (['state', 'kind', 'organization_id', 'service_id', 'provider_instance_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, (string) $request->query($filter));
            }
        }

        return $this->api->paginate($request, $query, fn (Operation $o) => Presenters::operation($o, true), 'queued_at');
    }

    /** The operations board (audit §5e-3): stalled, failed and long-running operations across tenants, and every node with its recent record. */
    public function board(Request $request, OperationsBoard $board): JsonResponse
    {
        $this->api->authorize($request, 'provisioning.operation.read', CommandScope::global());

        return response()->json(['data' => $board->board()]);
    }

    /** Bulk staff actions (audit §5e-7): list, start, follow. */
    public function bulkJobs(Request $request, BulkActionService $bulk): JsonResponse
    {
        $this->api->authorize($request, 'provisioning.operation.read', CommandScope::global());

        return $this->api->paginate($request, BulkJob::query(), fn (BulkJob $job) => $bulk->present($bulk->refresh($job)), 'created_at');
    }

    public function bulkJob(Request $request, BulkActionService $bulk, string $job): JsonResponse
    {
        $this->api->authorize($request, 'provisioning.operation.read', CommandScope::global());
        $model = BulkJob::query()->find($job);
        if ($model === null) {
            throw DomainError::notFound('bulk_job');
        }

        return response()->json(['data' => $bulk->present($bulk->refresh($model))]);
    }

    public function startBulkJob(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'in:'.implode(',', BulkActionService::ACTIONS)], 'params' => ['nullable', 'array'], 'reason' => ['nullable', 'string', 'max:250'],
            'filter' => ['required', 'array'], 'filter.provider_instance_id' => ['nullable', 'string', 'max:40'], 'filter.node_id' => ['nullable', 'string', 'max:40'], 'filter.organization_id' => ['nullable', 'string', 'max:40'],
            'filter.product_key' => ['nullable', 'string', 'max:60'], 'filter.family' => ['nullable', 'string', 'max:20'], 'filter.service_ids' => ['nullable', 'array', 'max:500'], 'filter.service_ids.*' => ['string', 'max:40'],
        ]);

        return $this->dispatch(new ProvisioningCommand($this->idempotencyKey($request, 'bulk.start'), ['op' => 'bulk.start', 'filter' => $data['filter'], 'action' => $data['action'], 'params' => (array) ($data['params'] ?? []), 'reason' => $data['reason'] ?? null]), $this->api->context($request, null, $data['reason'] ?? null), 201);
    }

    /** The rebalancing plan (audit §5i): nodes with their sold load and the moves that would even it out. */
    public function rebalancePlan(Request $request, NodeRebalancer $rebalancer): JsonResponse
    {
        $this->api->authorize($request, 'provisioning.operation.read', CommandScope::global());
        $role = (string) $request->query('role', '');

        return response()->json(['data' => $rebalancer->plan($role !== '' ? $role : null, (string) $request->query('basis', 'sold'))]); // basis=usage: the measured load instead of the sold RAM (audit §5j-4)
    }

    /** Applies the plan (all moves or the named services) as migrations, usually inside a window the customers may move. */
    public function rebalanceApply(Request $request): JsonResponse
    {
        $data = $request->validate(['service_ids' => ['nullable', 'array', 'max:200'], 'service_ids.*' => ['string', 'max:40'], 'reason' => ['nullable', 'string', 'max:250'], 'window_from' => ['nullable', 'date'], 'window_to' => ['nullable', 'date', 'after:window_from']]);

        return $this->dispatch(new ProvisioningCommand($this->idempotencyKey($request, 'rebalance.apply:'.now()->format('YmdHi')), ['op' => 'rebalance.apply', 'service_ids' => (array) ($data['service_ids'] ?? []), 'reason' => $data['reason'] ?? null, 'window_from' => $data['window_from'] ?? null, 'window_to' => $data['window_to'] ?? null]), $this->api->context($request, null, $data['reason'] ?? null), 202);
    }

    /** Node prerequisites (audit §5e-8): check what the instance can deliver and record it on the instance. */
    public function prerequisites(Request $request, string $instance): JsonResponse
    {
        return $this->dispatch(new ProvisioningCommand("instance.prereqs:{$instance}:".now()->format('U.u'), ['op' => 'instance.prereqs', 'instance_key' => $instance]), $this->api->context($request)); // a fresh check every time, never a replay of the last one
    }

    /** Drain, resume, park or disable one node of an instance. */
    public function nodeState(Request $request, string $instance, string $node): JsonResponse
    {
        $data = $request->validate(['state' => ['required', 'in:active,draining,maintenance,disabled'], 'reason' => ['nullable', 'string', 'max:250'], 'keep' => ['nullable', 'boolean']]);

        return $this->dispatch(new ProvisioningCommand($this->idempotencyKey($request, "node.state:{$node}:{$data['state']}:".now()->format('YmdHi')), ['op' => 'node.state', 'instance_key' => $instance, 'node_id' => $node, 'state' => $data['state'], 'reason' => $data['reason'] ?? null, 'keep' => (bool) ($data['keep'] ?? false)]), $this->api->context($request, null, $data['reason'] ?? null));
    }

    public function operation(Request $request, string $operation): JsonResponse
    {
        $this->api->authorize($request, 'provisioning.operation.read', CommandScope::global());
        $model = Operation::query()->find($operation);
        if ($model === null) {
            throw DomainError::notFound('operation');
        }
        $attempts = OperationAttempt::query()->where('operation_id', $model->id)->orderBy('created_at')->get()->map(fn (OperationAttempt $a) => $a->toArray())->all();
        $calls = DB::table('provider_calls')->where('operation_id', $model->id)->orderBy('created_at')->limit(200)->get(['id', 'provider', 'instance_key', 'action', 'method', 'path', 'http_status', 'ok', 'duration_ms', 'error', 'created_at'])->all();

        return response()->json(['data' => Presenters::operation($model, true) + ['attempts' => $attempts, 'provider_calls' => $calls]]);
    }

    public function retry(Request $request, string $operation): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:250'], 'acknowledge_vendor_task' => ['nullable', 'boolean']]); // H327: a timed-out provider task has to be looked at before the workflow starts over

        return $this->dispatch(new ProvisioningCommand("op.retry:{$operation}:".now()->timestamp, ['op' => 'retry', 'operation_id' => $operation] + $data), $this->api->context($request, null, $data['reason'] ?? null), 202);
    }

    public function cancel(Request $request, string $operation): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:250']]);

        return $this->dispatch(new ProvisioningCommand("op.cancel:{$operation}", ['op' => 'cancel', 'operation_id' => $operation] + $data), $this->api->context($request, null, $data['reason']));
    }

    public function drifts(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'provisioning.operation.read', CommandScope::global());
        $query = ResourceDrift::query();
        if ($request->boolean('open', true)) {
            $query->where('state', 'open');
        }
        if ($request->filled('classification')) {
            $query->where('classification', strtoupper((string) $request->query('classification')));
        }

        return $this->api->paginate($request, $query, fn (ResourceDrift $d) => Presenters::drift($d), 'detected_at');
    }

    public function resolveDrift(Request $request, string $drift): JsonResponse
    {
        $data = $request->validate(['resolution' => ['required', 'in:approved,ignored,repair'], 'note' => ['required', 'string', 'min:5', 'max:250']]);

        return $this->dispatch(new ProvisioningCommand("drift:{$drift}:".$data['resolution'], ['op' => 'resolve_drift', 'drift_id' => $drift] + $data), $this->api->context($request, null, $data['note']));
    }

    /**
     * The deletion lifecycle board (audit §5ab): services waiting out their restore window, the archives we hold and
     * how long each of them still lives. Nothing here deletes anything — the removal runs on its own schedule.
     */
    /** SSH key revocations a panel has not taken yet (H185): until one is confirmed the key may still open a session. */
    public function sshKeyRevocations(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'service.read', CommandScope::global());
        $open = SshKeyGrant::query()->where('state', SshKeyGrant::REVOKING)->orderBy('revoke_requested_at')->limit(200)->get();
        $services = Service::query()->withTrashed()->whereIn('id', $open->pluck('service_id')->unique()->all())->get()->keyBy('id');
        $organizations = Organization::query()->whereIn('id', $open->pluck('organization_id')->unique()->all())->pluck('name', 'id');

        return $this->ok(['open' => $open->count(), 'rows' => $open->map(fn (SshKeyGrant $g) => SshKeyLedger::present($g, null, []) + [
            'organization' => $organizations[$g->organization_id] ?? null,
            'service' => ($s = $services->get($g->service_id)) === null ? null : ['label' => $s->label ?: ($s->hostname ?: $s->name), 'state' => $s->state, 'control_plane' => ControlPlaneStatus::of($s)],
            'stuck' => $g->revoke_attempts >= SshKeyLedger::STUCK_AFTER_ATTEMPTS,
        ])->values()->all()]);
    }

    public function deletions(Request $request, DeletionPolicy $policy): JsonResponse
    {
        $this->api->authorize($request, 'service.read', CommandScope::global());
        $pending = Service::query()->withTrashed()->whereNotNull('terminate_at')->whereIn('state', [ServiceStateMachine::SUSPENDED, ServiceStateMachine::FAILED])
            ->orderBy('terminate_at')->limit(200)->get();
        $archives = Backup::query()->where('kind', 'final')->whereIn('state', ['completed', 'failed'])->orderByDesc('created_at')->limit(200)->get();
        $organizations = Organization::query()->whereIn('id', $pending->pluck('organization_id')->merge($archives->pluck('organization_id'))->unique()->all())->pluck('name', 'id');

        return $this->ok([
            'policy' => $policy->all(),
            'pending' => $pending->map(fn (Service $s) => [
                'id' => $s->id, 'name' => $s->name, 'label' => $s->label ?: $s->hostname, 'family' => $s->family, 'state' => $s->state,
                'organization' => $organizations[$s->organization_id] ?? $s->organization_id,
                'grace_until' => $s->terminate_at?->toIso8601String(), 'days_left' => $s->terminate_at === null ? null : (int) now()->diffInDays($s->terminate_at, false),
                'archive_backup_id' => data_get($s->tags, 'deletion.archive_backup_id'), 'reason' => data_get($s->tags, 'deletion.reason'),
                'due' => $s->terminate_at !== null && $s->terminate_at->isPast(),
            ])->values()->all(),
            'archives' => $archives->map(fn (Backup $b) => [
                'id' => $b->id, 'service_id' => $b->service_id, 'organization' => $organizations[$b->organization_id] ?? $b->organization_id,
                'state' => $b->state, 'size_bytes' => (int) $b->size_bytes, 'created_at' => $b->created_at?->toIso8601String(), 'retention_until' => $b->retention_until?->toIso8601String(),
                'parts' => (array) data_get($b->meta, 'parts', []), 'gaps' => (array) data_get($b->meta, 'gaps', []), 'attempts' => (array) data_get($b->meta, 'attempts', []),
                'error' => data_get($b->meta, 'error'), 'identity_matched' => data_get($b->meta, 'identity.matched'), 'paid' => (bool) data_get($b->meta, 'download.paid', false),
            ])->values()->all(),
        ]);
    }

    /** Remove a service now instead of waiting out its restore window — with a reason, and only after the archive. */
    public function purgeService(Request $request, string $service): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:250']]);
        $model = Service::query()->withTrashed()->findOrFail($service);

        return $this->dispatch(new ServiceActionCommand($model->organization_id, $this->idempotencyKey($request, 'staff.purge:'.$service), [
            'service_id' => $model->id, 'action' => 'purge', 'params' => ['force' => true, 'reason' => $data['reason']],
        ]), $this->api->context($request, null, $data['reason']), 202);
    }

    public function integrations(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'provider.instance.read', CommandScope::global());
        $health = IntegrationHealth::query()->get()->keyBy('provider_instance_id');
        $instances = ProviderInstance::query()->platform()->orderBy('provider')->orderBy('key')->get()->map(function (ProviderInstance $i) use ($health) {
            $h = $health->get($i->id);

            return Presenters::providerInstance($i) + ['integration' => $h ? ['up' => (bool) $h->up, 'last_success_at' => $h->last_success_at?->toIso8601String(), 'last_failure_at' => $h->last_failure_at?->toIso8601String(), 'error_rate_1h' => $h->error_rate_1h, 'p95_ms' => $h->p95_ms, 'calls_24h' => $h->calls_24h, 'errors_24h' => $h->errors_24h, 'budget_used_pct' => $h->budget_used_pct, 'circuit_state' => $h->circuit_state, 'last_error' => $h->last_error, 'checked_at' => $h->checked_at?->toIso8601String()] : null];
        })->all();

        return response()->json(['data' => $instances, 'frozen' => app(FreezeSwitch::class)->meta()]);
    }

    public function probe(Request $request, IntegrationHealthProbe $probe): JsonResponse
    {
        $this->api->authorize($request, 'provider.instance.read', CommandScope::global());

        return response()->json(['data' => $probe->run()]);
    }

    public function capacity(Request $request, NodeScheduler $scheduler): JsonResponse
    {
        $this->api->authorize($request, 'capacity.read', CommandScope::global());
        $nodes = Node::query()->with('providerInstance')->orderBy('region_code')->orderBy('role')->get()->map(fn (Node $n) => ['id' => $n->id, 'name' => $n->name, 'instance' => $n->providerInstance?->key, 'region' => $n->region_code, 'role' => $n->role, 'state' => $n->state, 'capacity' => $n->capacity, 'usage' => $n->usage, 'failure_domain' => $n->failure_domain, 'last_seen_at' => $n->last_seen_at?->toIso8601String()])->all();
        $pools = [];
        foreach (['compute', 'web', 'managed', 'game', 'mail'] as $role) {
            $pools[$role] = $scheduler->sellableCapacity($role);
        }

        return response()->json(['data' => ['nodes' => $nodes, 'sellable' => $pools, 'forecast' => app(CapacityForecast::class)->forecast(), 'requests' => CapacityRequest::query()->whereIn('state', CapacityRequest::OPEN)->orderBy('created_at')->get()->map(fn (CapacityRequest $r) => CapacityPlanner::present($r))->values()->all(), 'budget' => app(CapacityBudget::class)->status(), 'budget_forecast' => app(CapacityForecast::class)->budget()]]); // §5r-5: next month's purchases against the cap; §5m-7: days left per pool; §5n-7: open capacity requests
    }

    /** Capacity requests the forecast proposed (audit §5n-7). */
    public function capacityRequests(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'capacity.read', CommandScope::global());
        $state = (string) $request->query('state', 'open');
        $query = CapacityRequest::query()->orderByDesc('created_at');
        if ($state === 'open') {
            $query->whereIn('state', CapacityRequest::OPEN);
        } elseif ($state !== 'all') {
            $query->where('state', $state);
        }

        return response()->json(['data' => $query->limit(200)->get()->map(fn (CapacityRequest $r) => CapacityPlanner::present($r))->values()->all(), 'auto_order' => app(AutomationLedger::class)->enabled(CapacityPlanner::RULE), 'budget' => app(CapacityBudget::class)->status()]); // §5q-5: the monthly cap next to the requests
    }

    /** The monthly cap on vendor node orders (audit §5q-5). */
    public function capacityBudget(Request $request, CapacityBudget $budget): JsonResponse
    {
        $this->api->authorize($request, 'capacity.read', CommandScope::global());

        return response()->json(['data' => $budget->status()]);
    }

    public function setCapacityBudget(Request $request): JsonResponse
    {
        $data = $request->validate(['monthly_minor' => ['nullable', 'integer', 'min:0', 'max:1000000000']]);

        return $this->dispatch(new CapacityCommand($this->idempotencyKey($request, 'capacity.budget:'.now()->format('YmdHis')), ['op' => 'budget', 'monthly_minor' => $data['monthly_minor'] ?? null]), $this->api->context($request));
    }

    /** The daily capacity pass on demand (audit §5o): warnings, proposals, orders per the rule, deliveries. */
    public function runCapacityForecast(Request $request, CapacityForecast $forecast, CapacityPlanner $planner): JsonResponse
    {
        $this->api->authorize($request, 'capacity.manage', CommandScope::global());

        return response()->json(['data' => ['warned' => $forecast->warn(), 'plan' => $planner->run(), 'forecast' => $forecast->forecast()]]);
    }

    public function decideCapacityRequest(Request $request, string $capacityRequest): JsonResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,cancel,delivered,retry'], 'note' => ['nullable', 'string', 'max:500', 'required_if:override_budget,true', 'min:5'], 'node_name' => ['nullable', 'string', 'max:80'], 'override_budget' => ['nullable', 'boolean']]); // §5q-5: crossing the budget needs the override and a note naming who approved it

        return $this->dispatch(new CapacityCommand($this->idempotencyKey($request, "capacity.decide:{$capacityRequest}:{$data['decision']}"), ['op' => 'decide', 'request_id' => $capacityRequest, 'decision' => $data['decision'], 'note' => $data['note'] ?? null, 'node_name' => $data['node_name'] ?? null, 'override_budget' => (bool) ($data['override_budget'] ?? false)]), $this->api->context($request, null, $data['note'] ?? null));
    }

    public function freeze(Request $request): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:250']]);

        return $this->dispatch(new ProvisioningCommand('freeze:'.now()->timestamp, ['op' => 'freeze'] + $data), $this->api->context($request, null, $data['reason']));
    }

    public function thaw(Request $request): JsonResponse
    {
        return $this->dispatch(new ProvisioningCommand('thaw:'.now()->timestamp, ['op' => 'thaw']), $this->api->context($request));
    }

    public function reconcile(Request $request, string $service): JsonResponse
    {
        return $this->dispatch(new ProvisioningCommand("reconcile:{$service}:".now()->timestamp, ['op' => 'reconcile', 'service_id' => $service]), $this->api->context($request));
    }

    // ── provider instances (admin onboarding of Proxmox / ISPConfig / aaPanel / Pterodactyl / PowerDNS / WEDOS / RKE2) ──

    public function instance(Request $request, string $instance, ProviderInstanceService $instances): JsonResponse
    {
        $this->api->authorize($request, 'provider.instance.read', CommandScope::global());
        $model = ProviderInstance::query()->where('key', $instance)->orWhere('id', $instance)->first();
        if ($model === null) {
            throw DomainError::notFound('provider_instance');
        }
        $health = IntegrationHealth::query()->where('provider_instance_id', $model->id)->first();
        $nodes = Node::query()->where('provider_instance_id', $model->id)->orderBy('name')->get()->map(fn (Node $n) => ['id' => $n->id, 'name' => $n->name, 'role' => $n->role, 'state' => $n->state, 'region' => $n->region_code, 'capacity' => $n->capacity, 'usage' => $n->usage, 'last_seen_at' => $n->last_seen_at?->toIso8601String()])->all();

        return $this->ok(Presenters::providerInstance($model) + [
            'base_url' => $model->base_url, 'options' => $model->options, 'quotas' => $model->quotas, 'rate_limits' => $model->rate_limits, 'credentials' => $instances->credentialStatus($model),
            'integration' => $health ? ['up' => (bool) $health->up, 'last_success_at' => $health->last_success_at?->toIso8601String(), 'last_failure_at' => $health->last_failure_at?->toIso8601String(), 'error_rate_1h' => $health->error_rate_1h, 'p95_ms' => $health->p95_ms, 'circuit_state' => $health->circuit_state, 'last_error' => $health->last_error] : null,
            'nodes' => $nodes, 'schema' => ['credentials' => ProviderInstanceService::CREDENTIALS[$model->provider] ?? null, 'capabilities' => ProviderInstanceService::CAPABILITIES[$model->provider] ?? null],
        ]);
    }

    public function providerSchema(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'provider.instance.read', CommandScope::global());

        return $this->ok(['providers' => array_map(fn (string $p) => ['provider' => $p, 'credentials' => ProviderInstanceService::CREDENTIALS[$p], 'capabilities' => ProviderInstanceService::CAPABILITIES[$p]], array_keys(ProviderInstanceService::CREDENTIALS)), 'regions' => Region::query()->orderBy('code')->get(['code', 'name', 'country', 'state'])->all()]);
    }

    public function upsertInstance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9][a-z0-9-]{1,58}$/'], 'provider' => ['required', 'in:'.implode(',', array_keys(ProviderInstanceService::CREDENTIALS))], 'name' => ['nullable', 'string', 'max:120'],
            'region_code' => ['nullable', 'string', 'max:16'], 'base_url' => ['required', 'url', 'max:250'], 'options' => ['nullable', 'array'], 'capabilities' => ['nullable', 'array'], 'quotas' => ['nullable', 'array'], 'rate_limits' => ['nullable', 'array'],
            'secret_ref' => ['nullable', 'string', 'max:190', 'regex:~^(bao|env|file|db)://~'], 'credentials' => ['nullable', 'array'], 'credentials.*' => ['nullable', 'string', 'max:4096'], 'state' => ['nullable', 'in:active,draining,maintenance,disabled'],
            'confirm_host_change' => ['nullable', 'boolean'], // a new panel host locks the instance until a probe confirms it (H311)
            'force_credentials' => ['nullable', 'boolean'], // store a new access the panel could not confirm: the stored one is compromised (H314)
        ]);

        return $this->dispatch(new ProvisioningCommand($this->idempotencyKey($request, "instance.upsert:{$data['key']}"), ['op' => 'instance.upsert'] + $data), $this->api->context($request), 201);
    }

    public function probeInstance(Request $request, string $instance): JsonResponse
    {
        return $this->dispatch(new ProvisioningCommand("instance.probe:{$instance}:".now()->format('U.u'), ['op' => 'instance.probe', 'instance_key' => $instance]), $this->api->context($request));
    }

    public function instanceState(Request $request, string $instance): JsonResponse
    {
        $data = $request->validate(['state' => ['required', 'in:active,draining,maintenance,disabled'], 'reason' => ['nullable', 'string', 'max:250'], 'maintenance_until' => ['nullable', 'date']]);

        return $this->dispatch(new ProvisioningCommand($this->idempotencyKey($request, "instance.state:{$instance}"), ['op' => 'instance.state', 'instance_key' => $instance] + $data), $this->api->context($request, null, $data['reason'] ?? null));
    }

    public function discoverNodes(Request $request, string $instance): JsonResponse
    {
        return $this->dispatch(new ProvisioningCommand("instance.discover:{$instance}:".now()->format('U.u'), ['op' => 'instance.discover', 'instance_key' => $instance]), $this->api->context($request));
    }

    /** Plan placements (which panel / server a product or plan runs on) with the catalogue and candidate instances. */
    public function placements(Request $request, PlacementService $placements): JsonResponse
    {
        $this->api->authorize($request, 'provider.instance.read', CommandScope::global());

        return $this->ok($placements->overview());
    }

    public function upsertPlacement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_key' => ['required', 'string', 'max:60'], 'plan_key' => ['nullable', 'string', 'max:60'], 'region_code' => ['nullable', 'string', 'max:16'], 'provider_instance_key' => ['required', 'string', 'max:60'],
            'node_id' => ['nullable', 'string', 'max:120'], 'priority' => ['nullable', 'integer', 'min:1', 'max:1000'], 'state' => ['nullable', 'in:active,disabled'], 'note' => ['nullable', 'string', 'max:250'],
        ]);

        return $this->dispatch(new ProvisioningCommand($this->idempotencyKey($request, 'placement.upsert:'.$data['product_key'].':'.($data['plan_key'] ?? '*').':'.($data['region_code'] ?? '*')), ['op' => 'placement.upsert', 'placement' => $data]), $this->api->context($request), 201);
    }

    public function deletePlacement(Request $request, string $placement): JsonResponse
    {
        return $this->dispatch(new ProvisioningCommand($this->idempotencyKey($request, "placement.delete:{$placement}"), ['op' => 'placement.delete', 'placement_id' => $placement]), $this->api->context($request));
    }

    public function upsertNode(Request $request, string $instance): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'role' => ['required', 'in:compute,web,managed,game,mail,dns,apps,backup'], 'region_code' => ['nullable', 'string', 'max:16'], 'state' => ['nullable', 'in:active,draining,maintenance,unreachable,disabled'], 'capacity' => ['nullable', 'array'], 'failure_domain' => ['nullable', 'string', 'max:60'], 'remote_id' => ['nullable', 'string', 'max:120'], 'tags' => ['nullable', 'array']]);

        return $this->dispatch(new ProvisioningCommand($this->idempotencyKey($request, "node.upsert:{$instance}:{$data['name']}"), ['op' => 'node.upsert', 'instance_key' => $instance, 'node' => $data]), $this->api->context($request), 201);
    }
}
