<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\ResourceDrift;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\ResourceSpec;
use Throwable;

/**
 * Desired vs. actual reconciliation (blueprint §5.6): critical services every 5 min,
 * the rest every 15. Drift is classified by the adapter; EXPECTED is ignored,
 * AUTO_REPAIRABLE is repaired through an operation, REQUIRES_APPROVAL and
 * SECURITY_SUSPICIOUS open a drift record and an alert — never a silent overwrite.
 */
final class Reconciler
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly OperationService $operations,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    /** @return array{checked:int, drifted:int, missing:int, repaired:int, errors:int} */
    public function run(string $tier = 'normal', int $limit = 200, ?CommandContext $context = null): array
    {
        $context ??= CommandContext::system("reconcile {$tier}");
        $minutes = (int) config($tier === 'critical' ? 'onhost.provisioning.reconcile.critical_minutes' : 'onhost.provisioning.reconcile.normal_minutes', 15);
        $query = Service::query()->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED, ServiceStateMachine::SUSPENDED])
            ->whereNotNull('provider_instance_id')->whereHas('bindings')
            ->where(fn ($q) => $q->whereNull('last_reconciled_at')->orWhere('last_reconciled_at', '<', now()->subMinutes($minutes)))
            ->orderBy('last_reconciled_at')->limit($limit);
        if ($tier === 'critical') {
            $query->where('sla_class', '!=', 'standard');
        }
        $stats = ['checked' => 0, 'drifted' => 0, 'missing' => 0, 'repaired' => 0, 'errors' => 0];
        foreach ($query->get() as $service) {
            $stats['checked']++;
            try {
                $this->reconcileService($service, $context, $stats);
            } catch (ProviderException $e) {
                $stats['errors']++;
                $service->forceFill(['health' => array_replace((array) $service->health, ['status' => 'unknown', 'error' => $e->getMessage(), 'checked_at' => now()->toISOString()])])->save();
            } catch (Throwable $e) {
                $stats['errors']++;
                report($e);
            }
        }

        return $stats;
    }

    public function reconcileService(Service $service, CommandContext $context, array &$stats = []): void
    {
        $instance = ProviderInstance::query()->find($service->provider_instance_id);
        $binding = $service->primaryBinding();
        if ($instance === null || $binding === null || ! in_array($instance->state, ['active', 'draining', 'maintenance'], true)) {
            return;
        }
        // a maintenance lock (H322): the panel is still read and every difference recorded, but nothing is repaired,
        // degraded or recovered on the strength of what a panel under maintenance answers
        $observeOnly = $instance->state === 'maintenance';
        try {
            $adapter = $this->providers->forInstance($instance);
        } catch (\RuntimeException $e) { // credentials missing / secret store unreachable: the health probe reports it, reconcile just skips
            $stats['errors'] = ($stats['errors'] ?? 0) + 1;
            $service->forceFill(['health' => array_replace((array) $service->health, ['status' => 'unknown', 'error' => 'executor credentials unavailable', 'checked_at' => now()->toISOString()])])->save();

            return;
        }
        if (! $adapter instanceof InfrastructureProvider) {
            return;
        }
        $actual = $adapter->getActualState($binding->ref());
        $service->forceFill(['actual_spec' => $actual->attributes, 'last_reconciled_at' => now()])->save();
        $binding->forceFill(['last_reconciled_at' => now()])->save();

        if (! $actual->exists) {
            $stats['missing'] = ($stats['missing'] ?? 0) + 1;
            $this->openDrift($service, 'existence', 'present', 'missing', 'ONHOST_MANAGED', $observeOnly ? 'REQUIRES_APPROVAL' : 'SECURITY_SUSPICIOUS', $context);
            if ($service->state !== ServiceStateMachine::DEGRADED && ! $observeOnly) {
                $service->forceFill(['state' => ServiceStateMachine::DEGRADED, 'health' => ['status' => 'missing', 'checked_at' => now()->toISOString()]])->save();
                $this->outbox->publish(GenericEvent::of('service.degraded', 'service', $service->id, ['reason' => 'resource missing at provider', 'binding' => $binding->remote_type.':'.$binding->remote_id], $service->organization_id));
            }

            return;
        }
        $spec = new ResourceSpec($service->id, $this->kindFor($service), "reconcile:{$service->id}", array_replace((array) $service->desired_spec, ['entitlements' => $service->entitlements]), $binding->remote_node, $service->region_code, $service->organization_id);
        $plan = $adapter->reconcile($spec, $actual);
        $service->forceFill(['health' => array_replace((array) $service->health, ['status' => $actual->status, 'checked_at' => now()->toISOString(), 'drift' => count($plan->drifts), 'observe_only' => $observeOnly ?: null])])->save();
        if ($service->state === ServiceStateMachine::DEGRADED && ! $plan->hasDrift() && ! $observeOnly) {
            $service->forceFill(['state' => ServiceStateMachine::ACTIVE])->save();
            ResourceDrift::query()->where('service_id', $service->id)->where('state', 'open')->update(['state' => 'repaired', 'resolved_at' => now(), 'resolution' => 'resource back in sync']);
            $this->outbox->publish(GenericEvent::of('service.recovered', 'service', $service->id, [], $service->organization_id));
        }
        $autoRepair = [];
        foreach ($plan->drifts as $drift) {
            if (($drift['classification'] ?? '') === 'EXPECTED') {
                continue;
            }
            $stats['drifted'] = ($stats['drifted'] ?? 0) + 1;
            $this->openDrift($service, (string) $drift['field'], $drift['expected'] ?? null, $drift['actual'] ?? null, (string) ($drift['ownership'] ?? 'ONHOST_MANAGED'), (string) ($drift['classification'] ?? 'REQUIRES_APPROVAL'), $context);
            if (($drift['classification'] ?? '') === 'AUTO_REPAIRABLE' && config('onhost.provisioning.auto_repair', true)) {
                $autoRepair[] = $drift['field'];
            }
        }
        if ($autoRepair !== [] && ! $observeOnly && $service->state === ServiceStateMachine::ACTIVE && ! Operation::query()->where('service_id', $service->id)->whereIn('state', [Operation::PENDING, Operation::RUNNING, Operation::WAITING])->exists()) {
            $this->operations->start(ServiceActionWorkflow::class, 'repair:'.$service->id.':'.now()->format('YmdH'), ['action' => 'resize', 'entitlements' => $service->entitlements, 'reason' => 'drift repair: '.implode(',', $autoRepair), 'service_id' => $service->id], $context, $service->id, $service->organization_id, null, $service->provider_instance_id);
            $service->forceFill(['state' => ServiceStateMachine::RESIZING])->save();
            $stats['repaired'] = ($stats['repaired'] ?? 0) + 1;
        }
    }

    private function openDrift(Service $service, string $field, mixed $expected, mixed $actual, string $ownership, string $classification, CommandContext $context): void
    {
        $existing = ResourceDrift::query()->where('service_id', $service->id)->where('field', $field)->where('state', 'open')->first();
        if ($existing !== null) {
            $existing->forceFill(['actual' => ['value' => $actual], 'detected_at' => now()])->save();

            return;
        }
        $drift = ResourceDrift::query()->create(['service_id' => $service->id, 'provider_binding_id' => $service->primaryBinding()?->id, 'field' => $field, 'ownership' => $ownership, 'expected' => ['value' => $expected], 'actual' => ['value' => $actual], 'classification' => $classification, 'state' => 'open', 'detected_at' => now()]);
        $this->audit->record($context->withScope($service->organization_id), 'provisioning.drift', 'detected', ['field' => $field, 'classification' => $classification], 'service', $service->id);
        $this->outbox->publish(GenericEvent::of('provisioning.drift.detected', 'service', $service->id, ['drift_id' => $drift->id, 'field' => $field, 'classification' => $classification, 'ownership' => $ownership, 'expected' => $expected, 'actual' => $actual], $service->organization_id));
    }

    private function kindFor(Service $service): string
    {
        return match ($service->family) {
            'cloud', 'data' => 'vm', 'web', 'managed' => 'website', 'game' => 'game_server', 'mail' => 'mail_domain', 'apps' => 'namespace', default => 'resource'
        };
    }
}
