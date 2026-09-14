<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Billing\SubscriptionService;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\OrderFulfilmentService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\FreezeSwitch;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\OperationService;
use Onhost\Domain\Provisioning\PlacementService;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Provisioning\Workflows\CdnWorkflow;
use Onhost\Domain\Provisioning\Workflows\CertificateWorkflow;
use Onhost\Domain\Provisioning\Workflows\DeployWorkflow;
use Onhost\Domain\Provisioning\Workflows\ImportWorkflow;
use Onhost\Domain\Provisioning\Workflows\ProvisionAppWorkflow;
use Onhost\Domain\Provisioning\Workflows\ProvisionGameServerWorkflow;
use Onhost\Domain\Provisioning\Workflows\ProvisionMailDomainWorkflow;
use Onhost\Domain\Provisioning\Workflows\ProvisionVpsWorkflow;
use Onhost\Domain\Provisioning\Workflows\ProvisionWebsiteWorkflow;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Provisioning\Workflows\StagingWorkflow;
use Onhost\Domain\Provisioning\Workflows\WordPressWorkflow;
use Onhost\Domain\Services\Models\BackupPolicy;
use Onhost\Domain\Services\Models\DatabaseInstance;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Web\CommandRunner;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\ActualState;
use Onhost\Providers\Contracts\ConsoleCapable;
use Onhost\Providers\Contracts\GameToolsProvider;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\Usage;
use Onhost\Providers\Shell\SecurityRules;

/**
 * Service lifecycle (blueprint §5.1): the platform database is the desired state,
 * every change to a remote resource goes through an Operation, and the state
 * machine guards what is allowed when.
 */
