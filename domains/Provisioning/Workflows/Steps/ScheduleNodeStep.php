<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows\Steps;

use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflows\ServiceStep;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/** Weighted placement with the N+1 sell ratio (blueprint §5.4). Idempotent: an already placed service keeps its node. */
final class ScheduleNodeStep extends ServiceStep
{
    public function __construct(private readonly string $role, private readonly ?string $provider = null) {}

    public function label(): string
    {
        return 'Výběr uzlu';
    }

    public function run(StepContext $context): StepResult
    {
        $service = $this->service($context);
        if ($service->node_id !== null && $service->provider_instance_id !== null) {
            $node = Node::query()->find($service->node_id);

            return StepResult::done(['node_id' => $service->node_id, 'node_name' => $node?->name, 'provider_instance_id' => $service->provider_instance_id, 'region' => $service->region_code]);
        }
        $ent = (array) $service->entitlements;
        try {
            $pick = $context->container->make(NodeScheduler::class)->pick(array_filter([
                'role' => $this->role, 'provider' => $this->provider ?? $context->desired('executor'), 'region' => $service->region_code ?? $context->desired('region'),
                'ram_mb' => (int) ($ent['ram_mb'] ?? 0), 'cpu_cores' => (int) ($ent['vcpu'] ?? 0), 'disk_gb' => (int) ($ent['nvme_gb'] ?? 0),
                'anti_affinity' => (array) $context->desired('anti_affinity', []), 'affinity_failure_domain' => $context->desired('failure_domain'),
                'placement' => (array) $context->desired('placement', []),
                'sandbox' => NodeScheduler::sandboxFor($service->organization_id),
            ], fn ($v) => $v !== null && $v !== [] && $v !== ''));
        } catch (DomainError $e) {
            $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of('capacity.unavailable', 'service', $service->id, ['role' => $this->role, 'region' => $service->region_code, 'error' => $e->error], $service->organization_id));

            return StepResult::fail($e->getMessage(), true, $e->extra, 900); // capacity may free up; retry with backoff instead of failing the order
        }
        $service->forceFill(['node_id' => $pick['node']->id, 'provider_instance_id' => $pick['instance']->id, 'region_code' => $pick['node']->region_code])->save();
        $context->operation->forceFill(['provider_instance_id' => $pick['instance']->id])->save();

        return StepResult::done(['node_id' => $pick['node']->id, 'node_name' => $pick['node']->name, 'node_remote_id' => $pick['node']->remote_id, 'provider_instance_id' => $pick['instance']->id, 'region' => $pick['node']->region_code, 'placement_score' => $pick['score'], 'placement_candidates' => $pick['candidates']]);
    }
}
