<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Scheduling;

use App\Http\Presenters\Presenters;
use Carbon\CarbonImmutable;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\NodeUsageSample;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ServiceMigrationService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * Efficient use of the fleet (audit §5i): the load of a node is the RAM sold on it against its capacity; nodes above
 * the high mark hand their smallest services to the least loaded node of the same role and region (same cluster for
 * VPS, any panel for game servers) until both sit under the target mark. The plan is a proposal — staff apply it,
 * usually inside a window the customers may move — and every move is one migration saga with its own report.
 */
final class NodeRebalancer
{
    public function __construct(private readonly ServiceMigrationService $migrations) {}

    /**
     * @return array{thresholds:array{high:float,low:float,target:float}, nodes:list<array<string,mixed>>, moves:list<array<string,mixed>>}
     */
    public function plan(?string $role = null, string $basis = 'sold'): array
    {
        $basis = in_array($basis, ['usage', 'trend'], true) ? $basis : 'sold'; // usage = the node's last measured RAM (audit §5j-4); trend = the 7-day p95 projected a week ahead from the hourly samples (§5k-7); sold = the RAM sold on it
        $high = (float) config('onhost.provisioning.rebalance.high', 0.85);
        $low = (float) config('onhost.provisioning.rebalance.low', 0.6);
        $target = (float) config('onhost.provisioning.rebalance.target', 0.75);
        $roles = $role !== null ? [$role] : ['game', 'compute'];
        $nodes = Node::query()->with('providerInstance')->where('state', 'active')->whereIn('role', $roles)->get()->filter(fn (Node $n) => (int) data_get($n->capacity, 'ram_mb', 0) > 0);
        $services = Service::query()->whereIn('node_id', $nodes->pluck('id'))->whereIn('family', array_keys(ServiceMigrationService::WORKFLOWS))->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->get()->groupBy('node_id');
        $rows = [];
        $trends = [];
        $diskCap = [];
        foreach ($nodes as $node) {
            $sold = (int) $services->get($node->id, collect())->sum(fn (Service $s) => (int) data_get($s->entitlements, 'ram_mb', 0));
            $trend = null;
            if ($basis === 'usage' && $node->use('ram_used_mb') > 0) {
                $sold = (int) $node->use('ram_used_mb'); // the measured load stands in for the sold RAM; a node without a measurement keeps the sold figure
            } elseif ($basis === 'trend') {
                $trend = $this->trend($node);
                $sold = $trend !== null ? (int) $trend['projected_mb'] : ((int) $node->use('ram_used_mb') ?: $sold); // too few samples: fall back to the last measurement, then to the sold RAM
            }
            $trends[$node->id] = $trend;
            $diskCap[$node->id] = (int) data_get($node->capacity, 'disk_gb', 0);
            $rows[$node->id] = ['id' => $node->id, 'name' => $node->name, 'role' => $node->role, 'region' => $node->region_code, 'instance' => $node->providerInstance?->key, 'instance_id' => $node->provider_instance_id, 'provider' => $node->providerInstance?->provider, 'capacity_mb' => (int) data_get($node->capacity, 'ram_mb', 0), 'sold_mb' => $sold, 'load' => round($sold / max(1, (int) data_get($node->capacity, 'ram_mb', 1)), 3), 'services' => $services->get($node->id, collect())->count()];
        }
        foreach ($rows as $id => $row) {
            $rows[$id]['load_before'] = $row['load']; // the plan reports the load before and after its moves
            // the trend's other axes (§5l-7): a node hot on CPU or disk is rebalanced too; the estimate shrinks with the RAM share moved away
            $t = $trends[$id] ?? null;
            $reasons = $row['load'] > $high ? ['ram'] : [];
            if ($t !== null && ($t['cpu_p95'] ?? 0) >= $high * 100) {
                $reasons[] = 'cpu';
            }
            $diskPct = $t !== null && ($diskCap[$id] ?? 0) > 0 ? (float) $t['disk_p95_gb'] / $diskCap[$id] * 100 : 0.0;
            if ($diskPct >= 85.0) {
                $reasons[] = 'disk';
            }
            $rows[$id] += ['reasons' => $reasons, 'cpu_p95' => $t['cpu_p95'] ?? null, 'disk_p95_gb' => $t['disk_p95_gb'] ?? null, 'cpu_est' => (float) ($t['cpu_p95'] ?? 0), 'disk_est_pct' => $diskPct, 'trend' => $t];
        }
        $moves = [];
        foreach ($rows as $hotId => $hot) {
            if ($hot['load'] <= $high && $hot['reasons'] === []) {
                continue;
            }
            $candidates = array_filter($rows, fn ($n) => $n['id'] !== $hotId && $n['role'] === $hot['role'] && $n['region'] === $hot['region'] && $n['load'] < $low && ($hot['role'] !== 'compute' || $n['instance_id'] === $hot['instance_id']) && ($hot['role'] !== 'game' || $n['provider'] === $hot['provider']));
            if ($candidates === []) {
                continue;
            }
            $movable = $services->get($hotId, collect())->sortBy(fn (Service $s) => (int) data_get($s->entitlements, 'ram_mb', 0))->values();
            foreach ($movable as $service) {
                if ($rows[$hotId]['load'] <= $target && $rows[$hotId]['cpu_est'] <= $target * 100 && $rows[$hotId]['disk_est_pct'] <= $target * 100) {
                    break;
                }
                $ram = (int) data_get($service->entitlements, 'ram_mb', 0);
                if ($ram <= 0) {
                    continue;
                }
                uasort($candidates, fn ($a, $b) => $rows[$a['id']]['load'] <=> $rows[$b['id']]['load']);
                $to = null;
                foreach ($candidates as $candidate) {
                    $after = ($rows[$candidate['id']]['sold_mb'] + $ram) / max(1, $candidate['capacity_mb']);
                    if ($after <= $target && ($hot['role'] !== 'game' || $candidate['instance_id'] === $hot['instance_id'] || $this->templateMapped($service, $candidate['instance_id']))) {
                        $to = $candidate;
                        break;
                    }
                }
                if ($to === null) {
                    break;
                }
                $fraction = $ram / max(1, $rows[$hotId]['sold_mb']); // the share of the node's load that leaves with this service
                $rows[$hotId]['sold_mb'] -= $ram;
                $rows[$hotId]['load'] = round($rows[$hotId]['sold_mb'] / max(1, $rows[$hotId]['capacity_mb']), 3);
                $rows[$hotId]['cpu_est'] = round($rows[$hotId]['cpu_est'] * max(0.0, 1 - $fraction), 1);
                $rows[$hotId]['disk_est_pct'] = round($rows[$hotId]['disk_est_pct'] * max(0.0, 1 - $fraction), 1);
                $rows[$to['id']]['sold_mb'] += $ram;
                $rows[$to['id']]['load'] = round($rows[$to['id']]['sold_mb'] / max(1, $rows[$to['id']]['capacity_mb']), 3);
                $moves[] = ['service_id' => $service->id, 'label' => $service->label ?: $service->name, 'family' => $service->family, 'organization_id' => $service->organization_id, 'ram_mb' => $ram, 'from' => $hot['name'], 'to' => $to['name'], 'to_node_id' => $to['id'], 'reason' => sprintf('%s at %d %% → %s', $hot['name'], (int) round($hot['load'] * 100), $to['name'])];
            }
        }

        return ['basis' => $basis, 'thresholds' => ['high' => $high, 'low' => $low, 'target' => $target], 'nodes' => array_values(array_map(fn ($n) => $n + ['load_pct' => (int) round($n['load'] * 100), 'load_before_pct' => (int) round(($n['load_before'] ?? $n['load']) * 100)], $rows)), 'moves' => $moves];
    }

