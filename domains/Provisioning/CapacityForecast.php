<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Scheduling\NodeRebalancer;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Trend-driven pre-provisioning (audit §5m-7): per role and region the sellable RAM (N+1 view) against what is sold
 * and how fast the measured load grows (the daily slope of every node's 7-day trend); the days left before the pool
 * is sold out reach operations before the scheduler refuses an order. Once a day per pool while it is short.
 */
final class CapacityForecast
{
    public const ROLES = ['compute', 'game', 'web', 'managed', 'mail'];

    public function __construct(private readonly NodeScheduler $scheduler, private readonly NodeRebalancer $rebalancer, private readonly OutboxPublisher $outbox, private readonly CacheRepository $cache) {}

    /**
     * @return list<array{role:string, region:?string, nodes:int, sellable_mb:int, sold_mb:int, used_mb:int, headroom_mb:int, growth_mb_per_day:int, days_left:?int, low:bool, basis:string}>
     */
    public function forecast(): array
    {
        $warnDays = max(1, (int) config('onhost.provisioning.capacity_forecast.warn_days', 30));
        $nodes = Node::query()->whereIn('state', ['active', 'drain'])->get();
        $out = [];
        foreach ($nodes->groupBy(fn (Node $n) => $n->role.'|'.($n->region_code ?? '')) as $key => $group) {
            [$role, $region] = explode('|', (string) $key, 2);
            if (! in_array($role, self::ROLES, true)) {
                continue;
            }
            $region = $region !== '' ? $region : null;
            $sellable = (int) $this->scheduler->sellableCapacity($role, $region)['ram_mb'];
            $sold = (int) Service::query()->whereIn('node_id', $group->pluck('id'))->whereIn('state', ['ACTIVE', 'DEGRADED', 'SUSPENDED', 'PROVISIONING'])->get()->sum(fn (Service $s) => (int) data_get($s->entitlements, 'ram_mb', 0));
            $used = 0;
            $growth = 0;
            $basis = 'sold';
            foreach ($group as $node) {
                $trend = $this->rebalancer->trend($node);
                if ($trend !== null) {
                    $used += (int) $trend['p95_mb'];
                    $growth += max(0, (int) $trend['slope_mb_per_day']);
                    $basis = 'trend';
                } else {
                    $used += (int) $node->use('ram_used_mb');
                }
            }
            $demand = max($sold, $used);
            $headroom = max(0, $sellable - $demand);
            $daysLeft = $growth > 0 ? (int) floor($headroom / $growth) : null;
            $out[] = ['role' => $role, 'region' => $region, 'nodes' => $group->count(), 'sellable_mb' => $sellable, 'sold_mb' => $sold, 'used_mb' => $used, 'headroom_mb' => $headroom, 'growth_mb_per_day' => $growth, 'days_left' => $daysLeft, 'low' => ($daysLeft !== null && $daysLeft < $warnDays) || ($sellable > 0 && $headroom === 0), 'basis' => $basis];
        }
        usort($out, fn ($a, $b) => [$a['role'], (string) $a['region']] <=> [$b['role'], (string) $b['region']]);

        return $out;
    }

    /** Tells operations about pools that run short (once a day per pool). @return list<string> pools warned now */
    public function warn(): array
    {
        $warned = [];
        foreach ($this->forecast() as $pool) {
            if (! $pool['low']) {
                continue;
            }
            $key = 'onhost:capacity:forecast:'.$pool['role'].':'.($pool['region'] ?? '-').':'.now()->toDateString();
            if ($this->cache->has($key)) {
                continue;
            }
            $this->cache->put($key, 1, 86400);
            $this->outbox->publish(GenericEvent::of('capacity.forecast.low', 'capacity', $pool['role'].':'.($pool['region'] ?? 'all'), $pool));
            $warned[] = $pool['role'].':'.($pool['region'] ?? 'all');
        }

        return $warned;
    }
}
