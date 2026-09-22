<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\WebHostingProvider;
use Throwable;

/**
 * How full a shared web node really is. The placement rule that keeps a node from being filled up has always been
 * there — `NodeScheduler` refuses a node whose used disk plus what the new service needs would pass 85 % of it — but
 * for ISPConfig and aaPanel nodes it was measuring **nothing**: `nodes.usage.disk_used_gb` is written when a Proxmox
 * cluster or a game panel is discovered, and never for a web node. The number stayed 0, so the rule never fired, and
 * a shared node close to its last gigabyte went on taking new sites. The acceptance check (`NodeQualification`, the
 * 15 % headroom) read the same zero and passed every time.
 *
 * Both panels can say it — aaPanel through its system totals and disk list, ISPConfig through its monitor — and the
 * adapters had `nodeLoad()` ready for it with nobody calling it. This asks every web node every quarter of an hour,
 * writes what it answers, and tells the operators when a node has less room left than it keeps for itself.
 */
final class NodeUsageSync
{
    /** Below this share of free disk a node is no longer a place to put anything new (the qualification's headroom). */
    public const LOW_FREE_PCT = NodeQualification::DISK_HEADROOM_PCT;

    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly OutboxPublisher $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    /** @return array{checked:int, updated:int, low:int, errors:int} */
    public function run(int $limit = 200): array
    {
        $stats = ['checked' => 0, 'updated' => 0, 'low' => 0, 'errors' => 0];
        $nodes = Node::query()->whereIn('state', ['active', 'qualifying', 'draining'])->whereNotNull('provider_instance_id')->orderBy('id')->limit(max(1, $limit))->get();
        foreach ($nodes as $node) {
            $instance = ProviderInstance::query()->find($node->provider_instance_id);
            if ($instance === null || $instance->state !== 'active') {
                continue;
            }
            try {
                $adapter = $this->registry->forInstance($instance);
            } catch (Throwable) {
                continue;
            }
            if (! $adapter instanceof WebHostingProvider) {
                continue; // Proxmox and the game panel write their numbers when they are synchronised
            }
            $stats['checked']++;
            try {
                $load = $adapter->nodeLoad($node->remote_id !== null ? (string) $node->remote_id : null);
            } catch (Throwable) {
                $stats['errors']++;

                continue;
            }
            if ($this->write($node, $load)) {
                $stats['updated']++;
            }
            $free = self::freePct($node->fresh() ?? $node);
            if ($free !== null && $free < self::LOW_FREE_PCT) {
                $stats['low']++;
                $this->tellOperators($node->fresh() ?? $node, $free);
            }
        }

        return $stats;
    }

    /** How much of the node's disk is free, as far as the platform knows; null when nobody ever measured it. */
    public static function freePct(Node $node): ?float
    {
        $pct = data_get($node->usage, 'disk_pct');
        if (is_numeric($pct)) {
            return round(100 - (float) $pct, 2);
        }
        $total = (float) $node->cap('disk_gb');
        $used = (float) $node->use('disk_used_gb');

        return $total > 0 ? round(($total - $used) / $total * 100, 2) : null;
    }

    /**
     * @param  array<string,mixed>  $load
     */
    private function write(Node $node, array $load): bool
    {
        $usage = (array) ($node->usage ?? []);
        $before = $usage;
        foreach (['cpu_pct' => 'cpu_pct', 'disk_pct' => 'disk_pct', 'load' => 'load_one', 'sites' => 'sites'] as $from => $to) {
            if (is_numeric($load[$from] ?? null)) {
                $usage[$to] = $to === 'sites' ? (int) $load[$from] : round((float) $load[$from], 2);
            }
        }
        $capacity = (array) ($node->capacity ?? []);
        // the node says how big its disk is; the platform stops guessing, and the scheduler works in gigabytes, so a
        // percentage only becomes one once that size is known
        $totalGb = is_numeric($load['disk_total_gb'] ?? null) && (float) $load['disk_total_gb'] > 0 ? (float) $load['disk_total_gb'] : $node->cap('disk_gb');
        if ($totalGb > 0) {
            $capacity['disk_gb'] = (int) round($totalGb);
        }
        if (is_numeric($load['disk_used_gb'] ?? null)) {
            $usage['disk_used_gb'] = (int) round((float) $load['disk_used_gb']);
        } elseif ($totalGb > 0 && is_numeric($load['disk_pct'] ?? null)) {
            $usage['disk_used_gb'] = (int) round($totalGb * (float) $load['disk_pct'] / 100);
        }
        if (is_numeric($load['mem_pct'] ?? null) && $node->cap('ram_mb') > 0) {
            $usage['ram_used_mb'] = (int) round($node->cap('ram_mb') * (float) $load['mem_pct'] / 100);
        }
        $usage['sampled_at'] = now()->toIso8601String();
        $node->forceFill(['usage' => $usage, 'capacity' => $capacity])->save();

        return array_diff_key($usage, ['sampled_at' => 1]) != array_diff_key($before, ['sampled_at' => 1]);
    }

    /** Once a day per node: a node with no room left is an outage waiting for the next customer who saves a file. */
    private function tellOperators(Node $node, float $free): void
    {
        $tags = (array) ($node->tags ?? []);
        if ((string) data_get($tags, 'disk_low_on') === now()->toDateString()) {
            return;
        }
        $tags['disk_low_on'] = now()->toDateString();
        $node->forceFill(['tags' => $tags])->save();
        $context = CommandContext::system('nodes.usage');
        $this->audit->record($context, 'node.disk.low', 'succeeded', ['node' => $node->name, 'free_pct' => $free, 'used_gb' => (int) $node->use('disk_used_gb'), 'disk_gb' => (int) $node->cap('disk_gb')], 'node', $node->id);
        $this->outbox->publish(GenericEvent::of('node.disk.low', 'node', $node->id, [
            'node' => $node->name, 'role' => $node->role, 'region' => $node->region_code, 'free_pct' => $free,
            'used_gb' => (int) $node->use('disk_used_gb'), 'disk_gb' => (int) $node->cap('disk_gb'), 'headroom_pct' => self::LOW_FREE_PCT,
        ]));
    }
}
