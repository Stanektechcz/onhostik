<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Scheduling;

use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\PlanPlacement;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/**
 * Read-only comparison behind decisions 7 and 19 (TASK-0023): every node under the measured and under the sold basis, the
 * plans on sale that would stop fitting when the disk basis flips to sold, and where a plan with dedicated PHP workers
 * runs (or is sold) on a panel that runs one PHP pool for the whole node. Nothing here writes, moves or resizes anything;
 * `onhost:capacity:basis` prints it and `onhost:doctor` counts it.
 */
final class CapacityReport
{
    public const MEASURED_BASIS = ['disk' => CapacityBasis::MEASURED, 'ram' => CapacityBasis::MEASURED, 'cpu' => CapacityBasis::MEASURED];

    public const SOLD_BASIS = ['disk' => CapacityBasis::SOLD, 'ram' => CapacityBasis::SOLD, 'cpu' => CapacityBasis::MEASURED];

    /** The role a product's plans are placed by (ProvisionWebsiteWorkflow, ScheduleNodeStep callers). */
    private const ROLES = ['ispconfig' => 'web', 'aapanel' => 'managed', 'proxmox' => 'compute', 'pterodactyl' => 'game', 'kubernetes' => 'apps'];

    public function __construct(private readonly NodeScheduler $scheduler) {}

    /** @return list<array<string,mixed>> */
    public function nodes(?string $role = null, ?string $region = null): array
    {
        $plans = $this->plansOnSale();
        $out = [];
        $query = Node::query()->with('providerInstance')->whereIn('state', [Node::ACTIVE, Node::QUALIFYING])->orderBy('region_code')->orderBy('name');
        foreach ($query->get() as $node) {
            if (($role !== null && ! NodeScheduler::serves($node, $role)) || ($region !== null && $node->region_code !== $region)) {
                continue;
            }
            $instance = NodeScheduler::instanceOf($node);
            $provider = (string) $instance?->provider;
            $measured = $this->scheduler->headroom($node, self::MEASURED_BASIS);
            $sold = $this->scheduler->headroom($node, self::SOLD_BASIS);
            $sizes = array_values(array_filter($plans, fn (array $p) => $p['executor'] === $provider && NodeScheduler::serves($node, $p['role']) && ($p['disk_gb'] > 0 || $p['ram_mb'] > 0)));
            usort($sizes, fn ($a, $b) => $a['disk_gb'] <=> $b['disk_gb']);
            $fitsMeasured = array_values(array_filter($sizes, fn (array $p) => self::fits($measured, $p)));
            $fitsSold = array_values(array_filter($sizes, fn (array $p) => self::fits($sold, $p)));
            $lost = array_values(array_diff(array_column($fitsMeasured, 'plan'), array_column($fitsSold, 'plan')));
            $out[] = [
                'node' => $node->name, 'instance' => $instance?->key, 'provider' => $provider, 'role' => $node->role, 'region' => $node->region_code, 'state' => $node->state,
                'basis' => CapacityBasis::for($instance), 'legacy' => $instance !== null && CapacityBasis::isLegacy($instance),
                'disk' => ['measured' => self::dimension($measured['disk']), 'sold' => self::dimension($sold['disk'])],
                'ram' => ['measured' => self::dimension($measured['ram']), 'sold' => self::dimension($sold['ram'])],
                'smallest_plan' => $sizes[0]['plan'] ?? null, 'largest_plan' => $sizes === [] ? null : $sizes[count($sizes) - 1]['plan'],
                'loses_under_sold_disk' => $lost,
                // the node would take no plan on sale any more once the disk is judged by what was sold
                'closes_under_sold_disk' => $sizes !== [] && $fitsMeasured !== [] && $fitsSold === [],
            ];
        }

        return $out;
    }

