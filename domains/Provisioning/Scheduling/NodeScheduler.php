<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Scheduling;

use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\DomainError;

/**
 * Blueprint §5.3: hard constraints first (region, role, state, provider
 * usability, capacity after placement, anti-affinity), then a weighted soft score:
 *   0.30 memory headroom + 0.25 cpu headroom + 0.20 storage headroom
 *   + 0.10 io health + 0.10 failure-domain affinity + 0.05 network health.
 * Sellable capacity is the N+1 view: the largest node's capacity is treated as reserve.
 */
final class NodeScheduler
{
    /**
     * @param  array{region?:string, role:string, provider?:string, ram_mb?:int, cpu_cores?:int, disk_gb?:int, anti_affinity?:list<string>, affinity_failure_domain?:string, exclude_nodes?:list<string>}  $constraints
     * @return array{node:Node, instance:ProviderInstance, score:float, candidates:list<array{node:string,score:float}>}
     */
    /** Whether an organization is a sandbox tenant (`feature_flags.sandbox`): its services are placed on lab instances (`options.sandbox`). */
    public static function sandboxFor(?string $organizationId): bool
    {
        if ($organizationId === null) {
            return false;
        }

        return (bool) data_get(Organization::query()->find($organizationId)?->feature_flags, 'sandbox', false);
    }

