<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Presenters\Presenters;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderRiskService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\BulkActionService;
use Onhost\Domain\Provisioning\Commands\ProvisioningCommand;
use Onhost\Domain\Provisioning\Models\BulkJob;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\WalletLedger\Models\AutoTopupSetting;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Providers\Contracts\GameProvider;
use Onhost\Providers\Contracts\GameToolsProvider;
use Throwable;

/**
 * Data behind the staff console's game, automation, renewals and jobs views (audit §5f-2): the prototype's tables
 * are filled from these endpoints through `onhost-admin.api.js`. Read-only except the two game panel actions, which
 * go through the provisioning command (audited, permission provider.instance.manage).
 */
final class ConsoleController extends ApiController
{
    /** Game panels: nodes (panel + scheduler view), templates (nests/eggs with the catalogue mapping), allocations per node, servers, and the game provisioning queue. */
    public function game(Request $request, ProviderRegistry $providers): JsonResponse
    {
        $this->api->authorize($request, 'provider.instance.read', CommandScope::global());
        $out = ['instances' => [], 'queue' => []];
        // the panel listings cost a handful of API calls per instance: kept for 20 seconds unless the console asks for fresh data (`?fresh=1`)
        $cached = $request->boolean('fresh') ? null : Cache::get('onhost:staff:game:instances');
        $pending = ! is_array($cached);
        $out['instances'] = is_array($cached) ? $cached : [];
        foreach ($pending ? ProviderInstance::query()->platform()->where('provider', 'pterodactyl')->orderBy('key')->get() : collect() as $instance) {
            $row = Presenters::providerInstance($instance) + ['base_url' => $instance->base_url, 'eggs_mapped' => (array) $instance->option('eggs', []), 'prereqs' => data_get($instance->capabilities, 'prereqs'), 'nodes' => [], 'eggs' => [], 'allocations' => [], 'servers' => [], 'error' => null];
            $local = Node::query()->where('provider_instance_id', $instance->id)->get()->keyBy(fn (Node $n) => (string) $n->remote_id);
            $services = Service::query()->where('provider_instance_id', $instance->id)->where('family', 'game')->get(['id', 'label', 'name', 'hostname', 'organization_id', 'state'])->keyBy('id');
            try {
                $adapter = $providers->forInstance($instance);
                if ($adapter instanceof GameProvider) {
                    $servers = $adapter instanceof GameToolsProvider ? $adapter->listServers() : [];
                    $byNode = collect($servers)->groupBy('node');
                    foreach ($adapter->listNodes() as $node) {
                        $l = $local->get((string) $node['id']);
                        $row['nodes'][] = $node + ['scheduler_state' => $l?->state, 'scheduler_id' => $l?->id, 'servers' => $byNode->get($node['id'], collect())->count(), 'memory_pct' => $node['memory'] > 0 ? (int) round($node['allocated_memory'] / $node['memory'] * 100) : 0, 'disk_pct' => $node['disk'] > 0 ? (int) round($node['allocated_disk'] / $node['disk'] * 100) : 0];
                        if ($adapter instanceof GameToolsProvider) {
                            $allocations = $adapter->nodeAllocations((int) $node['id']);
                            $row['allocations'][] = ['node' => (int) $node['id'], 'name' => $node['name'], 'total' => count($allocations), 'free' => count(array_filter($allocations, fn ($a) => ! $a['assigned'])), 'ips' => array_values(array_unique(array_column($allocations, 'ip')))];
                        }
                    }
                    if ($adapter instanceof GameToolsProvider) {
                        $mapped = (array) $instance->option('eggs', []);
                        $row['eggs'] = array_map(fn (array $egg) => $egg + ['servers' => collect($servers)->where('egg', $egg['id'])->count(), 'mapped_as' => array_keys(array_filter($mapped, fn ($m) => (int) ($m['nest'] ?? 0) === $egg['nest_id'] && (int) ($m['egg'] ?? 0) === $egg['id']))], $adapter->listEggs());
                        $row['servers'] = array_map(fn (array $s) => $s + ['service' => null], $servers); // matched to services below through the bindings
                        $bindings = ProviderBinding::query()->where('provider_instance_id', $instance->id)->where('remote_type', 'server')->get()->keyBy('remote_id');
                        foreach ($row['servers'] as &$server) {
                            $binding = $bindings->get((string) $server['id']);
                            $service = $binding ? $services->get($binding->service_id) : null;
                            $server['service'] = $service ? ['id' => $service->id, 'label' => $service->label ?: $service->name, 'state' => $service->state, 'organization' => Organization::query()->whereKey($service->organization_id)->value('name')] : null;
                        }
                        unset($server);
                    }
                }
            } catch (Throwable $e) {
                $row['error'] = mb_substr($e->getMessage(), 0, 160); // staff see the real reason (missing key, refused key, panel down)
            }
            $out['instances'][] = $row;
        }
        if ($pending) {
            Cache::put('onhost:staff:game:instances', $out['instances'], 20);
        }
        $queue = Operation::query()->whereIn('kind', ['provision.game', 'game.migrate'])->whereIn('state', [Operation::PENDING, Operation::RUNNING, Operation::WAITING, Operation::FAILED])->orderByDesc('queued_at')->limit(50)->get();
        $out['queue'] = $queue->map(fn (Operation $o) => Presenters::operation($o, true) + ['service' => Service::query()->whereKey($o->service_id)->value('label') ?: Service::query()->whereKey($o->service_id)->value('name'), 'organization' => Organization::query()->whereKey($o->organization_id)->value('name')])->all();
        $out['recent'] = Operation::query()->whereIn('kind', ['provision.game', 'game.migrate'])->where('state', Operation::SUCCEEDED)->where('finished_at', '>=', now()->subDay())->count();

        return response()->json(['data' => $out]);
    }