final class ServiceService
{
    public function __construct(
        private readonly OperationService $operations,
        private readonly ProviderRegistry $providers,
        private readonly FreezeSwitch $freeze,
        private readonly OrderFulfilmentService $fulfilment,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    // ── creation ─────────────────────────────────────────────────────────────

    public function createFromOrderItem(OrderItem $item, Order $order, CommandContext $context): Service
    {
        $existing = Service::query()->where('order_item_id', $item->id)->first();
        if ($existing !== null) {
            return $existing;
        }
        $organization = Organization::query()->findOrFail($order->organization_id);
        $product = Product::query()->where('key', $item->product_key)->firstOrFail();
        $version = $item->plan_version_id ? PlanVersion::query()->with('plan')->find($item->plan_version_id) : null;
        $config = (array) $item->config;
        if ($product->family === 'addon') {
            if (empty($config['parent_service_id']) && ! empty($config['parent_line_id'])) { // web checkout: the add-on names the cart line it extends
                $parentItem = OrderItem::query()->where('order_id', $item->order_id)->where('id', '!=', $item->id)->get()->first(fn (OrderItem $i) => (($i->config['line_id'] ?? null) === $config['parent_line_id']));
                if ($parentItem === null) {
                    throw new DomainError('addon_parent_required', "Addon {$product->key} refers to an order line that does not exist.", 422);
                }
                if ($parentItem->service_id === null) {
                    $this->createFromOrderItem($parentItem, $order, $context); // the parent service first, then the add-on hangs under it
                }
                $config['parent_service_id'] = $parentItem->fresh()->service_id;
            }

            return $this->attachAddon($organization, $product, $version, $config, $context, $item);
        }

        return $this->create($organization, $product, $version, $config, $context, $item, $item->name);
    }

    /** @param array<string,mixed> $config order item configuration (fqdn/domain, hostname, image, options, ssh_keys, egg, app …) */
    public function create(Organization $organization, Product $product, ?PlanVersion $version, array $config, CommandContext $context, ?OrderItem $item = null, ?string $name = null): Service
    {
        if ($product->executor === null || $product->state !== 'active') {
            throw new DomainError('product_not_provisionable', "Product {$product->key} has no executor or is not active.", 422);
        }
        $entitlements = $this->applyOptions((array) ($config['entitlements'] ?? $version?->entitlements ?? []), (array) ($config['options'] ?? []), $product);
        $limits = (array) ($config['limits'] ?? $version?->limits ?? []);
        $region = (string) ($config['region'] ?? config('onhost.provisioning.default_region', 'cz1'));
        $service = DB::transaction(function () use ($organization, $product, $version, $config, $item, $name, $entitlements, $limits, $region) {
            $service = Service::query()->create([
                'organization_id' => $organization->id, 'project_id' => $config['project_id'] ?? null, 'product_key' => $product->key, 'plan_version_id' => $version?->id, 'family' => $product->family,
                'name' => $name ?: ($product->localizedName('cs').($version ? ' '.$version->plan?->localizedName('cs') : '')), 'label' => $config['label'] ?? null,
                'state' => ServiceStateMachine::PAID, 'region_code' => $region, 'entitlements' => $entitlements, 'sla_class' => (string) ($version?->plan?->sla_class ?? 'standard'),
                'order_item_id' => $item?->id, 'tags' => array_filter(['parent_service_id' => $config['parent_service_id'] ?? null]), 'desired_spec' => [],
            ]);
            $desired = $this->desiredSpec($service, $product, $version, $config, $organization, $limits);
            $service->forceFill(['desired_spec' => $desired, 'hostname' => $desired['hostname'] ?? $desired['domain'] ?? null])->save();
            $item?->forceFill(['service_id' => $service->id, 'state' => 'provisioning'])->save();

            return $service;
        });
        $this->audit->record($context->withScope($organization->id), 'service.create', 'succeeded', ['product' => $product->key, 'plan_version_id' => $version?->id, 'region' => $region], 'service', $service->id);
        $this->outbox->publish(GenericEvent::of('service.created', 'service', $service->id, ['product_key' => $product->key, 'family' => $product->family, 'order_item_id' => $item?->id], $organization->id));
        $this->startProvisioning($service, $context);

        return $service;
    }

    /** Addons (extra IPv4, backup plans) attach to a parent service instead of provisioning their own resource. */
    private function attachAddon(Organization $organization, Product $product, ?PlanVersion $version, array $config, CommandContext $context, OrderItem $item): Service
    {
        $parent = isset($config['parent_service_id']) ? Service::query()->find($config['parent_service_id']) : null;
        if ($parent === null || $parent->organization_id !== $organization->id) {
            throw new DomainError('addon_parent_required', "Addon {$product->key} needs a parent service in the same organization.", 422);
        }
        $entitlements = (array) ($config['entitlements'] ?? $version?->entitlements ?? []);
        $service = Service::query()->create([
            'organization_id' => $organization->id, 'product_key' => $product->key, 'plan_version_id' => $version?->id, 'family' => 'addon', 'name' => $item->name, 'state' => ServiceStateMachine::ACTIVE, 'activated_at' => now(),
            'region_code' => $parent->region_code, 'provider_instance_id' => $parent->provider_instance_id, 'node_id' => $parent->node_id, 'entitlements' => $entitlements, 'sla_class' => $parent->sla_class, 'order_item_id' => $item->id,
            'tags' => ['parent_service_id' => $parent->id], 'desired_spec' => ['parent_service_id' => $parent->id, 'addon' => $product->key],
        ]);
        $item->forceFill(['service_id' => $service->id, 'state' => 'active'])->save();
        if ($product->key === 'backup-plus') {
            BackupPolicy::query()->updateOrCreate(['service_id' => $parent->id], ['product_key' => $product->key, 'schedule' => ['daily' => '02:30'], 'retention' => array_intersect_key($entitlements, array_flip(['daily', 'weekly', 'monthly'])), 'offsite' => (bool) ($entitlements['offsite'] ?? false), 'restore_test' => ['cadence' => $entitlements['restore_test'] ?? 'monthly'], 'state' => 'active']);
        } elseif ($product->key === 'ipv4') {
            $parent->forceFill(['entitlements' => array_replace((array) $parent->entitlements, ['ipv4' => (int) ($entitlements['addresses'] ?? 1)])])->save();
            if ($parent->isActive() && $parent->family === 'cloud') {
                $this->requestAction($parent, 'resize', $context, "addon:{$item->id}", ['entitlements' => ['ipv4' => (int) ($entitlements['addresses'] ?? 1)], 'reason' => 'ipv4 addon']);
            }
        }
        $this->audit->record($context->withScope($organization->id), 'service.addon.attach', 'succeeded', ['addon' => $product->key, 'parent' => $parent->id], 'service', $service->id);
        $this->outbox->publish(GenericEvent::of('service.activated', 'service', $service->id, ['product_key' => $product->key, 'parent_service_id' => $parent->id, 'order_item_id' => $item->id], $organization->id));
        $this->checkOrderCompletion($item, $context);

        return $service;
    }

    public function startProvisioning(Service $service, CommandContext $context): Operation
    {
        $workflow = $this->workflowFor($service);
        $this->transition($service, ServiceStateMachine::PROVISIONING, $context, 'provisioning started');
        $desired = array_merge((array) $service->desired_spec, ['service_id' => $service->id]);

        return $this->operations->start($workflow, "provision:{$service->id}", $desired, $context, $service->id, $service->organization_id, $service->order_item_id, $service->provider_instance_id);
    }

    /** @return class-string<Workflow> */
    public function workflowFor(Service $service): string
    {
        $executor = (string) data_get($service->desired_spec, 'executor', '');

        return match (true) {
            $executor === 'proxmox' => ProvisionVpsWorkflow::class,
            $executor === 'ispconfig' && $service->family === 'mail' => ProvisionMailDomainWorkflow::class,
            $executor === 'ispconfig', $executor === 'aapanel' => ProvisionWebsiteWorkflow::class,
            $executor === 'pterodactyl' => ProvisionGameServerWorkflow::class,
            $executor === 'kubernetes' && $service->family === 'apps' => ProvisionAppWorkflow::class,
            default => throw new DomainError('product_not_provisionable', "No provisioning workflow for executor '{$executor}' / family '{$service->family}'.", 422),
        };
    }

    // ── lifecycle callbacks (called by sagas) ────────────────────────────────

    /** @param array<string,mixed> $access customer-facing access details (ips, domain, console kind …) */
    public function activate(Service $service, CommandContext $context, Operation $operation, array $access = []): void
    {
        $fresh = Service::query()->findOrFail($service->id);
        if ($fresh->state !== ServiceStateMachine::ACTIVE) {
            $this->transition($fresh, ServiceStateMachine::ACTIVE, $context, 'provisioning finished');
        }
        $fresh->forceFill(['activated_at' => $fresh->activated_at ?? now(), 'health' => ['status' => 'ok', 'checked_at' => now()->toISOString()], 'tags' => array_replace((array) $fresh->tags, ['access' => array_filter($access)])])->save();
        if ($fresh->family === 'data') {
            $engine = (string) data_get($fresh->desired_spec, 'engine', 'postgresql-16');
            [$name, $ver] = array_pad(explode('-', $engine, 2), 2, null);
            DatabaseInstance::query()->updateOrCreate(['service_id' => $fresh->id], ['engine' => $name, 'version' => $ver, 'host' => $access['ipv6'] ?? $access['ipv4'] ?? $fresh->hostname, 'port' => match ($name) {
                'mariadb' => 3306, 'redis' => 6379, default => 5432
            }, 'pitr' => (bool) $fresh->entitlement('pitr_days'), 'external_access' => false, 'allowlist' => [], 'state' => 'active']);
        }
        $item = $operation->order_item_id !== null ? OrderItem::query()->find($operation->order_item_id) : null;
        if ($item !== null) {
            $item->forceFill(['state' => 'active', 'service_id' => $fresh->id])->save();
        }
        if (data_get($fresh->tags, 'billing') !== 'included') { // staging copies ride on the production plan and are never invoiced
            app(SubscriptionService::class)->ensureForService($fresh, $item, $context); // renewals / metering period start with activation
        }
        if ($item !== null) {
            $this->checkOrderCompletion($item, $context);
        }
        $this->audit->record($context->withScope($fresh->organization_id), 'service.activated', 'succeeded', ['product' => $fresh->product_key, 'operation_id' => $operation->id], 'service', $fresh->id);
        $this->outbox->publish(GenericEvent::of('service.activated', 'service', $fresh->id, ['product_key' => $fresh->product_key, 'family' => $fresh->family, 'access' => array_filter($access), 'order_item_id' => $operation->order_item_id, 'operation_id' => $operation->id], $fresh->organization_id));
    }

    public function fail(Service $service, CommandContext $context, string $reason, Operation $operation): void
    {
        $fresh = Service::query()->findOrFail($service->id);
        if (! in_array($fresh->state, [ServiceStateMachine::TERMINATED, ServiceStateMachine::FAILED], true)) {
            $fresh->forceFill(['state' => ServiceStateMachine::FAILED, 'health' => ['status' => 'failed', 'error' => mb_substr($reason, 0, 500), 'checked_at' => now()->toISOString()]])->save();
        }
        if ($operation->order_item_id !== null) {
            $item = OrderItem::query()->find($operation->order_item_id);
            if ($item !== null) {
                $item->forceFill(['state' => 'failed'])->save();
                $this->checkOrderCompletion($item, $context);
            }
        }
        $this->audit->record($context->withScope($fresh->organization_id), 'service.failed', 'failed', ['reason' => $reason, 'operation_id' => $operation->id], 'service', $fresh->id);
        $this->outbox->publish(GenericEvent::of('service.failed', 'service', $fresh->id, ['product_key' => $fresh->product_key, 'reason' => $reason, 'order_item_id' => $operation->order_item_id, 'operation_id' => $operation->id], $fresh->organization_id));
    }

    /** Finish a transient state (SUSPENDING → SUSPENDED, RESIZING → ACTIVE, TERMINATING → TERMINATED, …). */
    public function settleTransient(Service $service, string $state, CommandContext $context, string $reason, Operation $operation, ?ActualState $actual = null, ?string $event = null): void
    {
        $fresh = Service::query()->findOrFail($service->id);
        $patch = [];
        if ($fresh->state !== $state) {
            ServiceStateMachine::machine()->assertTransition($fresh->state, $state);
            $patch['state'] = $state;
        }
        if ($actual !== null) {
            $patch['actual_spec'] = $actual->attributes;
            $patch['last_reconciled_at'] = now();
        }
        match ($state) {
            ServiceStateMachine::SUSPENDED => $patch += ['suspended_at' => now(), 'suspended_reason' => mb_substr($reason, 0, 120)],
            ServiceStateMachine::ACTIVE => $patch += ['suspended_at' => null, 'suspended_reason' => null],
            ServiceStateMachine::TERMINATED => $patch += ['terminated_at' => now(), 'retention_until' => now()->addDays((int) config('onhost.compliance.retention_after_termination_days', 30))],
            default => null,
        };
        $fresh->forceFill($patch)->save();
        $this->audit->record($context->withScope($fresh->organization_id), 'service.'.strtolower($state), 'succeeded', ['reason' => $reason, 'operation_id' => $operation->id], 'service', $fresh->id);
        $this->outbox->publish(GenericEvent::of($event ?? 'service.'.strtolower($state), 'service', $fresh->id, ['product_key' => $fresh->product_key, 'reason' => $reason, 'operation_id' => $operation->id, 'state' => $fresh->state], $fresh->organization_id));
        if ($state === ServiceStateMachine::TERMINATED) {
            Subscription::query()->where('service_id', $fresh->id)->whereNotIn('state', [Subscription::CANCELLED])->update(['state' => Subscription::CANCELLED, 'auto_renew' => false]);
            if ($fresh->deleted_at === null) {
                $fresh->delete(); // soft delete; data retention handled by the compliance timers
            }
        }
    }

    public function recordActual(Service $service, ActualState $actual, CommandContext $context, string $auditAction, array $detail = []): void
    {
        $service->forceFill(['actual_spec' => $actual->attributes, 'last_reconciled_at' => now(), 'health' => array_replace((array) $service->health, ['status' => $actual->status, 'checked_at' => now()->toISOString()])])->save();
        $this->audit->record($context->withScope($service->organization_id), $auditAction, 'succeeded', array_merge($detail, ['status' => $actual->status]), 'service', $service->id);
    }

    // ── customer/staff actions ───────────────────────────────────────────────

    /**
     * @param  'power'|'suspend'|'resume'|'resize'|'terminate'|'backup'|'restore'|'snapshot'|'rollback_snapshot'  $action
     * @param  array<string,mixed>  $params  power_action, entitlements, backup_id, name, reason, final_backup …
     */
    public function requestAction(Service $service, string $action, CommandContext $context, string $idempotencyKey, array $params = [], bool $chained = false): Operation
    {
        if (! in_array($action, ServiceActionWorkflow::ACTIONS, true)) {
            throw new DomainError('service_action_unknown', "Unknown service action {$action}.", 422);
        }
        $existing = Operation::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }
        if ($service->primaryBinding() === null || $service->provider_instance_id === null) {
            throw new DomainError('service_not_provisioned', 'The service has no provider resource yet.', 409);
        }
        // one thing at a time per service — except a declarative apply, which deliberately queues several steps; the queue runs them one after another (RunOperation is WithoutOverlapping per service)
        if (! $chained && Operation::query()->where('service_id', $service->id)->whereIn('state', [Operation::PENDING, Operation::RUNNING, Operation::WAITING])->exists()) {
            throw new DomainError('operation_in_progress', 'Another operation is still running on this service; wait for it to finish.', 409);
        }
        if ($this->freeze->isFrozen() && ! in_array($action, ['power', 'backup', 'snapshot'], true)) {
            throw new DomainError('provisioning_frozen', 'Provisioning is frozen by an incident switch.', 423);
        }
        $allowed = match ($action) {
            'power', 'resize', 'snapshot', 'rollback_snapshot' => [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED],
            'suspend' => [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED],
            'resume' => [ServiceStateMachine::SUSPENDED],
            'terminate' => [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED, ServiceStateMachine::SUSPENDED, ServiceStateMachine::FAILED],
            'backup', 'restore' => [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED, ServiceStateMachine::SUSPENDED],
            default => [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED], // feature actions (php, databases, ftp, cron, ssl, mail …)
        };
        if (! in_array($service->state, $allowed, true)) {
            throw new DomainError('service_state_invalid', "{$action} is not allowed while the service is {$service->state}.", 409, ['state' => $service->state]);
        }
        if ($action === 'power' && ! in_array($params['power_action'] ?? '', ['start', 'stop', 'shutdown', 'reboot', 'reset', 'kill'], true)) {
            throw new DomainError('power_action_invalid', 'power_action must be one of start, stop, shutdown, reboot, reset, kill.', 422);
        }
        if ($action === 'terminate') {
            if ($service->legal_hold) {
                throw new DomainError('legal_hold', 'The service is under legal hold and cannot be terminated.', 423);
            }
            if ($context->actorType === 'user' && $context->stepUpMethod === null) {
                throw new DomainError('step_up_required', 'Terminating a service requires a fresh step-up.', 403, ['step_up' => 'required']);
            }
            if ($context->actorType === 'ai') {
                throw new DomainError('ai_action_forbidden', 'AI actors may not terminate services.', 403);
            }
        }
        if ($action === 'resize' && empty($params['entitlements'])) {
            throw new DomainError('resize_target_required', 'Resize needs the target entitlements.', 422);
        }
        if (! in_array($action, ServiceActionWorkflow::CORE_ACTIONS, true)) {
            $params = $this->featureParams($service, $action, $params);
        }
        $transient = match ($action) {
            'suspend' => ServiceStateMachine::SUSPENDING, 'resume' => ServiceStateMachine::RESUMING, 'resize' => ServiceStateMachine::RESIZING, 'terminate' => ServiceStateMachine::TERMINATING, default => null,
        };
        if ($transient !== null) {
            $this->transition($service, $transient, $context, (string) ($params['reason'] ?? $action));
        }
        $operation = $this->operations->start(self::actionWorkflowFor($action), $idempotencyKey, array_merge($params, ['action' => $action, 'service_id' => $service->id]), $context, $service->id, $service->organization_id, null, $service->provider_instance_id);
        $this->audit->record($context->withScope($service->organization_id), "service.action.{$action}", 'succeeded', ['params' => array_diff_key($params, array_flip(['password'])), 'operation_id' => $operation->id], 'service', $service->id, stepUp: $context->stepUpMethod, approvalIds: $context->approvalIds);

        return $operation;
    }