    public function pick(array $constraints): array
    {
        $weights = (array) config('onhost.provisioning.scheduler_weights');
        $query = Node::query()->with('providerInstance')->where('role', $constraints['role'])->where('state', 'active');
        if (! empty($constraints['region'])) {
            $query->where('region_code', $constraints['region']);
        }
        if (! empty($constraints['placement']['instance_id'])) { // operator pinned the plan to a panel (and maybe a server)
            $query->where('provider_instance_id', (string) $constraints['placement']['instance_id']);
            if (! empty($constraints['placement']['node_id'])) {
                $query->where('id', (string) $constraints['placement']['node_id']);
            }
        }
        $nodes = $query->get()->filter(fn (Node $n) => $n->providerInstance !== null && $n->providerInstance->isUsable()
            && (empty($constraints['provider']) || $n->providerInstance->provider === $constraints['provider'])
            && ! in_array($n->id, $constraints['exclude_nodes'] ?? [], true)
            && ! in_array($n->name, $constraints['exclude_nodes'] ?? [], true)
            && (bool) data_get($n->providerInstance->options, 'sandbox', false) === (bool) ($constraints['sandbox'] ?? false)); // sandbox tenants land on lab instances only; everyone else never does (audit §5j-9)
        if ($nodes->isEmpty()) {
            $pinnedKey = (string) data_get($constraints, 'placement.instance_key', '');
            throw new DomainError('capacity_unavailable', 'No schedulable node matches the constraints.'.($pinnedKey !== '' ? " Import the nodes of {$pinnedKey}: php artisan onhost:nodes:discover {$pinnedKey}" : ''), 503, ['role' => $constraints['role'], 'region' => $constraints['region'] ?? null, 'instance' => $pinnedKey !== '' ? $pinnedKey : null]);
        }

        $ramNeed = (float) ($constraints['ram_mb'] ?? 0);
        $cpuNeed = (float) ($constraints['cpu_cores'] ?? 0);
        $diskNeed = (float) ($constraints['disk_gb'] ?? 0);
        $antiAffinity = $constraints['anti_affinity'] ?? [];

        $candidates = [];
        $blocked = [];
        foreach ($nodes as $node) {
            $instance = $node->providerInstance;
            $sellRatio = (float) ($instance instanceof ProviderInstance ? $instance->option('sell_ratio', config('onhost.provisioning.n_plus_one_sell_ratio', 0.75)) : config('onhost.provisioning.n_plus_one_sell_ratio', 0.75)); // an instance may sell more of its nodes (option sell_ratio)
            $ramTotal = $node->cap('ram_mb');
            $cpuTotal = $node->cap('cpu_cores');
            $diskTotal = $node->cap('disk_gb');
            $ramFree = $ramTotal - $node->use('ram_used_mb');
            $cpuFreePct = 100.0 - $node->use('cpu_pct');
            $diskFree = $diskTotal - $node->use('disk_used_gb');
            // Hard capacity: placement must fit inside the sellable share (N+1 reserve kept on every node).
            if ($ramTotal > 0 && ($node->use('ram_used_mb') + $ramNeed) > $ramTotal * $sellRatio) {
                $blocked[] = sprintf('%s RAM %d+%d > %d MB (%d %%)', $node->name, (int) $node->use('ram_used_mb'), (int) $ramNeed, (int) ($ramTotal * $sellRatio), (int) round($sellRatio * 100));

                continue;
            }
            if ($diskTotal > 0 && ($node->use('disk_used_gb') + $diskNeed) > $diskTotal * 0.85) {
                $blocked[] = sprintf('%s disk %d+%d > %d GB', $node->name, (int) $node->use('disk_used_gb'), (int) $diskNeed, (int) ($diskTotal * 0.85));

                continue;
            }
            if ($cpuTotal > 0 && $cpuNeed > 0 && $node->use('cpu_pct') > 85.0) {
                $blocked[] = sprintf('%s CPU %d %%', $node->name, (int) $node->use('cpu_pct'));

                continue;
            }
            $hosts = (array) data_get($node->usage, 'hosted_services', []);
            if ($antiAffinity !== [] && array_intersect($antiAffinity, $hosts) !== []) {
                continue;
            }
            $memory = $ramTotal > 0 ? max(0.0, min(1.0, $ramFree / $ramTotal)) : 0.5;
            $cpu = max(0.0, min(1.0, $cpuFreePct / 100.0));
            $storage = $diskTotal > 0 ? max(0.0, min(1.0, $diskFree / $diskTotal)) : 0.5;
            $io = max(0.0, min(1.0, 1.0 - $node->use('io_wait_pct') / 100.0));
            $affinity = isset($constraints['affinity_failure_domain']) ? ($node->failure_domain === $constraints['affinity_failure_domain'] ? 1.0 : 0.0) : 0.5;
            $network = max(0.0, min(1.0, 1.0 - $node->use('net_util_pct', 0.0) / 100.0));
            $score = $weights['memory_headroom'] * $memory + $weights['cpu_headroom'] * $cpu + $weights['storage_headroom'] * $storage
                + $weights['io_health'] * $io + $weights['failure_domain_affinity'] * $affinity + $weights['network_health'] * $network;
            $candidates[] = ['node' => $node, 'score' => round($score, 4)];
        }
        if ($candidates === []) {
            throw new DomainError('capacity_unavailable', 'All matching nodes are at their N+1 sellable limit: '.implode('; ', $blocked).'.', 503, ['role' => $constraints['role'], 'blocked' => $blocked]);
        }
        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score'] ?: strcmp($a['node']->name, $b['node']->name));
        $best = $candidates[0];

        return [
            'node' => $best['node'],
            'instance' => $best['node']->providerInstance,
            'score' => $best['score'],
            'candidates' => array_map(fn ($c) => ['node' => $c['node']->name, 'score' => $c['score']], $candidates),
        ];
    }

    /** N+1 sellable capacity of a role/region: total minus the largest node, times the sell ratio. @return array{ram_mb:int, cpu_cores:int, disk_gb:int, nodes:int, largest_node:?string} */
    public function sellableCapacity(string $role, ?string $region = null): array
    {
        $query = Node::query()->where('role', $role)->whereIn('state', ['active', 'drain']);
        if ($region !== null) {
            $query->where('region_code', $region);
        }
        $nodes = $query->get();
        if ($nodes->isEmpty()) {
            return ['ram_mb' => 0, 'cpu_cores' => 0, 'disk_gb' => 0, 'nodes' => 0, 'largest_node' => null];
        }
        $largest = $nodes->sortByDesc(fn (Node $n) => $n->cap('ram_mb'))->first();
        $sellRatio = (float) config('onhost.provisioning.n_plus_one_sell_ratio', 0.75);
        $sum = fn (string $key) => (int) floor(($nodes->sum(fn (Node $n) => $n->cap($key)) - $largest->cap($key)) * $sellRatio);

        return ['ram_mb' => $sum('ram_mb'), 'cpu_cores' => $sum('cpu_cores'), 'disk_gb' => $sum('disk_gb'), 'nodes' => $nodes->count(), 'largest_node' => $largest->name];
    }
}
