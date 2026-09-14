<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Domain\Provisioning\ServiceMigrationService;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\ComputeProvider;
use Onhost\Providers\Contracts\InfrastructureProvider;

/**
 * Moves a VPS to another node of its cluster (audit §5i-2): the target is named or picked by the scheduler, the
 * hypervisor migrates the VM live when it runs (offline otherwise, local disks included), and the platform switches
 * the binding and the node. Addresses stay the same. A failed migration leaves the VM where it was; the outcome is
 * recorded on the service either way.
 */
final class VpsMigrationWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'vps.migrate';
    }

    public function queue(Operation $operation): string
    {
        return 'provider-proxmox';
    }

    public function steps(Operation $operation): array
    {
        return [$this->targetStep(), $this->migrateStep(), $this->switchStep()];
    }

    public function compensate(StepContext $context): void
    {
        $service = $context->service ?? Service::query()->find($context->operation->service_id);
        if ($service === null || $context->get('swapped') === true) {
            return;
        }
        ServiceMigrationService::markSchedule($service->fresh() ?? $service, 'failed', ['error' => mb_substr((string) ($context->operation->error['message'] ?? 'migration failed'), 0, 200)]);
        $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of('service.migration.failed', 'service', $service->id, ['label' => $service->label ?: $service->name, 'error' => (string) ($context->operation->error['message'] ?? 'migration failed'), 'source_node' => $context->get('source_node_name'), 'target_node' => $context->get('target_node_name')], $service->organization_id));
    }

    private function targetStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Cílový uzel';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $binding = $this->binding($context, 'qemu');
                $sourceNodeName = (string) $binding->remote_node;
                $wanted = trim((string) $context->desired('target_node_id', ''));
                if ($wanted !== '') {
                    $target = Node::query()->where('provider_instance_id', $service->provider_instance_id)->where(fn ($q) => $q->whereKey($wanted)->orWhere('name', $wanted))->first();
                    if ($target === null) {
                        return StepResult::fail("Target node {$wanted} is not a node of this cluster", false);
                    }
                } else {
                    $ent = (array) $service->entitlements;
                    try {
                        $pick = $context->container->make(NodeScheduler::class)->pick(array_filter([
                            'role' => 'compute', 'provider' => 'proxmox', 'region' => $service->region_code, 'ram_mb' => (int) ($ent['ram_mb'] ?? 0), 'cpu_cores' => (int) ($ent['vcpu'] ?? 0), 'disk_gb' => (int) ($ent['nvme_gb'] ?? 0),
                            'exclude_nodes' => array_values(array_filter([$service->node_id, $sourceNodeName])), 'sandbox' => NodeScheduler::sandboxFor($service->organization_id),
                        ], fn ($v) => $v !== null && $v !== [] && $v !== ''));
                    } catch (DomainError $e) {
                        return StepResult::fail($e->getMessage(), true, $e->extra, 900);
                    }
                    $target = $pick['node'];
                    if ($target->provider_instance_id !== $service->provider_instance_id) {
                        return StepResult::fail('The scheduler found room only in another cluster; a VM moves within its cluster', false);
                    }
                }
                if ($target->name === $sourceNodeName) {
                    return StepResult::fail('The VM already runs on the target node', false);
                }
                if ($target->role !== 'compute' || $target->state !== 'active') {
                    return StepResult::fail("Target node {$target->name} is not an active compute node", false);
                }

                return StepResult::done(['source_node_id' => $service->node_id, 'source_node_name' => $sourceNodeName, 'target_node_id' => $target->id, 'target_node_name' => $target->name]);
            }
        };
    }

    private function migrateStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Přesun virtuálního serveru';
            }

            public function run(StepContext $context): StepResult
            {
                $ref = $this->ref($context, 'qemu');
                $online = $this->capability($context, InfrastructureProvider::class)->getActualState($ref)->status === 'running';
                $result = $this->capability($context, ComputeProvider::class)->migrate($ref, (string) $context->get('target_node_name'), $online);

                return $this->settle($result, ['online' => $online]);
            }

            protected function afterAsyncSuccess(StepContext $context, AsyncStatus $status): StepResult
            {
                return StepResult::done(['migrated' => true, 'task' => $status->detail['upid'] ?? null]);
            }
        };
    }

    private function switchStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Přepnutí na nový uzel';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $binding = $this->binding($context, 'qemu');
                $binding->forceFill(['remote_node' => (string) $context->get('target_node_name')])->save();
                $service->forceFill(['node_id' => (string) $context->get('target_node_id')])->save();
                $address = (string) (data_get($service->tags, 'access.ipv4') ?: data_get($service->tags, 'access.address', ''));
                ServiceMigrationService::markSchedule($service, 'finished', ['address' => $address ?: null, 'to_node' => $context->get('target_node_name')]);
                $context->container->make(AuditRecorder::class)->record($context->actor->withScope($service->organization_id), 'service.migrated', 'succeeded', ['from_node' => $context->get('source_node_name'), 'to_node' => $context->get('target_node_name'), 'online' => $context->get('online'), 'operation_id' => $context->operation->id], 'service', $service->id);
                $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of('service.migrated', 'service', $service->id, ['label' => $service->label ?: $service->name, 'address' => $address ?: null, 'from_node' => $context->get('source_node_name'), 'to_node' => $context->get('target_node_name'), 'reason' => $context->desired('reason')], $service->organization_id));

                return StepResult::done(['swapped' => true]);
            }
        };
    }
}
