<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Scheduling;

use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\PlacementService;

/**
 * Could a node take this cart line right now (H04)? Servers (Proxmox, Pterodactyl) were judged since H04; a web or
 * managed hosting was not judged at all, so an order no web node had room for was paid and then waited in
 * `ScheduleNodeStep` (TASK-0023, decision 19). The web line is judged where provisioning would put it: the placement
 * that applies to the plan (`PlacementService::resolve`), the panel the plan may run on (`PlacementRules`) and the
 * disk the plan sells, under the node's own basis (`CapacityBasis`).
 *
 * true / false, or null when no node of the kind is registered — then provisioning is not automatic and there is
 * nothing to judge by. A plan with dedicated PHP workers where only other web panels have nodes is false: it would wait
 * after payment for a node that does not exist.
 */
final class CartCapacity
{
    private const SERVER_ROLES = ['proxmox' => 'compute', 'pterodactyl' => 'game'];

    private const WEB_EXECUTORS = ['ispconfig', 'aapanel'];

    public function __construct(private readonly NodeScheduler $scheduler, private readonly PlacementService $placements) {}

    /** @param array<string,mixed> $entitlements */
    public function fits(Product $product, array $entitlements, string $region, ?string $planKey, bool $sandbox): ?bool
    {
        $executor = (string) $product->executor;
        if (isset(self::SERVER_ROLES[$executor])) {
            return $this->scheduler->canHost([
                'role' => self::SERVER_ROLES[$executor], 'provider' => $executor, 'region' => $region, 'sandbox' => $sandbox,
                'ram_mb' => (int) ($entitlements['ram_mb'] ?? 0), 'cpu_cores' => (int) ($entitlements['vcpu'] ?? 0), 'disk_gb' => (int) ($entitlements['nvme_gb'] ?? 0),
            ]);
        }
        if (! in_array($product->family, ['web', 'managed'], true) || ! in_array($executor, self::WEB_EXECUTORS, true)) {
            return null;
        }
        $placement = $this->placements->resolve($product->key, $planKey, $region, $entitlements);
        $runsOn = PlacementRules::executorFor($executor, $entitlements, $placement);
        $providers = PlacementRules::providersFor(PlacementRules::requires($executor, $entitlements));
        $constraints = array_filter([
            'role' => $runsOn === 'aapanel' ? 'managed' : 'web', 'provider' => $runsOn, 'providers' => $providers, 'region' => $region, 'sandbox' => $sandbox,
            'disk_gb' => (int) ($entitlements['nvme_gb'] ?? 0),
            'placement' => $placement === null ? null : array_filter(['instance_id' => $placement->provider_instance_id, 'node_id' => $placement->node_id]),
        ], fn ($v) => $v !== null && $v !== [] && $v !== '');
        $fits = $this->scheduler->canHost($constraints);
        if ($fits === null && $providers !== null && $this->otherWebNodes()) {
            return false; // web nodes exist, none on a panel this plan may run on
        }

        return $fits;
    }

    private function otherWebNodes(): bool
    {
        return Node::query()->get()->contains(fn (Node $n) => NodeScheduler::serves($n, 'web') || NodeScheduler::serves($n, 'managed'));
    }
}