    /** Which workflow runs an action: one provider call (ServiceActionWorkflow) or a saga of its own. @return class-string<Workflow> */
    public static function actionWorkflowFor(string $action): string
    {
        return match (true) {
            str_starts_with($action, 'staging.') => StagingWorkflow::class,
            str_starts_with($action, 'deploy.') => DeployWorkflow::class,
            str_starts_with($action, 'wp.') => WordPressWorkflow::class,
            $action === 'import.run' => ImportWorkflow::class,
            str_starts_with($action, 'cdn.') => CdnWorkflow::class,
            $action === 'ssl.wildcard' => CertificateWorkflow::class,
            default => ServiceActionWorkflow::class,
        };
    }

    /**
     * Feature actions (ServiceFeatures::ACTIONS): the feature must be enabled for the service and the parameters are
     * validated here, once, before the operation is queued — names on shared executors get the per-service prefix.
     *
     * @return array<string,mixed> normalised params
     */
    private function featureParams(Service $service, string $action, array $params): array
    {
        $features = app(ServiceFeatures::class);
        if (! in_array($action, $features->actions($service), true)) {
            throw new DomainError('feature_unavailable', "{$action} is not available for this service.", 422, ['action' => $action]);
        }
        $need = function (string $key, string $pattern, string $message) use (&$params, $action): string {
            $value = trim((string) ($params[$key] ?? ''));
            if ($value === '' || ! preg_match($pattern, $value)) {
                throw new DomainError('action_param_invalid', "{$action}: {$message}", 422, ['field' => $key]);
            }

            return $value;
        };
        $password = function () use (&$params, $action): string {
            $value = (string) ($params['password'] ?? '');
            if (strlen($value) < 12 || strlen($value) > 72) {
                throw new DomainError('action_param_invalid', "{$action}: password must have 12–72 characters.", 422, ['field' => 'password']);
            }

            return $value;
        };
        $remote = fn () => $need('remote_id', '/^[A-Za-z0-9:_.-]{1,120}$/', 'remote_id is required.');
        $hostname = '/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i';
        $limit = function (string $feature, string $kind) use ($service, $features, $action): void {
            $limit = $features->features($service)[$feature]['limit'] ?? null;
            if ($limit !== null && $limit > 0 && count($features->resources($service, $kind, true)) >= $limit) {
                throw new DomainError('feature_limit_reached', "{$action}: the plan allows {$limit} of these.", 422, ['limit' => $limit]);
            }
        };

        return match ($action) {
            'php.set' => ['version' => $need('version', '/^\d\.\d$/', 'version must look like 8.3')],
            'database.create' => (function () use ($need, $password, $params, $service, $limit) {
                $limit('databases', 'databases');
                $name = ServiceFeatures::scopedName($service, $need('name', '/^[a-z0-9_]{1,24}$/i', 'name may contain letters, digits and underscores (max 24)'));

                return ['name' => $name, 'user' => isset($params['user']) && $params['user'] !== '' ? ServiceFeatures::scopedName($service, $need('user', '/^[a-z0-9_]{1,16}$/i', 'user may contain letters, digits and underscores (max 16)'), 32) : $name, 'password' => $password(), 'charset' => in_array($params['charset'] ?? 'utf8mb4', ['utf8mb4', 'utf8', 'latin1'], true) ? ($params['charset'] ?? 'utf8mb4') : 'utf8mb4'];
            })(),
            'database.delete', 'ftp.delete', 'cron.delete', 'subdomain.remove', 'mailbox.delete', 'alias.delete' => ['remote_id' => $remote()],
            'ftp.create' => (function () use ($need, $password, $params, $service, $limit) {
                $limit('ftp', 'ftp');

                return ['user' => ServiceFeatures::scopedName($service, $need('user', '/^[a-z0-9_.-]{1,24}$/i', 'user may contain letters, digits, dots, dashes and underscores (max 24)')), 'password' => $password(), 'path' => isset($params['path']) ? trim(preg_replace('/[^\w\/.-]/', '', (string) $params['path']) ?? '', '/') : null];
            })(),
            'ftp.password' => ['remote_id' => $remote(), 'password' => $password()],
            'cron.create' => (function () use ($need, $params, $limit) {
                $limit('cron', 'cron');
                $schedule = $need('schedule', '/^(\S+\s+){4}\S+$/', 'schedule must have five cron fields (minute hour day month weekday)');
                $command = trim((string) ($params['command'] ?? ''));
                if ($command === '' || strlen($command) > 500 || preg_match('/[\r\n]/', $command)) {
                    throw new DomainError('action_param_invalid', 'cron.create: command is required (one line, max 500 characters).', 422, ['field' => 'command']);
                }

                return ['schedule' => $schedule, 'command' => $command, 'label' => substr(trim((string) ($params['label'] ?? '')), 0, 40) ?: null];
            })(),
            'subdomain.add' => (function () use ($need, $params, $hostname, $limit) {
                $limit('subdomains', 'subdomains');

                return ['domain' => strtolower($need('domain', $hostname, 'domain must be a valid host name')), 'path' => isset($params['path']) ? trim(preg_replace('/[^\w\/.-]/', '', (string) $params['path']) ?? '', '/') : null];
            })(),
            'redirect.set' => (function () use ($params, $action) {
                $target = trim((string) ($params['target'] ?? ''));
                if ($target !== '' && ! filter_var($target, FILTER_VALIDATE_URL)) {
                    throw new DomainError('action_param_invalid', "{$action}: target must be an absolute URL or empty to remove the redirect.", 422, ['field' => 'target']);
                }

                return ['target' => $target, 'type' => in_array((string) ($params['type'] ?? '301'), ['301', '302'], true) ? (string) ($params['type'] ?? '301') : '301'];
            })(),
            'ssl.issue' => ['domains' => array_values(array_filter(array_map(fn ($d) => strtolower(trim((string) $d)), (array) ($params['domains'] ?? [])), fn ($d) => preg_match($hostname, $d) === 1))],
            'https.force' => ['enabled' => filter_var($params['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN)],
            'snapshot.delete' => ['name' => $need('name', '/^[A-Za-z0-9_-]{1,40}$/', 'name is required')],
            'firewall.apply' => (function () use ($params, $action) {
                $rules = [];
                foreach ((array) ($params['rules'] ?? []) as $rule) {
                    $rule = (array) $rule;
                    if (! in_array(strtoupper((string) ($rule['action'] ?? '')), ['ACCEPT', 'DROP', 'REJECT'], true) || ! in_array(strtolower((string) ($rule['type'] ?? 'in')), ['in', 'out'], true)) {
                        throw new DomainError('action_param_invalid', "{$action}: every rule needs action ACCEPT|DROP|REJECT and type in|out.", 422, ['field' => 'rules']);
                    }
                    $rules[] = ['action' => strtoupper((string) $rule['action']), 'type' => strtolower((string) ($rule['type'] ?? 'in')), 'proto' => isset($rule['proto']) ? strtolower((string) $rule['proto']) : null, 'dport' => isset($rule['dport']) ? preg_replace('/[^\d:,-]/', '', (string) $rule['dport']) : null, 'source' => isset($rule['source']) ? preg_replace('/[^\da-fA-F:.\/,]/', '', (string) $rule['source']) : null, 'enable' => filter_var($rule['enable'] ?? true, FILTER_VALIDATE_BOOLEAN), 'comment' => substr((string) ($rule['comment'] ?? ''), 0, 60)];
                }

                return ['rules' => $rules, 'enabled' => filter_var($params['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN)];
            })(),
            'command.send' => ['command' => $need('command', '/^[^\r\n]{1,1000}$/', 'command is required (one line)')],
            'schedule.create' => (function () use ($need, $params, $action) {
                $actions = [];
                foreach ((array) ($params['actions'] ?? []) as $a) {
                    $a = (array) $a;
                    if (! in_array($a['action'] ?? '', ['command', 'power', 'backup'], true)) {
                        throw new DomainError('action_param_invalid', "{$action}: actions must be command, power or backup.", 422, ['field' => 'actions']);
                    }
                    $actions[] = ['action' => $a['action'], 'payload' => substr((string) ($a['payload'] ?? ''), 0, 1000)];
                }
                if ($actions === []) {
                    throw new DomainError('action_param_invalid', "{$action}: at least one action is required.", 422, ['field' => 'actions']);
                }

                return ['name' => $need('name', '/^[\w .-]{1,40}$/u', 'name is required'), 'cron' => $need('cron', '/^(\S+\s+){4}\S+$/', 'cron must have five fields'), 'actions' => $actions];
            })(),
            'mailbox.create' => (function () use ($need, $password, $params, $limit) {
                $limit('mailboxes', 'mailboxes');

                return ['address' => strtolower($need('address', '/^[^@\s]{1,64}@[^@\s]{3,253}$/', 'address must be an e-mail address')), 'password' => $password(), 'name' => substr(trim((string) ($params['name'] ?? '')), 0, 80), 'quota_mb' => max(64, min(102400, (int) ($params['quota_mb'] ?? 2048)))];
            })(),
            'mailbox.update' => ['remote_id' => $remote(), 'changes' => array_filter(['password' => isset($params['password']) && $params['password'] !== '' ? $password() : null, 'name' => isset($params['name']) ? substr(trim((string) $params['name']), 0, 80) : null, 'quota_mb' => isset($params['quota_mb']) ? max(64, min(102400, (int) $params['quota_mb'])) : null], fn ($v) => $v !== null)],
            'alias.create' => ['source' => strtolower($need('source', '/^[^@\s]{1,64}@[^@\s]{3,253}$/', 'source must be an e-mail address')), 'destination' => strtolower($need('destination', '/^[^@\s]{1,64}@[^@\s]{3,253}$/', 'destination must be an e-mail address'))],
            'sending.set' => ['enabled' => filter_var($params['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN)],
            'errpages.set' => ['enabled' => filter_var($params['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN)],
            'directives.set' => (function () use ($params, $action, $service, $features) {
                $kinds = (array) ($features->features($service)['directives']['options'] ?? []);
                $kind = (string) ($params['kind'] ?? ($kinds[0] ?? ''));
                if (! in_array($kind, $kinds, true)) {
                    throw new DomainError('action_param_invalid', "{$action}: kind must be one of ".implode(', ', $kinds).'.', 422, ['field' => 'kind']);
                }
                $content = str_replace("\r\n", "\n", (string) ($params['content'] ?? ''));
                if (strlen($content) > 20000) {
                    throw new DomainError('action_param_invalid', "{$action}: content may have at most 20 kB.", 422, ['field' => 'content']);
                }
                // customer directives may tune their own vhost, never reach outside it or load code into the server
                if (preg_match('/^\s*(Include|IncludeOptional|LoadModule|SetHandler|php_admin_(value|flag)|ProxyPass|proxy_pass|include|load_module|fastcgi_pass|user|root\s+\/(etc|var\/lib|root|proc|sys))\b/mi', $content)) {
                    throw new DomainError('action_param_invalid', "{$action}: directives may not include files, load modules, change handlers or point outside the site.", 422, ['field' => 'content']);
                }

                return ['kind' => $kind, 'content' => $content];
            })(),
            'folder.protect' => (function () use ($need, $password, $params, $service, $features) {
                $mode = $features->features($service)['protected']['options'] ?? 'folders';
                $path = $mode === 'site' ? '/' : '/'.trim(preg_replace('~[^\w\/.-]~', '', str_replace('\\', '/', (string) ($params['path'] ?? '/'))) ?? '', '/');
                if (str_contains($path, '..')) {
                    throw new DomainError('action_param_invalid', 'folder.protect: path must stay inside the site.', 422, ['field' => 'path']);
                }

                return ['path' => $path, 'user' => $need('user', '/^[a-z0-9_.-]{2,32}$/i', 'user may contain letters, digits, dots, dashes and underscores (2–32)'), 'password' => $password()];
            })(),
            'folder.unprotect', 'dbuser.delete', 'shell.delete' => ['remote_id' => $remote()],
            'dbuser.create' => (function () use ($need, $password, $service) {
                return ['user' => ServiceFeatures::scopedName($service, $need('user', '/^[a-z0-9_]{1,16}$/i', 'user may contain letters, digits and underscores (max 16)')), 'password' => $password()];
            })(),
            'dbuser.password' => ['remote_id' => $remote(), 'password' => $password()],
            'shell.create' => (function () use ($need, $password, $params, $service, $limit) {
                $limit('shell', 'shell_users');
                $key = trim((string) ($params['ssh_key'] ?? ''));
                if ($key !== '' && ! preg_match('/^(ssh-(rsa|ed25519)|ecdsa-sha2-nistp(256|384|521)) [A-Za-z0-9+\/=]{40,}( [^\r\n]{0,120})?$/', $key)) {
                    throw new DomainError('action_param_invalid', 'shell.create: ssh_key must be an OpenSSH public key (ssh-ed25519, ssh-rsa or ecdsa).', 422, ['field' => 'ssh_key']);
                }

                return ['user' => ServiceFeatures::scopedName($service, $need('user', '/^[a-z0-9_-]{2,16}$/i', 'user may contain letters, digits, dashes and underscores (2–16)')), 'password' => $password(), 'ssh_key' => $key !== '' ? $key : null];
            })(),
            'shell.key' => (function () use ($remote, $params) {
                $key = trim((string) ($params['ssh_key'] ?? ''));
                if ($key !== '' && ! preg_match('/^(ssh-(rsa|ed25519)|ecdsa-sha2-nistp(256|384|521)) [A-Za-z0-9+\/=]{40,}( [^\r\n]{0,120})?$/', $key)) {
                    throw new DomainError('action_param_invalid', 'shell.key: ssh_key must be an OpenSSH public key (ssh-ed25519, ssh-rsa or ecdsa); empty removes it.', 422, ['field' => 'ssh_key']);
                }

                return ['remote_id' => $remote(), 'ssh_key' => $key];
            })(),
            'stats.set' => (function () use ($params, $action) {
                $type = strtolower((string) ($params['type'] ?? 'awstats'));
                if (! in_array($type, ['awstats', 'goaccess', 'webalizer', 'none'], true)) {
                    throw new DomainError('action_param_invalid', "{$action}: type must be awstats, goaccess, webalizer or none.", 422, ['field' => 'type']);
                }
                $pw = (string) ($params['password'] ?? '');
                if ($pw !== '' && (strlen($pw) < 8 || strlen($pw) > 72)) {
                    throw new DomainError('action_param_invalid', "{$action}: password must have 8–72 characters.", 422, ['field' => 'password']);
                }

                return ['type' => $type, 'password' => $pw !== '' ? $pw : null];
            })(),
            'ssl.upload' => (function () use ($params, $action) {
                $cert = trim((string) ($params['cert'] ?? ''));
                $key = trim((string) ($params['key'] ?? ''));
                $chain = trim((string) ($params['chain'] ?? ''));
                if (! str_contains($cert, '-----BEGIN CERTIFICATE-----') || strlen($cert) > 20000) {
                    throw new DomainError('action_param_invalid', "{$action}: cert must be a PEM certificate.", 422, ['field' => 'cert']);
                }
                if (! preg_match('/-----BEGIN (RSA |EC |ENCRYPTED )?PRIVATE KEY-----/', $key) || strlen($key) > 20000) {
                    throw new DomainError('action_param_invalid', "{$action}: key must be a PEM private key.", 422, ['field' => 'key']);
                }
                if ($chain !== '' && (! str_contains($chain, '-----BEGIN CERTIFICATE-----') || strlen($chain) > 40000)) {
                    throw new DomainError('action_param_invalid', "{$action}: chain must be PEM certificates.", 422, ['field' => 'chain']);
                }

                return ['cert' => $cert, 'key' => $key, 'chain' => $chain !== '' ? $chain : null];
            })(),
            'file.mkdir', 'file.delete', 'file.save' => (function () use ($params, $action) {
                $path = trim(str_replace('\\', '/', (string) ($params['path'] ?? '')), '/');
                if ($path === '' || strlen($path) > 500 || str_contains($path, "\0") || preg_match('~(^|/)\.\.?(/|$)~', $path)) {
                    throw new DomainError('action_param_invalid', "{$action}: path must be a relative path inside the site root.", 422, ['field' => 'path']);
                }
                $out = ['path' => $path];
                if ($action === 'file.delete') {
                    $out['directory'] = filter_var($params['directory'] ?? false, FILTER_VALIDATE_BOOLEAN);
                }
                if ($action === 'file.save') {
                    $content = (string) ($params['content'] ?? '');
                    if (strlen($content) > 512 * 1024) {
                        throw new DomainError('action_param_invalid', "{$action}: content may have at most 512 kB (use SFTP for larger files).", 422, ['field' => 'content']);
                    }
                    $out['content'] = $content;
                }

                return $out;
            })(),
            'app.install' => ['name' => $need('name', '/^[A-Za-z0-9 ._-]{2,40}$/', 'name is required'), 'php_version' => isset($params['php_version']) && $params['php_version'] !== '' ? $need('php_version', '/^\d\.\d$/', 'php_version must look like 8.3') : null],
            // ── tools on top of the panel ──────────────────────────────────────────────────────────────────────
            'command.run' => (function () use ($params, $action) {
                $command = trim((string) ($params['command'] ?? ''));
                if ($command === '' || strlen($command) > 2000 || preg_match('/[\r\n]/', $command)) {
                    throw new DomainError('action_param_invalid', "{$action}: command is required (one line, max 2000 characters).", 422, ['field' => 'command']);
                }
                $cwd = trim(preg_replace('~[^\w\/.-]~', '', str_replace('\\', '/', (string) ($params['cwd'] ?? ''))) ?? '', '/');
                if (str_contains($cwd, '..')) {
                    throw new DomainError('action_param_invalid', "{$action}: cwd must stay inside the site root.", 422, ['field' => 'cwd']);
                }

                return ['command' => CommandRunner::guard($command), 'cwd' => $cwd, 'timeout' => max(5, min(300, (int) ($params['timeout'] ?? 120)))];
            })(),
            'php.settings' => (function () use ($params, $action) {
                $settings = [];
                foreach ((array) ($params['settings'] ?? []) as $key => $value) {
                    if (! in_array((string) $key, SecurityRules::PHP_EDITABLE, true)) {
                        throw new DomainError('action_param_invalid', "{$action}: {$key} is not an editable PHP setting (".implode(', ', SecurityRules::PHP_EDITABLE).').', 422, ['field' => 'settings']);
                    }
                    $settings[(string) $key] = substr(trim((string) $value), 0, 64);
                }
                if ($settings === []) {
                    throw new DomainError('action_param_invalid', "{$action}: at least one setting is required.", 422, ['field' => 'settings']);
                }
                $limit = (int) ($service->entitlements['php_memory_mb'] ?? 0);
                if ($limit > 0 && isset($settings['memory_limit']) && preg_match('/^(\d+)\s*([KMG]?)$/i', $settings['memory_limit'], $m)) {
                    $mb = (int) $m[1] * match (strtoupper($m[2])) {
                        'G' => 1024, 'K' => 1 / 1024, default => 1
                    };
                    if ($mb > $limit) {
                        throw new DomainError('action_param_invalid', "{$action}: memory_limit may not exceed the plan's {$limit} MB.", 422, ['field' => 'settings']);
                    }
                }

                return ['settings' => $settings];
            })(),
            'security.set' => ['rules' => SecurityRules::normalize((array) ($params['rules'] ?? $params))],
            'http3.set' => ['enabled' => filter_var($params['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN)],
            'cron.update' => (function () use ($remote, $params, $action) {
                $out = ['remote_id' => $remote()];
                if (isset($params['schedule']) && $params['schedule'] !== '') {
                    if (! preg_match('/^(\S+\s+){4}\S+$/', trim((string) $params['schedule']))) {
                        throw new DomainError('action_param_invalid', "{$action}: schedule must have five cron fields.", 422, ['field' => 'schedule']);
                    }
                    $out['schedule'] = trim((string) $params['schedule']);
                }
                if (isset($params['command']) && $params['command'] !== '') {
                    $command = trim((string) $params['command']);
                    if (strlen($command) > 500 || preg_match('/[\r\n]/', $command)) {
                        throw new DomainError('action_param_invalid', "{$action}: command must be one line (max 500 characters).", 422, ['field' => 'command']);
                    }
                    $out['command'] = $command;
                }
                if (isset($params['label'])) {
                    $out['label'] = substr(trim((string) $params['label']), 0, 40) ?: null;
                }
                if (array_key_exists('active', $params)) {
                    $out['active'] = filter_var($params['active'], FILTER_VALIDATE_BOOLEAN);
                }

                return $out;
            })(),
            'cron.run', 'database.export', 'backup.delete', 'mailbox.backup' => ['remote_id' => $remote()],
            'database.import' => ['remote_id' => $remote(), 'upload_id' => $need('upload_id', '/^up_[a-z0-9]{20}(\.[a-z0-9.]{1,12})?$/', 'upload_id is required (upload the SQL file first)')],
            'database.access' => (function () use ($remote, $params, $action) {
                $hosts = [];
                foreach ((array) ($params['hosts'] ?? []) as $host) {
                    $host = trim((string) $host);
                    if ($host !== '' && filter_var(explode('/', $host)[0], FILTER_VALIDATE_IP) === false) {
                        throw new DomainError('action_param_invalid', "{$action}: hosts must be IP addresses.", 422, ['field' => 'hosts']);
                    }
                    if ($host !== '') {
                        $hosts[] = $host;
                    }
                }

                return ['remote_id' => $remote(), 'remote' => filter_var($params['remote'] ?? false, FILTER_VALIDATE_BOOLEAN), 'hosts' => array_values(array_unique($hosts))];
            })(),
            'file.rename', 'file.copy' => (function () use ($params, $action) {
                $clean = function (string $key) use ($params, $action): string {
                    $path = trim(str_replace('\\', '/', (string) ($params[$key] ?? '')), '/');
                    if ($path === '' || strlen($path) > 500 || str_contains($path, "\0") || preg_match('~(^|/)\.\.?(/|$)~', $path)) {
                        throw new DomainError('action_param_invalid', "{$action}: {$key} must be a relative path inside the site root.", 422, ['field' => $key]);
                    }

                    return $path;
                };

                return ['from' => $clean('from'), 'to' => $clean('to')];
            })(),
            'file.chmod' => (function () use ($params, $action) {
                $path = trim(str_replace('\\', '/', (string) ($params['path'] ?? '')), '/');
                if ($path === '' || str_contains($path, "\0") || preg_match('~(^|/)\.\.?(/|$)~', $path)) {
                    throw new DomainError('action_param_invalid', "{$action}: path must be a relative path inside the site root.", 422, ['field' => 'path']);
                }
                $mode = (string) ($params['mode'] ?? '');
                if (! preg_match('/^0?[0-7]{3}$/', $mode) || octdec($mode) < 0400) {
                    throw new DomainError('action_param_invalid', "{$action}: mode must be an octal permission such as 644 or 755.", 422, ['field' => 'mode']);
                }

                return ['path' => $path, 'mode' => octdec($mode)];
            })(),
            'file.archive' => (function () use ($params, $action) {
                $paths = [];
                foreach ((array) ($params['paths'] ?? []) as $p) {
                    $p = trim(str_replace('\\', '/', (string) $p), '/');
                    if ($p === '' || str_contains($p, "\0") || preg_match('~(^|/)\.\.?(/|$)~', $p)) {
                        throw new DomainError('action_param_invalid', "{$action}: paths must be relative paths inside the site root.", 422, ['field' => 'paths']);
                    }
                    $paths[] = $p;
                }
                $target = trim(str_replace('\\', '/', (string) ($params['target'] ?? '')), '/');
                if ($paths === [] || ! preg_match('/^[\w\/.-]+\.(zip|tar\.gz|tgz)$/i', $target) || str_contains($target, '..')) {
                    throw new DomainError('action_param_invalid', "{$action}: paths and a target archive (.zip or .tar.gz) are required.", 422, ['field' => 'target']);
                }

                return ['paths' => $paths, 'target' => $target];
            })(),
            'file.extract' => (function () use ($params, $action) {
                $archive = trim(str_replace('\\', '/', (string) ($params['path'] ?? '')), '/');
                $target = trim(str_replace('\\', '/', (string) ($params['target'] ?? dirname($archive))), '/');
                if (! preg_match('/^[\w\/.-]+\.(zip|tar\.gz|tgz)$/i', $archive) || str_contains($archive, '..') || str_contains($target, '..') || preg_match('/[^\w\/.-]/', $target)) {
                    throw new DomainError('action_param_invalid', "{$action}: path must name a .zip or .tar.gz inside the site root.", 422, ['field' => 'path']);
                }

                return ['path' => $archive, 'target' => $target === '.' ? '' : $target];
            })(),
            'proxy.create' => (function () use ($need, $params, $action, $hostname) {
                $target = trim((string) ($params['target'] ?? ''));
                if (! preg_match('~^https?://[A-Za-z0-9.\-]+(:\d{2,5})?(/[^\s]*)?$~', $target)) {
                    throw new DomainError('action_param_invalid', "{$action}: target must be an http(s) URL of the upstream, e.g. http://127.0.0.1:3000.", 422, ['field' => 'target']);
                }
                $path = '/'.trim(preg_replace('~[^\w\/.-]~', '', str_replace('\\', '/', (string) ($params['path'] ?? '/'))) ?? '', '/');

                return ['name' => $need('name', '/^[a-z0-9][a-z0-9_-]{1,30}$/i', 'name may contain letters, digits, dashes and underscores (2–31)'), 'target' => $target, 'path' => $path, 'cache' => filter_var($params['cache'] ?? false, FILTER_VALIDATE_BOOLEAN), 'host' => isset($params['host']) && preg_match($hostname, (string) $params['host']) ? strtolower((string) $params['host']) : null];
            })(),
            'proxy.delete' => ['remote_id' => $remote()],
            'proxies.set' => (function () use ($params, $action, $hostname) { // the whole list (automation): every item obeys the proxy.create rules, names unique, at most 20
                $items = [];
                foreach (array_values((array) ($params['items'] ?? [])) as $i => $item) {
                    $item = (array) $item;
                    $name = trim((string) ($item['name'] ?? ''));
                    $target = trim((string) ($item['target'] ?? ''));
                    if (! preg_match('/^[a-z0-9][a-z0-9_-]{1,30}$/i', $name) || ! preg_match('~^https?://[A-Za-z0-9.\-]+(:\d{2,5})?(/[^\s]*)?$~', $target)) {
                        throw new DomainError('action_param_invalid', "{$action}: item {$i} needs a name (letters, digits, dashes, underscores; 2–31) and an http(s) upstream URL.", 422, ['field' => "items.{$i}"]);
                    }
                    if (isset($items[strtolower($name)])) {
                        throw new DomainError('action_param_invalid', "{$action}: the name {$name} is used twice.", 422, ['field' => "items.{$i}.name"]);
                    }
                    $path = '/'.trim(preg_replace('~[^\w\/.-]~', '', str_replace('\\', '/', (string) ($item['path'] ?? '/'))) ?? '', '/');
                    $items[strtolower($name)] = ['name' => $name, 'target' => $target, 'path' => $path, 'cache' => filter_var($item['cache'] ?? false, FILTER_VALIDATE_BOOLEAN), 'host' => isset($item['host']) && preg_match($hostname, (string) $item['host']) ? (string) $item['host'] : null];
                }
                if (count($items) > 20) {
                    throw new DomainError('action_param_invalid', "{$action}: at most 20 proxies per site.", 422, ['field' => 'items']);
                }

                return ['items' => array_values($items)];
            })(),
            'index.set' => (function () use ($params, $action) {
                $given = array_values(array_filter(array_map(fn ($n) => trim((string) $n), (array) ($params['names'] ?? [])), fn ($n) => $n !== ''));
                $names = array_values(array_unique(array_filter($given, fn ($n) => preg_match('/^[A-Za-z0-9._-]{1,60}$/', $n) === 1)));
                // an empty list means "the web server's default order" (the managed list is dropped); a list of only bad names is refused
                if (($given !== [] && $names === []) || count($names) > 12) {
                    throw new DomainError('action_param_invalid', "{$action}: give up to 12 file names such as index.php, index.html (an empty list restores the default).", 422, ['field' => 'names']);
                }

                return ['names' => $names];
            })(),
            'node.create' => (function () use ($need, $params, $action) {
                $env = [];
                foreach ((array) ($params['env'] ?? []) as $k => $v) {
                    if (preg_match('/^[A-Z_][A-Z0-9_]*$/', (string) $k)) {
                        $env[(string) $k] = substr((string) $v, 0, 500);
                    }
                }
                $domains = array_values(array_filter(array_map(fn ($d) => strtolower(trim((string) $d)), (array) ($params['domains'] ?? [])), fn ($d) => preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $d) === 1));
                $port = (int) ($params['port'] ?? 0);
                if ($port < 1024 || $port > 65535) {
                    throw new DomainError('action_param_invalid', "{$action}: port must be between 1024 and 65535.", 422, ['field' => 'port']);
                }

                return ['name' => $need('name', '/^[a-z0-9][a-z0-9_-]{1,30}$/i', 'name may contain letters, digits, dashes and underscores (2–31)'), 'path' => trim(preg_replace('~[^\w\/.-]~', '', str_replace('\\', '/', (string) ($params['path'] ?? ''))) ?? '', '/'), 'script' => $need('script', '/^[^\r\n]{1,300}$/', 'script (start command or entry file) is required'), 'port' => $port, 'version' => isset($params['version']) ? preg_replace('/[^\d.v]/', '', (string) $params['version']) : null, 'domains' => $domains, 'env' => $env];
            })(),
            'node.action' => ['remote_id' => $remote(), 'op' => (function () use ($params, $action) {
                $op = (string) ($params['op'] ?? '');
                if (! in_array($op, ['start', 'stop', 'restart', 'delete'], true)) {
                    throw new DomainError('action_param_invalid', "{$action}: op must be start, stop, restart or delete.", 422, ['field' => 'op']);
                }

                return $op;
            })()],
            'ssl.wildcard' => ['domain' => isset($params['domain']) && $params['domain'] !== '' ? strtolower($need('domain', $hostname, 'domain must be a valid host name')) : strtolower((string) $service->spec('domain', $service->hostname))],
            // ── platform features (workflows of their own) ────────────────────────────────────────────────────
            'staging.create', 'staging.refresh', 'staging.delete' => ['databases' => filter_var($params['databases'] ?? true, FILTER_VALIDATE_BOOLEAN)],
            'staging.push' => (function () use ($params, $action) {
                if (! filter_var($params['confirm'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    throw new DomainError('action_param_invalid', "{$action}: confirm must be true — production files are replaced by the staging copy.", 422, ['field' => 'confirm']);
                }

                return ['databases' => filter_var($params['databases'] ?? true, FILTER_VALIDATE_BOOLEAN), 'confirm' => true];
            })(),
            'deploy.run' => [
                'ref' => isset($params['ref']) && $params['ref'] !== '' ? $need('ref', '/^[A-Za-z0-9._\/-]{1,120}$/', 'ref must be a branch, tag or commit') : null,
                'trigger' => in_array((string) ($params['trigger'] ?? ''), ['manual', 'webhook'], true) ? (string) $params['trigger'] : 'manual', // the webhook endpoint marks pushes; customers deploy by hand
                'message' => mb_substr(trim((string) ($params['message'] ?? '')), 0, 250), 'author' => mb_substr(trim((string) ($params['author'] ?? '')), 0, 120),
            ],
            'deploy.rollback' => ['deployment_id' => $need('deployment_id', '/^dpl_[A-Za-z0-9]{10,40}$/', 'deployment_id is required')],
            'wp.update' => (function () use ($params, $action) {
                $what = (string) ($params['what'] ?? 'all');
                if (! in_array($what, ['core', 'plugins', 'themes', 'all'], true)) {
                    throw new DomainError('action_param_invalid', "{$action}: what must be core, plugins, themes or all.", 422, ['field' => 'what']);
                }

                return ['what' => $what, 'staged' => filter_var($params['staged'] ?? true, FILTER_VALIDATE_BOOLEAN)];
            })(),
            'wp.cache' => ['enabled' => filter_var($params['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN)],
            'wp.install' => (function () use ($need, $params, $action, $service) {
                $title = trim((string) ($params['title'] ?? ''));
                if ($title === '' || mb_strlen($title) > 80) {
                    throw new DomainError('action_param_invalid', "{$action}: title is required (max 80 characters).", 422, ['field' => 'title']);
                }
                $password = (string) ($params['admin_password'] ?? '');
                if ($password !== '' && strlen($password) < 12) {
                    throw new DomainError('action_param_invalid', "{$action}: admin_password must have at least 12 characters.", 422, ['field' => 'admin_password']);
                }
                $locale = (string) ($params['locale'] ?? 'cs_CZ');

                return [
                    'title' => $title, 'admin_user' => isset($params['admin_user']) && $params['admin_user'] !== '' ? $need('admin_user', '/^[a-z0-9_.-]{3,30}$/i', 'admin_user may contain letters, digits, dots, dashes and underscores') : 'admin',
                    'admin_email' => strtolower($need('admin_email', '/^[^@\s]{1,64}@[^@\s]{3,253}$/', 'admin_email must be an e-mail address')), 'admin_password' => $password !== '' ? $password : Str::password(20, symbols: false),
                    'locale' => in_array($locale, ['cs_CZ', 'sk_SK', 'en_US', 'de_DE', 'pl_PL'], true) ? $locale : 'cs_CZ', 'database_id' => isset($params['database_id']) && $params['database_id'] !== '' ? $need('database_id', '/^[A-Za-z0-9:_.-]{1,120}$/', 'database_id must name one of the site databases') : null,
                    'database_password' => isset($params['database_password']) && $params['database_password'] !== '' ? $need('database_password', '/^[^\s\x00-\x1f]{6,128}$/', 'database_password must have 6 to 128 characters without spaces') : null, // a panel-made database whose password the platform does not keep
                    'url' => 'https://'.strtolower((string) $service->spec('domain', $service->hostname)),
                ];
            })(),
            'wp.plugin' => ['slug' => $need('slug', '/^[a-z0-9][a-z0-9-]{1,80}$/', 'slug must be a plugin slug'), 'op' => (function () use ($params, $action) {
                $op = (string) ($params['op'] ?? '');
                if (! in_array($op, ['activate', 'deactivate', 'update', 'install', 'delete'], true)) {
                    throw new DomainError('action_param_invalid', "{$action}: op must be activate, deactivate, update, install or delete.", 422, ['field' => 'op']);
                }

                return $op;
            })()],
            'cdn.enable', 'cdn.disable', 'cdn.purge' => ['settings' => (array) ($params['settings'] ?? [])],
            'import.run' => (function () use ($params, $action) {
                $kind = (string) ($params['kind'] ?? '');
                if (! in_array($kind, ['cpanel', 'plesk', 'url', 'upload'], true)) {
                    throw new DomainError('action_param_invalid', "{$action}: kind must be cpanel, plesk, url or upload.", 422, ['field' => 'kind']);
                }
                $source = trim((string) ($params['source'] ?? ''));
                if ($kind === 'url' ? ! filter_var($source, FILTER_VALIDATE_URL) || ! str_starts_with($source, 'http') : ! preg_match('/^up_[a-z0-9]{20}(\.[a-z0-9.]{1,12})?$/', $source)) {
                    throw new DomainError('action_param_invalid', "{$action}: source must be an https URL (kind url) or an upload_id.", 422, ['field' => 'source']);
                }

                return ['kind' => $kind, 'source' => $source, 'files' => filter_var($params['files'] ?? true, FILTER_VALIDATE_BOOLEAN), 'databases' => filter_var($params['databases'] ?? true, FILTER_VALIDATE_BOOLEAN), 'subdir' => trim(preg_replace('~[^\w\/.-]~', '', (string) ($params['subdir'] ?? '')) ?? '', '/')];
            })(),
            // ── mail tools ────────────────────────────────────────────────────────────────────────────────────
            'forward.create' => ['source' => strtolower($need('source', '/^[^@\s]{1,64}@[^@\s]{3,253}$/', 'source must be an e-mail address')), 'destination' => strtolower($need('destination', '/^[^@\s]{1,64}@[^@\s]{3,253}$/', 'destination must be an e-mail address'))],
            'forward.delete', 'list.delete', 'fetchmail.delete' => ['remote_id' => $remote()],
            'filter.delete' => ['remote_id' => $remote(), 'mailbox_id' => $need('mailbox_id', '/^[A-Za-z0-9:_.-]{1,120}$/', 'mailbox_id is required.')],
            'catchall.set' => ['destination' => isset($params['destination']) && $params['destination'] !== '' ? strtolower($need('destination', '/^[^@\s]{1,64}@[^@\s]{3,253}$/', 'destination must be an e-mail address')) : ''],
            'autoresponder.set' => (function () use ($remote, $params, $action) {
                foreach (['start', 'end'] as $k) {
                    if (! empty($params[$k]) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $params[$k])) {
                        throw new DomainError('action_param_invalid', "{$action}: {$k} must be a date (YYYY-MM-DD).", 422, ['field' => $k]);
                    }
                }

                return ['remote_id' => $remote(), 'enabled' => filter_var($params['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN), 'subject' => substr(trim((string) ($params['subject'] ?? '')), 0, 200), 'text' => substr((string) ($params['text'] ?? ''), 0, 5000), 'start' => $params['start'] ?? null, 'end' => $params['end'] ?? null];
            })(),
            'spam.policy' => ['remote_id' => $remote(), 'policy_id' => $need('policy_id', '/^\d{1,10}$/', 'policy_id is required')],
            'spam.list.add' => ['kind' => (function () use ($params, $action) {
                $kind = (string) ($params['kind'] ?? '');
                if (! in_array($kind, ['whitelist', 'blacklist'], true)) {
                    throw new DomainError('action_param_invalid', "{$action}: kind must be whitelist or blacklist.", 422, ['field' => 'kind']);
                }

                return $kind;
            })(), 'address' => strtolower($need('address', '/^(\*@)?[^@\s]{0,64}@?[^@\s]{3,253}$/', 'address must be an e-mail address or a domain'))],
            'spam.list.delete' => ['kind' => in_array((string) ($params['kind'] ?? ''), ['whitelist', 'blacklist'], true) ? (string) $params['kind'] : 'whitelist', 'remote_id' => $remote()],
            'filter.create' => (function () use ($remote, $need, $params, $action) {
                $source = (string) ($params['source'] ?? 'Subject');
                $op = (string) ($params['op'] ?? 'contains');
                $do = (string) ($params['action'] ?? 'move');
                if (! in_array($source, ['Subject', 'From', 'To', 'List-Id', 'Header'], true) || ! in_array($op, ['contains', 'is', 'begins', 'ends'], true) || ! in_array($do, ['move', 'delete', 'stop'], true)) {
                    throw new DomainError('action_param_invalid', "{$action}: source Subject|From|To|List-Id|Header, op contains|is|begins|ends, action move|delete|stop.", 422, ['field' => 'filter']);
                }

                return ['remote_id' => $remote(), 'name' => $need('name', '/^[^\r\n]{1,60}$/', 'name is required'), 'source' => $source, 'op' => $op, 'term' => $need('term', '/^[^\r\n]{1,120}$/', 'term is required'), 'action' => $do, 'target' => substr(trim((string) ($params['target'] ?? '')), 0, 120)];
            })(),
            'list.create' => ['name' => $need('name', '/^[a-z0-9][a-z0-9_-]{1,40}$/i', 'name may contain letters, digits, dashes and underscores'), 'email' => strtolower($need('email', '/^[^@\s]{1,64}@[^@\s]{3,253}$/', 'email must be an e-mail address')), 'password' => $password()],
            'fetchmail.create' => (function () use ($need, $password, $params, $action) {
                $type = (string) ($params['type'] ?? 'imapssl');
                if (! in_array($type, ['pop3', 'imap', 'pop3ssl', 'imapssl'], true)) {
                    throw new DomainError('action_param_invalid', "{$action}: type must be pop3, imap, pop3ssl or imapssl.", 422, ['field' => 'type']);
                }

                return ['type' => $type, 'host' => strtolower($need('host', '/^[a-z0-9.-]{3,253}$/i', 'host is required')), 'user' => $need('user', '/^[^\r\n\s]{1,120}$/', 'user is required'), 'password' => (string) ($params['password'] ?? '') !== '' ? (string) $params['password'] : $password(), 'destination' => strtolower($need('destination', '/^[^@\s]{1,64}@[^@\s]{3,253}$/', 'destination must be a mailbox')), 'delete' => filter_var($params['delete'] ?? false, FILTER_VALIDATE_BOOLEAN)];
            })(),
            'mailbox.restore' => ['remote_id' => $remote(), 'backup_id' => $need('backup_id', '/^\d{1,12}$/', 'backup_id is required')],
            // ── game tools ─────────────────────────────────────────────────────────────────────────
            'variable.set' => (function () use ($need, $params, $action) {
                $value = (string) ($params['value'] ?? '');
                if (strlen($value) > 1000 || preg_match('/[\r\n]/', $value)) {
                    throw new DomainError('action_param_invalid', "{$action}: value must be one line of at most 1000 characters.", 422, ['field' => 'value']);
                }

                return ['key' => strtoupper($need('key', '/^[A-Z0-9_]{1,64}$/i', 'key must be the variable name (letters, digits, underscores)')), 'value' => $value];
            })(),
            'image.set' => ['image' => $need('image', '~^[a-z0-9][a-z0-9._/:@-]{2,200}$~i', 'image must be a container image reference')],
            'rename' => ['name' => $need('name', '/^[^\r\n<>]{1,60}$/u', 'name is required (max 60 characters)')],
            'reinstall' => ['confirm' => filter_var($params['confirm'] ?? false, FILTER_VALIDATE_BOOLEAN) ?: throw new DomainError('action_param_invalid', "{$action}: confirm=true is required; a reinstall rewrites the server files.", 422, ['field' => 'confirm'])],
            'schedule.delete', 'schedule.run', 'gamedb.rotate', 'gamedb.delete', 'subuser.delete', 'allocation.primary', 'allocation.remove', 'gbackup.delete' => ['remote_id' => $remote()],
            'schedule.toggle' => ['remote_id' => $remote(), 'active' => filter_var($params['active'] ?? true, FILTER_VALIDATE_BOOLEAN)],
            'gamedb.create' => (function () use ($need, $params, $limit, $action) {
                $limit('game_databases', 'game_databases');
                $remoteHost = trim((string) ($params['remote'] ?? '%'));
                if (! preg_match('/^(%|[0-9.%]{1,40}|[a-z0-9.-]{1,120})$/i', $remoteHost)) {
                    throw new DomainError('action_param_invalid', "{$action}: remote must be % (anywhere), an IP or a host name.", 422, ['field' => 'remote']);
                }

                return ['name' => strtolower($need('name', '/^[a-z0-9_]{1,48}$/i', 'name may contain letters, digits and underscores (max 48)')), 'remote' => $remoteHost];
            })(),
            'subuser.create' => (function () use ($need, $params, $limit, $action) {
                $limit('subusers', 'subusers');
                $preset = strtolower(trim((string) ($params['preset'] ?? 'console')));
                $presets = GameToolsProvider::SUBUSER_PRESETS;
                $permissions = isset($params['permissions']) && is_array($params['permissions']) ? array_values(array_filter(array_map(fn ($x) => strtolower(trim((string) $x)), $params['permissions']), fn ($x) => preg_match('/^[a-z-]+\.[a-z-]+$/', $x) === 1)) : ($presets[$preset] ?? null);
                if ($permissions === null || $permissions === []) {
                    throw new DomainError('action_param_invalid', "{$action}: preset must be console, files or full (or give permissions).", 422, ['field' => 'preset']);
                }

                return ['email' => strtolower($need('email', '/^[^@\s]{1,64}@[^@\s]{3,253}$/', 'email must be an e-mail address')), 'permissions' => $permissions];
            })(),
            'gfile.save', 'gfile.upload', 'gfile.delete', 'gfile.mkdir', 'gfile.rename' => (function () use ($params, $action) {
                $clean = function (string $key, bool $required = true) use ($params, $action): string {
                    $value = trim(str_replace('\\', '/', (string) ($params[$key] ?? '')), '/');
                    if (($value === '' && $required) || strlen($value) > 500 || str_contains($value, "\0") || preg_match('~(^|/)\.\.?(/|$)~', $value)) {
                        throw new DomainError('action_param_invalid', "{$action}: {$key} must be a path inside the server directory.", 422, ['field' => $key]);
                    }

                    return $value;
                };
                if ($action === 'gfile.save') {
                    $content = (string) ($params['content'] ?? '');
                    if (strlen($content) > 512 * 1024) {
                        throw new DomainError('action_param_invalid', "{$action}: content may have at most 512 kB (use SFTP for larger files).", 422, ['field' => 'content']);
                    }

                    return ['path' => $clean('path'), 'content' => $content];
                }
                if ($action === 'gfile.upload') { // §5r-3: only a file the upload endpoint staged (and the scanner passed)
                    $tmp = (string) ($params['tmp_path'] ?? '');
                    if (preg_match('~^game-uploads/tmp/[a-z0-9]{24}$~', $tmp) !== 1) {
                        throw new DomainError('action_param_invalid', "{$action}: upload the file through /services/{id}/game-files/upload.", 422, ['field' => 'file']);
                    }

                    return ['directory' => '/'.$clean('directory', false), 'name' => basename($clean('name')), 'tmp_path' => $tmp, 'size' => (int) ($params['size'] ?? 0), 'scan' => (string) ($params['scan'] ?? '')];
                }
                if ($action === 'gfile.rename') {
                    return ['root' => '/'.$clean('root', false), 'from' => basename($clean('from')), 'to' => basename($clean('to'))];
                }

                return ['root' => '/'.$clean('root', false), 'name' => basename($clean('name'))];
            })(),
            'allocation.add' => (function () use ($limit) {
                $limit('allocations', 'allocations');

                return [];
            })(),
            'gbackup.lock' => ['remote_id' => $remote(), 'locked' => filter_var($params['locked'] ?? true, FILTER_VALIDATE_BOOLEAN)],
            'panel.password' => ['password' => $password()],
            default => throw new DomainError('action_unknown', "Unknown action {$action}.", 422),
        } + ['reason' => $params['reason'] ?? null];
    }

    /** @return array{kind:string,url:string,token:string,expires_at:string,meta?:array<string,mixed>} */
    public function consoleAccess(Service $service, CommandContext $context): array
    {
        if (! $service->isActive() && $service->state !== ServiceStateMachine::SUSPENDED) {
            throw new DomainError('service_state_invalid', 'Console is available for active services only.', 409);
        }
        $binding = $service->primaryBinding();
        $instance = $service->provider_instance_id ? ProviderInstance::query()->find($service->provider_instance_id) : null;
        if ($binding === null || $instance === null) {
            throw new DomainError('service_not_provisioned', 'The service has no provider resource yet.', 409);
        }
        $adapter = $this->providers->forInstance($instance);
        if (! $adapter instanceof ConsoleCapable) {
            throw new DomainError('console_unsupported', 'This service type has no console.', 422);
        }
        $access = $adapter->consoleAccess($binding->ref());
        $this->audit->record($context->withScope($service->organization_id), 'service.console', 'succeeded', ['kind' => $access['kind'], 'expires_at' => $access['expires_at']], 'service', $service->id);

        return $access;
    }

    public function usage(Service $service): Usage
    {
        $binding = $service->primaryBinding();
        $instance = $service->provider_instance_id ? ProviderInstance::query()->find($service->provider_instance_id) : null;
        if ($binding === null || $instance === null) {
            throw new DomainError('service_not_provisioned', 'The service has no provider resource yet.', 409);
        }
        try {
            $adapter = $this->providers->forInstance($instance);
        } catch (DomainError $e) {
            throw $e;
        } catch (\Throwable $e) { // no credentials stored for the panel yet, or the adapter cannot be built: a clear refusal, never a 500 to the customer
            throw new DomainError('executor_unavailable', 'The panel behind this service is not reachable right now; usage is available once it answers again.', 409);
        }
        if (! $adapter instanceof InfrastructureProvider) {
            throw new DomainError('usage_unsupported', 'This service type reports no usage.', 422);
        }

        return $adapter->usage($binding->ref());
    }

    public function transition(Service $service, string $to, CommandContext $context, ?string $note = null): Service
    {
        ServiceStateMachine::machine()->assertTransition($service->state, $to);
        $from = $service->state;
        $service->forceFill(['state' => $to])->save();
        $this->audit->record($context->withScope($service->organization_id), 'service.transition', 'succeeded', ['from' => $from, 'to' => $to, 'note' => $note], 'service', $service->id);

        return $service;
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * Order item options (configurator sliders, per-item add-ons) applied on top of plan entitlements. An option's
     * `meta.entitlement` may name the entitlement key, whether a slider is absolute (configurator: "3 webs") or extra
     * (add-on: "+10 GB"), a value map for selects and the value a switch sets; the arms below cover the seeded keys.
     */
    /** The entitlements a plan version yields with the customer's priced options (sliders / add-ons) applied. */
    public function entitlementsFor(PlanVersion $version, array $options, ?Product $product = null): array
    {
        return $this->applyOptions((array) $version->entitlements, $options, $product);
    }

    private function applyOptions(array $entitlements, array $options, ?Product $product = null): array
    {
        $defs = $product?->options?->keyBy('key') ?? collect();
        foreach ($options as $key => $value) {
            $def = $defs->get($key);
            $rule = (array) data_get($def?->meta, 'entitlement', []);
            $target = (string) ($rule['key'] ?? $key);
            if (isset($rule['values']) && is_array($rule['values']) && array_key_exists((string) $value, $rule['values'])) {
                $entitlements[$target] = $rule['values'][(string) $value];

                continue;
            }
            if (($def?->kind ?? null) === 'addon' && array_key_exists('value', $rule)) {
                if ($value) {
                    $entitlements[$target] = $rule['value'];
                }

                continue;
            }
            if (($rule['mode'] ?? 'extra') === 'absolute' && is_numeric($value)) {
                $entitlements[$target] = (int) $value;

                continue;
            }
            match ($key) {
                'vcpu' => $entitlements['vcpu'] = (int) ($entitlements['vcpu'] ?? 0) + (int) $value,
                'ram_gb' => $entitlements['ram_mb'] = (int) ($entitlements['ram_mb'] ?? 0) + (int) $value * 1024,
                'nvme_gb', 'disk_gb' => $entitlements['nvme_gb'] = (int) ($entitlements['nvme_gb'] ?? 0) + (int) $value,
                'ipv4' => $entitlements['ipv4'] = $value ? 1 : ($entitlements['ipv4'] ?? 'addon'),
                'backup' => $entitlements['backup'] = $value ? 'daily' : ($entitlements['backup'] ?? 'addon'),
                'sites', 'mailboxes', 'databases', 'php_workers' => $entitlements[$key] = (int) ($entitlements[$key] ?? 0) + (int) $value,
                'backup_days' => $entitlements['backup_days'] = (int) (['backup-7' => 7, 'backup-30' => 30, 'backup-90' => 90][(string) $value] ?? (is_numeric($value) ? (int) $value : ($entitlements['backup_days'] ?? 7))),
                'staging', 'ssh' => $entitlements[$key] = (bool) $value || (bool) ($entitlements[$key] ?? false),
                'waf_cdn' => $entitlements['waf'] = $value ? 'pro + CDN' : ($entitlements['waf'] ?? 'basic'),
                'dedicated_ipv4' => $entitlements['ipv4'] = $value ? 1 : ($entitlements['ipv4'] ?? 0),
                'priority_support' => $entitlements['support'] = $value ? 'priority 10 min' : ($entitlements['support'] ?? 'standard'),
                default => $entitlements['options'][$key] = $value,
            };
        }

        return $entitlements;
    }

    /** @return array<string,mixed> */
    private function desiredSpec(Service $service, Product $product, ?PlanVersion $version, array $config, Organization $organization, array $limits): array
    {
        $meta = (array) $product->meta;
        $shortId = strtolower(substr($service->id, -8));
        // Where the plan runs: an operator placement (panel + server) overrides the product's default executor, so one
        // product can sell plans on different panels; without a placement the scheduler picks by role and region.
        $placement = app(PlacementService::class)->resolve($product->key, $version?->plan?->key, $service->region_code);
        $base = [
            'product_key' => $product->key, 'plan_key' => $version?->plan?->key, 'family' => $product->family, 'executor' => $placement?->providerInstance?->provider ?? $product->executor, 'region' => $service->region_code, 'sla_class' => $service->sla_class,
            'placement' => $placement === null ? null : ['id' => $placement->id, 'instance_id' => $placement->provider_instance_id, 'instance_key' => $placement->providerInstance?->key, 'node_id' => $placement->node_id],
            'entitlements' => $service->entitlements, 'limits' => $limits, 'contact_email' => $organization->billing_email ?: ($organization->owner?->email ?? null), 'contact_name' => $organization->name, 'organization_name' => $organization->name,
        ];
        $specific = match ($product->family) {
            'cloud', 'data' => [
                'hostname' => Str::lower((string) ($config['hostname'] ?? "vm-{$shortId}.".config('onhost.provisioning.hostname_suffix', 'cust.onhost.cz'))), 'image' => (string) ($config['image'] ?? ($product->family === 'data' ? (string) ($config['engine'] ?? ($meta['engines'][0] ?? 'postgresql-16')) : ($meta['images'][0] ?? 'debian-13'))),
                'engine' => $config['engine'] ?? ($product->family === 'data' ? ($meta['engines'][0] ?? null) : null), 'ssh_keys' => array_values((array) ($config['ssh_keys'] ?? [])), 'admin_user' => (string) ($config['admin_user'] ?? 'onhost'), 'firewall' => $config['firewall'] ?? config('onhost.provisioning.default_firewall', []),
            ],
            'web', 'managed' => ['domain' => Str::lower((string) ($config['fqdn'] ?? $config['domain'] ?? "{$shortId}.".config('onhost.provisioning.web_preview_suffix', 'web.onhost.cz'))), 'php_version' => (string) ($config['php_version'] ?? '8.3'), 'aliases' => array_values((array) ($config['aliases'] ?? []))],
            'mail' => ['domain' => Str::lower((string) ($config['fqdn'] ?? $config['domain'] ?? '')), 'mailboxes' => (array) ($config['mailboxes'] ?? [])],
            'game' => (function () use ($config, $meta) { // the wizard's "system image" of a game server is the template key
                $eggs = array_values(array_map('strval', (array) ($meta['eggs'] ?? [])));
                $wanted = (string) ($config['egg'] ?? $config['image'] ?? '');
                $egg = $wanted !== '' && ($eggs === [] || in_array($wanted, $eggs, true)) ? $wanted : ($eggs[0] ?? 'minecraft-paper');

                return ['egg' => $egg, 'environment' => array_merge((array) ($config['environment'] ?? []), ! empty($config['version']) ? ['MINECRAFT_VERSION' => (string) $config['version']] : []), 'port' => $config['port'] ?? null]; // §5o: the version the customer or staff chose
            })(),
            'apps' => ['app' => array_merge(['name' => Str::slug((string) ($config['app']['name'] ?? $config['label'] ?? "app-{$shortId}")), 'runtime' => $meta['runtimes'][0] ?? 'node-22', 'port' => 8080, 'git_branch' => 'main', 'healthcheck_path' => '/'], (array) ($config['app'] ?? []))],
            default => [],
        };
        if (($specific['domain'] ?? null) === '' && $product->family === 'mail') {
            throw new DomainError('mail_domain_required', 'Mail hosting needs a domain.', 422);
        }

        return array_merge($base, $specific);
    }

    private function checkOrderCompletion(OrderItem $item, CommandContext $context): void
    {
        $this->fulfilment->recheck($item->order_id, $context);
    }
}