    public function mapEgg(Request $request, string $instance): JsonResponse
    {
        $data = $request->validate(['key' => ['required', 'string', 'max:40'], 'nest' => ['nullable', 'integer', 'min:1'], 'egg' => ['nullable', 'integer', 'min:1'], 'docker_image' => ['nullable', 'string', 'max:200'], 'startup' => ['nullable', 'string', 'max:500'], 'environment' => ['nullable', 'array'], 'remove' => ['nullable', 'boolean']]);

        return $this->dispatch(new ProvisioningCommand($this->idempotencyKey($request, "game.eggs.map:{$instance}:{$data['key']}"), ['op' => 'game.eggs.map', 'instance_key' => $instance] + $data), $this->api->context($request));
    }

    /** Map the catalogue templates onto the panel's eggs by name (audit §5g-1); `force` re-maps the ones already mapped. */
    public function syncEggs(Request $request, string $instance): JsonResponse
    {
        $data = $request->validate(['force' => ['nullable', 'boolean']]);

        return $this->dispatch(new ProvisioningCommand("game.eggs.sync:{$instance}:".now()->format('U.u'), ['op' => 'game.eggs.sync', 'instance_key' => $instance] + $data), $this->api->context($request));
    }

    /** The whole bootstrap (probe, nodes, templates, prerequisites, port ranges, placement) from the console. */
    public function bootstrap(Request $request, string $instance): JsonResponse
    {
        $data = $request->validate(['product' => ['nullable', 'string', 'max:60']]);

        return $this->dispatch(new ProvisioningCommand("game.bootstrap:{$instance}:".now()->format('U.u'), ['op' => 'game.bootstrap', 'instance_key' => $instance, 'product' => $data['product'] ?? 'game']), $this->api->context($request));
    }

    public function createAllocations(Request $request, string $instance): JsonResponse
    {
        $data = $request->validate(['node' => ['required', 'integer', 'min:1'], 'ip' => ['required', 'ip'], 'ports' => ['required', 'array', 'min:1', 'max:50'], 'ports.*' => ['string', 'max:12'], 'alias' => ['nullable', 'string', 'max:120']]);

        return $this->dispatch(new ProvisioningCommand($this->idempotencyKey($request, "game.allocations.create:{$instance}:{$data['node']}"), ['op' => 'game.allocations.create', 'instance_key' => $instance] + $data), $this->api->context($request), 201);
    }

    /** One game server to another node of its panel (audit §5g-2): a saga with backup, rebuild, data transfer, switch and clean-up. */
    public function migrate(Request $request, string $service): JsonResponse
    {
        $data = $request->validate(['target_node_id' => ['nullable', 'string', 'max:60'], 'reason' => ['nullable', 'string', 'max:250'], 'window_from' => ['nullable', 'date'], 'window_to' => ['nullable', 'date', 'after:window_from']]);

        return $this->dispatch(new ProvisioningCommand($this->idempotencyKey($request, "service.migrate:{$service}:".now()->format('YmdHi')), ['op' => 'service.migrate', 'service_id' => $service, 'target_node_id' => $data['target_node_id'] ?? null, 'reason' => $data['reason'] ?? null, 'window_from' => $data['window_from'] ?? null, 'window_to' => $data['window_to'] ?? null]), $this->api->context($request, null, $data['reason'] ?? null), 202);
    }