    /**
     * Live services that sell dedicated PHP workers and run on a panel that gives a site no pool of its own. Reported, never moved.
     *
     * @return list<array{service:string, product:string, plan:?string, node:?string, provider:string}>
     */
    public function dedicatedOnNodeWidePool(): array
    {
        $instances = ProviderInstance::query()->get(['id', 'provider'])->keyBy('id');
        $out = [];
        $live = Service::query()->whereIn('family', ['web', 'managed'])->whereNotIn('state', [ServiceStateMachine::TERMINATED, ServiceStateMachine::FAILED, ServiceStateMachine::PENDING_PAYMENT])->whereNotNull('provider_instance_id')->get();
        foreach ($live as $service) {
            $provider = (string) $instances->get($service->provider_instance_id)?->provider;
            if (! PlacementRules::dedicatedPhp((array) $service->entitlements) || in_array($provider, PlacementRules::DEDICATED_PHP_PROVIDERS, true)) {
                continue;
            }
            $out[] = ['service' => (string) $service->id, 'product' => (string) $service->product_key, 'plan' => data_get($service->desired_spec, 'plan_key'), 'node' => $service->node_id === null ? null : Node::query()->whereKey($service->node_id)->value('name'), 'provider' => $provider];
        }

        return $out;
    }

    /**
     * Plans on sale that sell dedicated PHP workers on a product whose panel runs one pool for the whole node (eshop/shop-peak).
     *
     * @return list<string> product/plan
     */
    public function undeliveredPlans(): array
    {
        return array_values(array_map(fn (array $p) => $p['plan'], array_filter($this->plansOnSale(), fn (array $p) => PlacementRules::undelivered($p['executor'], $p['entitlements']))));
    }

    /**
     * Active placements a plan's rule makes `PlacementService::resolve()` ignore (e.g. profi pinned, or web-hosting pinned as a whole, to aaPanel).
     *
     * @return list<string> product/plan → instance
     */
    public function ignoredPlacements(): array
    {
        $plans = $this->plansOnSale();
        $out = [];
        foreach (PlanPlacement::query()->with('providerInstance')->where('state', 'active')->get() as $placement) {
            $instance = $placement->providerInstance instanceof ProviderInstance ? $placement->providerInstance : null;
            $provider = (string) $instance?->provider;
            foreach ($plans as $plan) {
                if ($plan['product'] !== $placement->product_key || ($placement->plan_key !== null && $placement->plan_key !== $plan['key'])) {
                    continue;
                }
                if (! PlacementRules::allows($plan['executor'], $provider, $plan['entitlements'])) {
                    $out[] = $plan['plan'].' → '.(string) $instance?->key;
                }
            }
        }

        return $out;
    }

    /** @return list<array{product:string, key:string, plan:string, executor:string, role:string, disk_gb:int, ram_mb:int, entitlements:array<string,mixed>}> */
    private function plansOnSale(): array
    {
        $products = Product::query()->where('state', 'active')->whereNotNull('executor')->get(['id', 'key', 'executor', 'family'])->keyBy('id');
        $out = [];
        foreach (Plan::query()->whereIn('product_id', $products->keys())->where('state', 'active')->get() as $plan) {
            $product = $products->get($plan->product_id);
            $entitlements = (array) $plan->currentVersion()?->entitlements;
            if ($product === null || $entitlements === []) {
                continue;
            }
            $out[] = ['product' => (string) $product->key, 'key' => (string) $plan->key, 'plan' => $product->key.'/'.$plan->key, 'executor' => (string) $product->executor,
                'role' => $product->family === 'mail' ? 'mail' : (self::ROLES[(string) $product->executor] ?? ''), 'disk_gb' => (int) ($entitlements['nvme_gb'] ?? 0), 'ram_mb' => (int) ($entitlements['ram_mb'] ?? 0), 'entitlements' => $entitlements];
        }

        return $out;
    }

    /** @param array<string,mixed> $room @param array{disk_gb:int, ram_mb:int} $plan */
    private static function fits(array $room, array $plan): bool
    {
        $disk = $room['disk']['total'] <= 0 || $room['disk']['used'] + $plan['disk_gb'] <= $room['disk']['limit'];
        $ram = $room['ram']['total'] <= 0 || $room['ram']['used'] + $plan['ram_mb'] <= $room['ram']['limit'];

        return $disk && $ram;
    }

    /** @param array<string,float> $dimension @return array{total:int, limit:int, used:int, free:int} */
    private static function dimension(array $dimension): array
    {
        return ['total' => (int) round($dimension['total']), 'limit' => (int) floor($dimension['limit']), 'used' => (int) round($dimension['used']), 'free' => (int) floor($dimension['free'])];
    }
}
