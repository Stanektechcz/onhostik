<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Carbon\CarbonInterface;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\NodeUsageSample;

/**
 * Hourly usage samples per node (§5k-7): the last measurement the integrations wrote on the node (`usage`) is copied
 * into `node_usage_samples` so a 7-day trend exists; probes may add measured power (§5k-6). Samples older than the
 * retention are pruned with every run.
 */
final class NodeSampler
{
    public function __construct(private readonly HostPowerReader $power) {}

    /** Snapshots every active node's current usage; returns the number of samples written. */
    public function snapshot(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $this->power->sweep(); // §5n-6: hosts with a management controller are read first, so the sample carries measured watts
        $count = 0;
        foreach (Node::query()->whereIn('state', ['active', 'drain', 'draining', 'maintenance'])->get() as $node) {
            $usage = (array) ($node->usage ?? []);
            if ($usage === [] || $node->last_seen_at === null || $node->last_seen_at < $now->copy()->subDays(2)) {
                continue; // nothing measured recently: a stale reading would fake a flat trend
            }
            NodeUsageSample::query()->create([
                'node_id' => $node->id, 'sampled_at' => $now, 'cpu_pct' => max(0, min(100, (int) ($usage['cpu_pct'] ?? 0))), 'ram_used_mb' => max(0, (int) ($usage['ram_used_mb'] ?? 0)),
                'disk_used_gb' => max(0, (int) ($usage['disk_used_gb'] ?? 0)), 'power_w' => isset($usage['power_w']) && $this->fresh($usage, $now) ? (int) $usage['power_w'] : null, 'source' => 'snapshot',
            ]);
            $count++;
        }
        NodeUsageSample::query()->where('sampled_at', '<', $now->copy()->subDays(max(7, (int) config('onhost.provisioning.samples.retention_days', 30))))->delete();

        return $count;
    }

    /** @param  array<string,mixed>  $usage */
    private function fresh(array $usage, CarbonInterface $now): bool
    {
        $at = $usage['power_sampled_at'] ?? null;

        return $at !== null && $now->copy()->subHours(max(1, (int) config('onhost.green.measured_max_age_hours', 24))) <= now()->parse((string) $at);
    }
}