    /** Every game server of a node to another one (maintenance): the node is drained first, each server is its own saga. */
    public function evacuate(Request $request, string $instance, string $node): JsonResponse
    {
        $data = $request->validate(['target_node_id' => ['nullable', 'string', 'max:60'], 'reason' => ['nullable', 'string', 'max:250'], 'window_from' => ['nullable', 'date'], 'window_to' => ['nullable', 'date', 'after:window_from']]);

        return $this->dispatch(new ProvisioningCommand($this->idempotencyKey($request, "service.evacuate:{$instance}:{$node}:".now()->format('YmdHi')), ['op' => 'service.evacuate', 'instance_key' => $instance, 'node_id' => $node, 'target_node_id' => $data['target_node_id'] ?? null, 'reason' => $data['reason'] ?? null, 'window_from' => $data['window_from'] ?? null, 'window_to' => $data['window_to'] ?? null]), $this->api->context($request, null, $data['reason'] ?? null), 202);
    }

    /** The weights and the hold threshold of the order intake check (audit §5h-4); `reset` returns to the defaults. */
    public function riskTuning(Request $request): JsonResponse
    {
        $data = $request->validate(['weights' => ['nullable', 'array'], 'weights.*' => ['integer', 'min:'.OrderRiskService::MIN_WEIGHT, 'max:'.OrderRiskService::MAX_WEIGHT], 'hold_score' => ['nullable', 'integer', 'min:10', 'max:300'], 'reset' => ['nullable', 'boolean'], 'reason' => ['nullable', 'string', 'max:250']]);

        return $this->dispatch(new ProvisioningCommand($this->idempotencyKey($request, 'automation.risk:'.now()->format('YmdHi')), ['op' => 'automation.risk', 'weights' => (array) ($data['weights'] ?? []), 'hold_score' => $data['hold_score'] ?? null, 'reset' => (bool) ($data['reset'] ?? false), 'reason' => $data['reason'] ?? null]), $this->api->context($request, null, $data['reason'] ?? null));
    }