    /**
     * The 7-day trend of a node (§5k-7): the 95th percentile of the hourly RAM samples plus the slope of the daily averages
     * projected a week ahead; null when fewer than twelve samples exist.
     *
     * @return array{samples:int, p95_mb:int, slope_mb_per_day:int, projected_mb:int}|null
     */
    public function trend(Node $node, int $days = 7): ?array
    {
        $samples = NodeUsageSample::query()->where('node_id', $node->id)->where('sampled_at', '>=', now()->subDays($days))->orderBy('sampled_at')->get(['sampled_at', 'ram_used_mb', 'cpu_pct', 'disk_used_gb']);
        if ($samples->count() < 12) {
            return null;
        }
        $values = $samples->pluck('ram_used_mb')->map(fn ($v) => (int) $v)->sort()->values();
        $p95 = (int) $values->get((int) floor(0.95 * ($values->count() - 1)));
        $daily = $samples->groupBy(fn ($s) => $s->sampled_at->toDateString())->map(fn ($g) => (int) round($g->avg('ram_used_mb')))->values();
        $slope = $daily->count() >= 2 ? (int) round(($daily->last() - $daily->first()) / max(1, $daily->count() - 1)) : 0;
        $projected = max($p95, (int) $samples->last()->ram_used_mb + max(0, $slope) * 7);
        $cap = (int) $node->cap('ram_mb');

        $p95Of = function (string $column) use ($samples): int { // the other axes (§5l-7): the same window's 95th percentile of CPU and disk
            $v = $samples->pluck($column)->map(fn ($x) => (int) $x)->sort()->values();

            return (int) $v->get((int) floor(0.95 * ($v->count() - 1)));
        };

        return ['samples' => $samples->count(), 'p95_mb' => $p95, 'slope_mb_per_day' => $slope, 'projected_mb' => $cap > 0 ? min($cap, $projected) : $projected, 'cpu_p95' => $p95Of('cpu_pct'), 'disk_p95_gb' => $p95Of('disk_used_gb')];
    }

    /**
     * Starts the migrations of the chosen moves (all of the plan when none are named), usually inside a window.
     *
     * @param  list<string>  $serviceIds
     * @return array{started:list<array<string,mixed>>, skipped:list<array{service_id:string,error:string}>}
     */
    public function apply(array $serviceIds, ?string $reason, CommandContext $context, ?CarbonImmutable $windowFrom = null, ?CarbonImmutable $windowTo = null): array
    {
        $plan = $this->plan();
        $out = ['started' => [], 'skipped' => []];
        foreach ($plan['moves'] as $move) {
            if ($serviceIds !== [] && ! in_array($move['service_id'], $serviceIds, true)) {
                continue;
            }
            $service = Service::query()->find($move['service_id']);
            if ($service === null) {
                continue;
            }
            try {
                $out['started'][] = Presenters::operation($this->migrations->start($service, $move['to'], $reason ?? $move['reason'], $context, $windowFrom, $windowTo), true) + ['label' => $move['label'], 'from' => $move['from'], 'to' => $move['to']];
            } catch (DomainError $e) {
                $out['skipped'][] = ['service_id' => $move['service_id'], 'error' => $e->error];
            }
        }

        return $out;
    }

    private function templateMapped(Service $service, string $instanceId): bool
    {
        $egg = (string) data_get($service->desired_spec, 'egg', '');
        $instance = ProviderInstance::query()->find($instanceId);

        return $egg !== '' && $instance !== null && ! empty($instance->option('eggs', [])[$egg]['egg']);
    }
}
