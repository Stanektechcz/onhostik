<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Commands;

use App\Http\Presenters\Presenters;
use Carbon\CarbonImmutable;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Domains\RegistrarPriceScraper;
use Onhost\Domain\Domains\RegistrarPricing;
use Onhost\Domain\Orders\OrderRiskService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\BulkActionService;
use Onhost\Domain\Provisioning\FreezeSwitch;
use Onhost\Domain\Provisioning\GamePanelBootstrap;
use Onhost\Domain\Provisioning\GameTemplates;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\ResourceDrift;
use Onhost\Domain\Provisioning\NodePrerequisites;
use Onhost\Domain\Provisioning\OperationsBoard;
use Onhost\Domain\Provisioning\OperationService;
use Onhost\Domain\Provisioning\PlacementService;
use Onhost\Domain\Provisioning\ProviderInstanceService;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\Reconciler;
use Onhost\Domain\Provisioning\Scheduling\NodeRebalancer;
use Onhost\Domain\Provisioning\ServiceMigrationService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\GameToolsProvider;

final class ProvisioningCommandHandler implements CommandHandler
{
    public function __construct(
        private readonly OperationService $operations,
        private readonly FreezeSwitch $freeze,
        private readonly Reconciler $reconciler,
        private readonly ServiceService $services,
        private readonly ProviderInstanceService $instances,
        private readonly PlacementService $placements,
        private readonly RegistrarPricing $registrarPricing,
        private readonly RegistrarPriceScraper $registrarScraper,
    ) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof ProvisioningCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }

        return match ($command->op()) {
            'retry' => $this->operation($this->operations->retry($this->findOperation($command), $context, $command->get('reason'), (bool) $command->get('acknowledge_vendor_task', false))),
            'cancel' => $this->operation($this->operations->cancel($this->findOperation($command), $context, (string) $command->get('reason', 'cancelled by operator'))),
            'resolve_drift' => $this->resolveDrift($command, $context),
            'freeze' => (function () use ($command, $context) {
                $this->freeze->freeze((string) $command->get('reason', 'incident'), $context->actorType.':'.($context->actorId ?? 'system'));

                return ['frozen' => true, 'meta' => $this->freeze->meta()];
            })(),
            'thaw' => (function () {
                $this->freeze->thaw();

                return ['frozen' => false];
            })(),
            'reconcile' => (function () use ($command, $context) {
                $service = Service::query()->find((string) $command->get('service_id'));
                if ($service === null) {
                    throw DomainError::notFound('service');
                }
                $stats = [];
                $this->reconciler->reconcileService($service, $context, $stats);

                return ['service_id' => $service->id, 'state' => $service->fresh()->state, 'stats' => $stats];
            })(),
            'instance.upsert' => $this->instanceView($this->instances->upsert($command->payload, $context)),
            'instance.probe' => $this->instances->probe($this->findInstance($command), $context) + ['instance' => $this->instanceView($this->findInstance($command))],
            'instance.state' => $this->instanceView($this->instances->setState($this->findInstance($command), (string) $command->get('state'), $context, $command->get('reason'), $command->get('maintenance_until') ? now()->parse((string) $command->get('maintenance_until')) : null)),
            'instance.discover' => $this->instances->discoverNodes($this->findInstance($command), $context),
            'node.upsert' => (function () use ($command, $context) {
                $node = $this->instances->upsertNode($this->findInstance($command), (array) $command->get('node', []), $context);

                return ['id' => $node->id, 'name' => $node->name, 'role' => $node->role, 'state' => $node->state, 'region' => $node->region_code, 'capacity' => $node->capacity, 'usage' => $node->usage];
            })(),
            // node prerequisites (audit §5e-8): what the instance can deliver, recorded on it and read by siteFeatures()
            'instance.prereqs' => app(NodePrerequisites::class)->check($this->findInstance($command), $context),
            // bulk staff action (audit §5e-7): one operation per selected service through the ordinary workflow
            'bulk.start' => app(BulkActionService::class)->present(app(BulkActionService::class)->start((array) $command->get('filter', []), (string) $command->get('action'), (array) $command->get('params', []), $context, $command->get('reason'), $command->permission())),
            // staff switch of a scheduled rule (audit §5g-7); the rule keeps its slot and records skips while off
            'automation.toggle' => app(AutomationLedger::class)->setEnabled((string) $command->get('key'), filter_var($command->get('enabled', true), FILTER_VALIDATE_BOOLEAN), $context->actorType.':'.($context->actorId ?? 'system')),
            // staff drain / resume a node (audit §5e-3): draining nodes receive no new placements, running services stay
            'node.state' => (function () use ($command, $context) {
                $instance = $this->findInstance($command);
                $node = Node::query()->where('provider_instance_id', $instance->id)->whereKey((string) $command->get('node_id'))->first();
                if ($node === null) {
                    throw DomainError::notFound('node');
                }
                $state = (string) $command->get('state');
                if (! in_array($state, ['active', 'draining', 'maintenance', 'disabled'], true)) {
                    throw new DomainError('node_state_invalid', 'State must be active, draining, maintenance or disabled.', 422, ['field' => 'state']);
                }
                $node = app(OperationsBoard::class)->setState($node, $state, $command->get('reason'), $context, false, [], filter_var($command->get('keep', false), FILTER_VALIDATE_BOOLEAN));

                return ['id' => $node->id, 'name' => $node->name, 'state' => $node->state, 'tags' => $node->tags];
            })(),
            // game panels (audit §5f-2): map a catalogue template onto a panel nest/egg, create port ranges on a node
            'game.eggs.map' => (function () use ($command, $context) {
                $instance = $this->findInstance($command);
                $eggs = (array) $instance->option('eggs', []);
                $key = strtolower(trim((string) $command->get('key')));
                if (! preg_match('/^[a-z0-9][a-z0-9-]{1,40}$/', $key)) {
                    throw new DomainError('egg_key_invalid', 'key must be the catalogue template key (letters, digits, dashes).', 422, ['field' => 'key']);
                }
                if ($command->get('remove')) {
                    unset($eggs[$key]);
                } else {
                    $nest = (int) $command->get('nest');
                    $egg = (int) $command->get('egg');
                    if ($nest <= 0 || $egg <= 0) {
                        throw new DomainError('egg_mapping_invalid', 'nest and egg must be the panel ids.', 422, ['field' => 'egg']);
                    }
                    $eggs[$key] = array_filter(['nest' => $nest, 'egg' => $egg, 'docker_image' => $command->get('docker_image') ?: null, 'startup' => $command->get('startup') ?: null, 'environment' => (array) $command->get('environment', []) ?: null], fn ($v) => $v !== null);
                }
                $instance->forceFill(['options' => array_merge((array) $instance->options, ['eggs' => $eggs])])->save();
                app(ProviderRegistry::class)->forget($instance);
                $this->instances->auditOptions($instance, 'eggs', array_keys($eggs), $context);

                return ['instance' => $instance->key, 'eggs' => $eggs];
            })(),
            'game.eggs.sync' => app(GamePanelBootstrap::class)->syncEggs($this->findInstance($command), $context, filter_var($command->get('force', false), FILTER_VALIDATE_BOOLEAN)),
            'game.bootstrap' => app(GamePanelBootstrap::class)->bootstrap($this->findInstance($command), $context, (string) $command->get('product', 'game')),
            // game migrations (audit §5g-2): one server to another node of its panel, or every server of a node
            'game.migrate', 'service.migrate' => (function () use ($command, $context) {
                $service = Service::query()->find((string) $command->get('service_id'));
                if ($service === null) {
                    throw DomainError::notFound('service');
                }

                $window = fn (string $k) => $command->get($k) !== null && $command->get($k) !== '' ? CarbonImmutable::parse((string) $command->get($k)) : null;

                return Presenters::operation(app(ServiceMigrationService::class)->start($service, $command->get('target_node_id') !== null ? (string) $command->get('target_node_id') : null, $command->get('reason') !== null ? (string) $command->get('reason') : null, $context, $window('window_from'), $window('window_to')), true);
            })(),
            'game.evacuate', 'service.evacuate' => (function () use ($command, $context) {
                $instance = $this->findInstance($command);
                $node = Node::query()->where('provider_instance_id', $instance->id)->whereKey((string) $command->get('node_id'))->first();
                if ($node === null) {
                    throw DomainError::notFound('node');
                }
                $window = fn (string $k) => $command->get($k) !== null && $command->get($k) !== '' ? CarbonImmutable::parse((string) $command->get($k)) : null;

                return app(ServiceMigrationService::class)->evacuate($node, $command->get('target_node_id') !== null ? (string) $command->get('target_node_id') : null, $command->get('reason') !== null ? (string) $command->get('reason') : null, $context, $window('window_from'), $window('window_to'));
            })(),
            // rebalancing (audit §5i): the plan's moves become migrations, usually inside a window the customers may move
            // sandbox tenant (audit §5j-9): provisioning goes to lab instances, a promo credit to test with, no loyalty and no commissions
            'service.create' => (function () use ($command, $context) { // §5o: a staff quick action — the same create path an order takes
                $organization = Organization::query()->find((string) $command->get('organization_id')) ?? throw DomainError::notFound('organization');
                $product = Product::query()->where('key', (string) $command->get('product_key'))->first() ?? throw DomainError::notFound('product');
                $planKey = (string) $command->get('plan_key', '');
                $plan = $planKey !== '' ? Plan::query()->where('product_id', $product->id)->where('key', $planKey)->first() ?? throw DomainError::notFound('plan') : null;
                $config = (array) $command->get('config', []);
                $service = $this->services->create($organization, $product, $plan?->currentVersion(), $config, $context->withScope($organization->id), null, isset($config['label']) && $config['label'] !== '' ? (string) $config['label'] : null);
                $operation = Operation::query()->where('service_id', $service->id)->orderByDesc('queued_at')->first();

                return ['service' => Presenters::service($service->refresh()), 'operation_id' => $operation?->id, 'operation_state' => $operation?->state];
            })(),
            'tenant.sandbox' => (function () use ($command, $context) {
                $organization = Organization::query()->find((string) $command->get('organization_id')) ?? throw DomainError::notFound('organization');
                $enabled = filter_var($command->get('enabled', true), FILTER_VALIDATE_BOOL);
                $organization->forceFill(['feature_flags' => array_merge((array) ($organization->feature_flags ?? []), ['sandbox' => $enabled])])->save();
                $credit = (int) config('onhost.sandbox.credit_minor', 0);
                if ($enabled && $credit > 0) {
                    app(WalletService::class)->topup($organization, Money::minor($credit, (string) $organization->currency), 'promo', "sandbox:{$organization->id}", $context->withScope($organization->id), null, 'Sandbox kredit pro testování API', true);
                }
                app(OutboxPublisher::class)->publish(GenericEvent::of('tenant.sandbox', 'organization', $organization->id, ['enabled' => $enabled, 'credit' => $enabled ? $credit : 0], $organization->id));

                return ['organization_id' => $organization->id, 'sandbox' => $enabled, 'credit' => $enabled ? $credit : 0];
            })(),
            'rebalance.apply' => (function () use ($command, $context) {
                $window = fn (string $k) => $command->get($k) !== null && $command->get($k) !== '' ? CarbonImmutable::parse((string) $command->get($k)) : null;

                return app(NodeRebalancer::class)->apply(array_values(array_map('strval', (array) $command->get('service_ids', []))), $command->get('reason') !== null ? (string) $command->get('reason') : null, $context, $window('window_from'), $window('window_to'));
            })(),
            // the order intake check's weights and threshold (audit §5h-4)
            'automation.risk' => app(OrderRiskService::class)->tune((array) $command->get('weights', []), $command->get('hold_score') !== null ? (int) $command->get('hold_score') : null, filter_var($command->get('reset', false), FILTER_VALIDATE_BOOLEAN), $context->actorType.':'.($context->actorId ?? 'system')),
            'game.allocations.create' => (function () use ($command, $context) {
                $instance = $this->findInstance($command);
                $adapter = app(ProviderRegistry::class)->forInstance($instance);
                if (! $adapter instanceof GameToolsProvider) {
                    throw new DomainError('instance_not_game_panel', 'Allocations are created on game panel instances only.', 422);
                }
                $node = (int) $command->get('node');
                $ip = trim((string) $command->get('ip'));
                $ports = array_values(array_filter(array_map(fn ($p) => trim((string) $p), (array) $command->get('ports', [])), fn ($p) => preg_match('/^\d{2,5}(-\d{2,5})?$/', $p) === 1));
                if ($node <= 0 || filter_var($ip, FILTER_VALIDATE_IP) === false || $ports === []) {
                    throw new DomainError('allocation_input_invalid', 'node (panel node id), ip and ports (e.g. 25565 or 25570-25580) are required.', 422);
                }
                $result = $adapter->createAllocations($node, $ip, $ports, $command->get('alias') ?: null);
                $this->instances->auditOptions($instance, 'allocations', ['node' => $node, 'ip' => $ip, 'ports' => $ports], $context);

                return $result->data;
            })(),
            // node limits from the console, never from the panel's own UI (audit §5q follow-up): memory / disk in MB, over-allocation in %, or `detect` = the daemon's RAM minus a reserve
            'game.operator_variable.set' => app(GameTemplates::class)->setOperatorVariable((string) $command->get('env'), $command->get('secret') !== null ? (string) $command->get('secret') : null, $context), // §5t-1: the value is stripped from the command audit (`secret`)
            'game.node.update' => (function () use ($command, $context) {
                $instance = $this->findInstance($command);
                $adapter = app(ProviderRegistry::class)->forInstance($instance);
                if (! $adapter instanceof GameToolsProvider) {
                    throw new DomainError('instance_not_game_panel', 'Node limits are set on game panel instances only.', 422);
                }
                $nodeId = (int) $command->get('node');
                $fields = [];
                foreach (['memory', 'disk', 'memory_overallocate', 'disk_overallocate'] as $key) {
                    if ($command->get($key) !== null && $command->get($key) !== '') {
                        $fields[$key] = max(0, (int) $command->get($key));
                    }
                }
                if ($command->get('maintenance') !== null) {
                    $fields['maintenance_mode'] = filter_var($command->get('maintenance'), FILTER_VALIDATE_BOOLEAN);
                }
                $system = null;
                if (filter_var($command->get('detect', false), FILTER_VALIDATE_BOOLEAN)) {
                    $system = $adapter->nodeSystem($nodeId);
                    if ($system === null || $system['memory_mb'] === null) {
                        throw new DomainError('node_system_unknown', 'The node daemon does not report its memory; set the limit by hand.', 422, ['field' => 'memory']);
                    }
                    $fields['memory'] = max(1024, $system['memory_mb'] - max(0, (int) config('onhost.game.node_reserve_mb', 1024)));
                }
                if ($fields === []) {
                    throw new DomainError('node_update_empty', 'Nothing to change: memory, disk, over-allocation, maintenance or detect.', 422);
                }
                if (isset($fields['memory']) && $fields['memory'] < 1024 || isset($fields['disk']) && $fields['disk'] < 1024) {
                    throw new DomainError('node_limit_invalid', 'Memory and disk limits are in MB and must be at least 1024.', 422);
                }
                $result = $adapter->updateNode($nodeId, $fields);
                $this->instances->auditOptions($instance, 'node.limits', ['node' => $nodeId] + $fields + ['detected' => $system], $context);
                $this->instances->discoverNodes($instance, $context); // the scheduler sees the new capacity at once

                return $result->data + ['detected' => $system];
            })(),
            'placement.upsert' => PlacementService::present($this->placements->upsert((array) $command->get('placement', []), $context)),
            'placement.delete' => (function () use ($command, $context) {
                $this->placements->delete((string) $command->get('placement_id'), $context);

                return ['deleted' => true];
            })(),
            'registrar.costs.refresh' => $this->registrarPricing->refresh(null, $command->get('tlds') === null ? null : (array) $command->get('tlds'), $context),
            'registrar.costs.scrape' => $this->registrarScraper->scrape($command->get('registrar') ?: null, [], $context),
            'registrar.costs.upsert' => (function () use ($command, $context) {
                $row = $this->registrarPricing->upsertManual((array) $command->get('cost', []), $context);

                return ['id' => $row->id, 'registrar_provider' => $row->registrar_provider, 'tld' => $row->tld, 'currency' => $row->currency, 'register' => $row->register_minor, 'renew' => $row->renew_minor, 'transfer' => $row->transfer_minor, 'restore' => $row->restore_minor, 'source' => $row->source];
            })(),
            'registrar.policy.set' => (function () use ($command, $context) {
                $policy = $this->registrarPricing->setPolicy((string) $command->get('tld'), (string) $command->get('registrar_provider', 'auto'), $context);

                return ['tld' => $policy->tld, 'registrar_provider' => $policy->registrar_provider];
            })(),
            default => throw new DomainError('provisioning_op_unknown', "Unknown provisioning operation {$command->op()}.", 422),
        };
    }

    private function findInstance(ProvisioningCommand $command): ProviderInstance
    {
        $key = (string) $command->get('instance_key', $command->get('key', ''));
        $instance = ProviderInstance::query()->where('key', $key)->orWhere('id', $key)->first();
        if ($instance === null) {
            throw DomainError::notFound('provider_instance');
        }

        return $instance;
    }

    private function instanceView(ProviderInstance $instance): array
    {
        return Presenters::providerInstance($instance->fresh()) + ['base_url' => $instance->base_url, 'options' => $instance->options, 'credentials' => $this->instances->credentialStatus($instance), 'nodes' => Node::query()->where('provider_instance_id', $instance->id)->orderBy('name')->get()->map(fn ($n) => ['id' => $n->id, 'name' => $n->name, 'role' => $n->role, 'region' => $n->region_code, 'state' => $n->state, 'capacity' => $n->capacity, 'usage' => $n->usage, 'remote_id' => $n->remote_id, 'failure_domain' => $n->failure_domain, 'last_seen_at' => $n->last_seen_at?->toIso8601String()])->all()];
    }

    private function findOperation(ProvisioningCommand $command): Operation
    {
        $operation = Operation::query()->find((string) $command->get('operation_id'));
        if ($operation === null) {
            throw DomainError::notFound('operation');
        }

        return $operation;
    }

    private function resolveDrift(ProvisioningCommand $command, CommandContext $context): array
    {
        $drift = ResourceDrift::query()->find((string) $command->get('drift_id'));
        if ($drift === null) {
            throw DomainError::notFound('drift');
        }
        $resolution = (string) $command->get('resolution', 'approved');
        if (! in_array($resolution, ['approved', 'ignored', 'repair'], true)) {
            throw new DomainError('drift_resolution_invalid', 'resolution must be approved, ignored or repair.', 422, ['field' => 'resolution']);
        }
        $note = (string) $command->get('note', '');
        if ($note === '') {
            throw new DomainError('drift_note_required', 'Every drift decision needs a reason for the audit trail.', 422, ['field' => 'note']);
        }
        if ($resolution === 'repair') {
            $service = Service::query()->findOrFail($drift->service_id);
            if ($service->state !== ServiceStateMachine::ACTIVE) {
                throw new DomainError('service_state_invalid', 'Repair is only possible for active services.', 409);
            }
            $operation = $this->services->requestAction($service, 'resize', $context, "drift-repair:{$drift->id}", ['entitlements' => $service->entitlements, 'reason' => "drift repair: {$drift->field} — {$note}"], authorizedPermission: $command->permission(), authorizedScope: 'global');
            $drift->forceFill(['state' => 'repaired', 'resolved_at' => now(), 'resolved_by' => $context->actorId, 'resolution' => $note])->save();

            return ['drift_id' => $drift->id, 'state' => 'repaired', 'operation_id' => $operation->id];
        }
        $drift->forceFill(['state' => $resolution, 'resolved_at' => now(), 'resolved_by' => $context->actorId, 'resolution' => $note])->save();

        return ['drift_id' => $drift->id, 'state' => $drift->state];
    }

    private function operation(Operation $operation): array
    {
        return ['operation_id' => $operation->id, 'state' => $operation->state, 'kind' => $operation->kind];
    }
}
