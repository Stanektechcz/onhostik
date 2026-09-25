<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Provisioning\Scheduling\CapacityBasis;
use Onhost\Domain\Provisioning\Scheduling\CapacityReport;

/**
 * Read before the switch (owner decision 19, TASK-0023): every node under the measured and under the sold basis, the plans
 * on sale each node would stop taking once `ONHOST_CAPACITY_DISK_BASIS=sold`, and where dedicated PHP workers are sold or
 * run on a panel that runs one PHP pool for the whole node (decision 7). Writes nothing — no node, service, audit row or
 * event — so it is safe to run in production at any time.
 */
final class CapacityBasisReport extends Command
{
    protected $signature = 'onhost:capacity:basis {--role= : only nodes serving this role (web, managed, compute, game, mail)} {--region= : only nodes of this region} {--json : machine-readable output}';

    protected $description = 'Compare node capacity under the measured and the sold basis, and list dedicated-PHP plans on a node-wide pool (read-only)';

    public function handle(CapacityReport $report): int
    {
        $role = $this->option('role') !== null && $this->option('role') !== '' ? (string) $this->option('role') : null;
        $region = $this->option('region') !== null && $this->option('region') !== '' ? (string) $this->option('region') : null;
        $nodes = $report->nodes($role, $region);
        $data = [
            'defaults' => CapacityBasis::defaults(), 'disk_sell_ratio' => CapacityBasis::diskSellRatio(null), 'nodes' => $nodes,
            'would_close' => array_values(array_map(fn (array $n) => $n['node'], array_filter($nodes, fn (array $n) => $n['closes_under_sold_disk']))),
            'dedicated_php' => ['on_node_wide_pool' => $report->dedicatedOnNodeWidePool(), 'undelivered_plans' => $report->undeliveredPlans(), 'ignored_placements' => $report->ignoredPlacements()],
        ];
        if ($this->option('json')) {
            $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }
        $this->line('Platform basis: disk '.$data['defaults']['disk'].', RAM '.$data['defaults']['ram'].', CPU '.$data['defaults']['cpu'].'; disk sell ratio '.$data['disk_sell_ratio']);
        $this->table(['Node', 'Instance', 'Role', 'Basis (disk/RAM)', 'Disk used measured / sold / limit GB', 'RAM used measured / sold / limit MB', 'Plans lost under sold disk', 'Closes'], array_map(fn (array $n) => [
            $n['node'], (string) $n['instance'], $n['role'], $n['basis']['disk'].'/'.$n['basis']['ram'].($n['legacy'] ? ' (legacy option)' : ''),
            $n['disk']['measured']['used'].' / '.$n['disk']['sold']['used'].' / '.$n['disk']['sold']['limit'],
            $n['ram']['measured']['used'].' / '.$n['ram']['sold']['used'].' / '.$n['ram']['sold']['limit'],
            implode(', ', $n['loses_under_sold_disk']) ?: '—', $n['closes_under_sold_disk'] ? 'yes' : 'no',
        ], $nodes));
        $this->line('Nodes that would take no plan under sold disk: '.(implode(', ', $data['would_close']) ?: 'none'));
        $php = $data['dedicated_php'];
        $this->line('Dedicated PHP sold where the panel runs one pool per node: '.(implode(', ', $php['undelivered_plans']) ?: 'none'));
        $this->line('Services with dedicated PHP on a node-wide pool (not moved): '.($php['on_node_wide_pool'] === [] ? 'none' : implode(', ', array_map(fn (array $s) => $s['service'].' '.$s['product'].'/'.(string) $s['plan'].' on '.(string) $s['node'], $php['on_node_wide_pool']))));
        $this->line('Placements ignored for a dedicated-PHP plan: '.(implode(', ', $php['ignored_placements']) ?: 'none'));

        return self::SUCCESS;
    }
}