    /** The automation rules with their last run and what they touch right now. */
    public function automation(Request $request, AutomationLedger $ledger, OrderRiskService $risk): JsonResponse
    {
        $this->api->authorize($request, 'provisioning.operation.read', CommandScope::global());
        $counts = [
            'usage.watch' => ['auto_upgrade' => Service::query()->where('tags->policy->auto_upgrade', true)->count(), 'critical' => Service::query()->where('tags->usage->level', 'critical')->count(), 'warn' => Service::query()->where('tags->usage->level', 'warn')->count()],
            'renewal.guard' => ['auto_topup' => AutoTopupSetting::query()->where('enabled', true)->count()],
            'operations.board' => ['draining' => Node::query()->where('state', 'draining')->count(), 'auto_drained' => Node::query()->where('state', 'draining')->whereNotNull('tags->auto_drain->at')->count()],
            'order.risk' => ['pending' => Order::query()->where('meta->review->state', 'pending')->count(), 'enabled' => (bool) config('onhost.orders.risk.enabled', true), 'hold_score' => $risk->holdScore(), 'weights' => implode(' ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($risk->weights()), $risk->weights())), 'feedback' => implode(' ', array_map(fn ($k, $v) => "{$k} ".(int) ($v['released'] ?? 0).'↓/'.(int) ($v['rejected'] ?? 0).'↑', array_keys($risk->feedback()), $risk->feedback())) ?: 'none', 'geo' => trim((string) config('onhost.orders.risk.geo.endpoint', '')) !== ''],
            'nodes.check' => ['instances' => ProviderInstance::query()->platform()->whereNotNull('capabilities->prereqs->checked_at')->count()],
            'digest.weekly' => ['off' => Organization::query()->where('settings->digest->frequency', 'off')->count(), 'monthly' => Organization::query()->where('settings->digest->frequency', 'monthly')->count()],
        ];
        $rules = array_map(fn (array $rule) => $rule + ['now' => $counts[$rule['key']] ?? []], $ledger->overview());

        return response()->json(['data' => $rules, 'liveness' => $ledger->liveness()]);
    }

    /** Staff switch of one rule (audit §5g-7): off = the rule records skips until switched on again. */
    public function toggleAutomation(Request $request, string $key): JsonResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:250']]);

        // a switch is flipped back and forth with identical bodies: the derived idempotency key gets a minute bucket so a later flip is not replayed from the cache
        return $this->dispatch(new ProvisioningCommand($this->idempotencyKey($request, "automation.toggle:{$key}:".now()->format('YmdHi')), ['op' => 'automation.toggle', 'key' => $key, 'enabled' => (bool) $data['enabled'], 'reason' => $data['reason'] ?? null]), $this->api->context($request, null, $data['reason'] ?? null));
    }

    /** Renewals and expiries ahead: subscriptions due within the horizon with the credit that covers them, plus domain expiries. */
    public function renewals(Request $request, WalletService $wallets): JsonResponse
    {
        $this->api->authorize($request, 'staff.order.manage', CommandScope::global());
        $days = max(1, min(90, (int) $request->query('days', 30)));
        $until = now()->addDays($days);
        $rows = [];
        $subscriptions = Subscription::query()->whereIn('state', ['active', 'past_due'])->where('next_renewal_at', '<=', $until)->orderBy('next_renewal_at')->limit(200)->get();
        $organizations = Organization::query()->whereIn('id', $subscriptions->pluck('organization_id')->unique())->get()->keyBy('id');
        $services = Service::query()->whereIn('id', $subscriptions->pluck('service_id')->filter()->unique())->get()->keyBy('id');
        $available = [];
        foreach ($subscriptions as $sub) {
            $org = $organizations->get($sub->organization_id);
            $service = $sub->service_id ? $services->get($sub->service_id) : null;
            $key = $sub->organization_id.':'.$sub->currency;
            if (! isset($available[$key]) && $org !== null) {
                try {
                    $available[$key] = $wallets->balances($org, (string) $sub->currency)['available']->minor;
                } catch (Throwable) {
                    $available[$key] = null;
                }
            }
            $rows[] = [
                'id' => $sub->id, 'kind' => 'subscription', 'organization' => $org?->name, 'organization_id' => $sub->organization_id, 'service' => $service ? ($service->label ?: $service->hostname ?: $service->name) : null, 'service_id' => $sub->service_id,
                'period' => $sub->period, 'amount' => Presenters::money((int) $sub->amount_minor, (string) $sub->currency), 'at' => $sub->next_renewal_at?->toIso8601String(), 'days' => $sub->next_renewal_at ? (int) now()->diffInDays($sub->next_renewal_at, false) : null,
                'auto_renew' => (bool) $sub->auto_renew && ! $sub->cancel_at_period_end, 'state' => $sub->state, 'covered' => isset($available[$key]) && $available[$key] !== null ? $available[$key] >= (int) $sub->amount_minor : null,
            ];
        }
        foreach (Domain::query()->whereNotNull('expires_at')->where('expires_at', '<=', $until)->whereNotIn('state', ['deleted', 'transferred_out'])->orderBy('expires_at')->limit(200)->get() as $domain) {
            $rows[] = ['id' => $domain->id, 'kind' => 'domain', 'organization' => Organization::query()->whereKey($domain->organization_id)->value('name'), 'organization_id' => $domain->organization_id, 'service' => (string) ($domain->fqdn ?? $domain->fqdn_ascii ?? ''), 'service_id' => null, 'period' => 'year', 'amount' => null, 'at' => $domain->expires_at?->toIso8601String(), 'days' => (int) now()->diffInDays($domain->expires_at, false), 'auto_renew' => (bool) ($domain->auto_renew ?? true), 'state' => $domain->state, 'covered' => null];
        }
        usort($rows, fn ($a, $b) => strcmp((string) $a['at'], (string) $b['at']));

        return response()->json(['data' => $rows, 'days' => $days]);
    }

    /** The scheduler as configured (every command with its expression and next run) and the bulk jobs in flight. */
    public function jobs(Request $request, Schedule $schedule, BulkActionService $bulk, AutomationLedger $ledger): JsonResponse
    {
        $this->api->authorize($request, 'provisioning.operation.read', CommandScope::global());
        if ($schedule->events() === []) { // HTTP requests never load routes/console.php; the console kernel does, once
            app(ConsoleKernel::class)->bootstrap();
            $schedule = app(Schedule::class);
        }
        $events = [];
        foreach ($schedule->events() as $event) {
            $command = trim((string) preg_replace('~^.*artisan[\'"]?\s+~', '', (string) $event->command));
            $command = trim(preg_replace('/\s+>.*$/', '', $command) ?? $command) ?: 'job:'.class_basename((string) $event->description); // queued jobs have no command line, only a class name
            $key = collect(AutomationLedger::RULES)->first(fn ($r) => $r['command'] !== null && str_starts_with($command, $r['command']))['key'] ?? null;
            $events[] = ['command' => $command, 'expression' => $event->expression, 'description' => $event->description, 'next_run_at' => $event->nextRunDate()->toIso8601String(), 'last' => $key ? $ledger->last($key) : null];
        }
        $bulkJobs = BulkJob::query()->orderByDesc('created_at')->limit(20)->get()->map(fn (BulkJob $job) => $bulk->present($job))->all();

        return response()->json(['data' => ['scheduler' => $events, 'bulk_jobs' => $bulkJobs, 'bulk_actions' => BulkActionService::ACTIONS, 'liveness' => $ledger->liveness(), 'backlog' => $ledger->backlog(), 'queue' => ['pending' => Operation::query()->where('state', Operation::PENDING)->count(), 'running' => Operation::query()->where('state', Operation::RUNNING)->count(), 'waiting' => Operation::query()->where('state', Operation::WAITING)->count()]]]);
    }
}
