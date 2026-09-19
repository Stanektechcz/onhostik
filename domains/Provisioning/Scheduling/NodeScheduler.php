<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Scheduling;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
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

    /** Whether a node serves a role: its `role`, or one of the extra roles in `tags.roles` / `tags.ispconfig_roles` (a panel host running web and mail). */
    public static function serves(Node $node, string $role): bool
    {
        if ($node->role === $role) {
            return true;
        }
        $extra = array_merge((array) data_get($node->tags, 'roles', []), (array) data_get($node->tags, 'ispconfig_roles', []));

        return in_array($role, array_map('strval', $extra), true);
    }

    public function pick(array $constraints): array
    {
        $weights = (array) config('onhost.provisioning.scheduler_weights');
        $query = Node::query()->with('providerInstance')->where('state', 'active'); // the role is matched below: the node's role or one of its extra roles
        if (! empty($constraints['region'])) {
            $query->where('region_code', $constraints['region']);
        }
        if (! empty($constraints['placement']['instance_id'])) { // operator pinned the plan to a panel (and maybe a server)
            $query->where('provider_instance_id', (string) $constraints['placement']['instance_id']);
            if (! empty($constraints['placement']['node_id'])) {
                $query->where('id', (string) $constraints['placement']['node_id']);
            }
        }
        $nodes = $query->get()->filter(fn (Node $n) => self::serves($n, (string) $constraints['role']) && $n->providerInstance !== null && $n->providerInstance->isUsable()
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

        $held = $this->commitments($nodes->all());

        $candidates = [];
        $blocked = [];
        foreach ($nodes as $node) {
            $instance = $node->providerInstance;
            // `usage` is the last measurement. What was placed since — paid and waiting to be built, or built after the sample — is
            // held on top of it, otherwise every order between two samples is promised the same free space (H04). An instance
            // with `capacity_basis: sold` is judged by what was sold on the node instead of what its guests happen to use.
            $basis = $instance instanceof ProviderInstance ? (string) $instance->option('capacity_basis', 'measured') : 'measured';
            $ramUsed = $basis === 'sold' ? max($held[$node->id]['sold_ram'], 0.0) : $node->use('ram_used_mb') + $held[$node->id]['pending_ram'];
            $diskUsed = $basis === 'sold' ? max($held[$node->id]['sold_disk'], $node->use('disk_used_gb')) : $node->use('disk_used_gb') + $held[$node->id]['pending_disk'];
            $sellRatio = (float) ($instance instanceof ProviderInstance ? $instance->option('sell_ratio', config('onhost.provisioning.n_plus_one_sell_ratio', 0.75)) : config('onhost.provisioning.n_plus_one_sell_ratio', 0.75)); // an instance may sell more of its nodes (option sell_ratio)
            $ramTotal = $node->cap('ram_mb');
            $cpuTotal = $node->cap('cpu_cores');
            $diskTotal = $node->cap('disk_gb');
            $ramFree = $ramTotal - $ramUsed;
            $cpuFreePct = 100.0 - $node->use('cpu_pct');
            $diskFree = $diskTotal - $diskUsed;
            // Hard capacity: placement must fit inside the sellable share (N+1 reserve kept on every node).
            if ($ramTotal > 0 && ($ramUsed + $ramNeed) > $ramTotal * $sellRatio) {
                $blocked[] = sprintf('%s RAM %d+%d > %d MB (%d %%)', $node->name, (int) $ramUsed, (int) $ramNeed, (int) ($ramTotal * $sellRatio), (int) round($sellRatio * 100));

                continue;
            }
            if ($diskTotal > 0 && ($diskUsed + $diskNeed) > $diskTotal * 0.85) {
                $blocked[] = sprintf('%s disk %d+%d > %d GB', $node->name, (int) $diskUsed, (int) $diskNeed, (int) ($diskTotal * 0.85));

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

    /**
     * Could anything host this right now? true / false, or null when no node of the role is registered at all — then
     * provisioning is not automatic here and there is nothing to judge by.
     *
     * @param  array<string,mixed>  $constraints  as for pick()
     */
    public function canHost(array $constraints): ?bool
    {
        $role = (string) ($constraints['role'] ?? '');
        $provider = (string) ($constraints['provider'] ?? '');
        $registered = Node::query()->with('providerInstance')->get()->contains(function (Node $n) use ($role, $provider) {
            $instance = $n->providerInstance;

            return self::serves($n, $role) && ($provider === '' || ($instance instanceof ProviderInstance && $instance->provider === $provider));
        });
        if (! $registered) {
            return null;
        }
        try {
            $this->pick($constraints);

            return true;
        } catch (DomainError $e) {
            if ($e->error !== 'capacity_unavailable') {
                throw $e;
            }

            return false;
        }
    }

    /**
     * Pick a node and put the service on it as one step. Two workers placing at the same moment would otherwise both read
     * the same free space: the node rows are locked for the length of the decision, so the second placement waits and then
     * counts the first one (H04). PostgreSQL serializes on the row locks; SQLite has a single writer anyway.
     *
     * @param  array<string,mixed>  $constraints  as for pick()
     * @return array{node:Node, instance:ProviderInstance, score:float, candidates:list<array{node:string,score:float}>}
     */
    public function place(Service $service, array $constraints): array
    {
        return DB::transaction(function () use ($service, $constraints) {
            Node::query()->where('state', 'active')->orderBy('id')->lockForUpdate()->get(['id']);
            $pick = $this->pick($constraints);
            $service->forceFill(['node_id' => $pick['node']->id, 'provider_instance_id' => $pick['instance']->id, 'region_code' => $pick['node']->region_code])->save();

            return $pick;
        }, 3);
    }

    /**
     * Per node: what is placed on it but not in its last measurement (`pending_*`), and everything sold on it (`sold_*`).
     *
     * @param  array<int, Node>  $nodes
     * @return array<string, array{pending_ram:float, pending_disk:float, sold_ram:float, sold_disk:float}>
     */
    private function commitments(array $nodes): array
    {
        $out = [];
        $seen = [];
        foreach ($nodes as $node) {
            $out[$node->id] = ['pending_ram' => 0.0, 'pending_disk' => 0.0, 'sold_ram' => 0.0, 'sold_disk' => 0.0];
            $seen[$node->id] = $node->last_seen_at;
        }
        if ($out === []) {
            return $out;
        }
        $services = Service::query()->whereIn('node_id', array_keys($out))->whereNotIn('state', [ServiceStateMachine::TERMINATED, ServiceStateMachine::FAILED, ServiceStateMachine::PENDING_PAYMENT])->get(['id', 'node_id', 'state', 'entitlements', 'activated_at']);
        foreach ($services as $service) {
            $ram = (float) data_get($service->entitlements, 'ram_mb', 0);
            $disk = (float) data_get($service->entitlements, 'nvme_gb', 0);
            $out[$service->node_id]['sold_ram'] += $ram;
            $out[$service->node_id]['sold_disk'] += $disk;
            $measuredAt = $seen[$service->node_id];
            $unbuilt = in_array($service->state, [ServiceStateMachine::PAID, ServiceStateMachine::PROVISIONING, ServiceStateMachine::VERIFYING], true);
            // a node that was never measured has whatever usage was typed in for it: only what is not built yet is certainly missing from that
            if ($unbuilt || ($measuredAt !== null && $service->activated_at !== null && $service->activated_at->greaterThan($measuredAt))) {
                $out[$service->node_id]['pending_ram'] += $ram;
                $out[$service->node_id]['pending_disk'] += $disk;
            }
        }

        return $out;
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
