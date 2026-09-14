<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;
use Onhost\Providers\Contracts\HostPowerProvider;
use Onhost\Providers\Redfish\RedfishPowerProvider;
use Throwable;

/**
 * Per-host power without a probe (audit §5n-6): a node whose `tags.bmc` names a management controller
 * (`{driver: redfish, url, chassis?, secret_ref?, insecure?}`) is read by the hourly usage watch before it snapshots; the
 * reading lands where a probe's would (`usage.power_w` + `usage.power_sampled_at`, then the hourly sample) so the
 * footprint and the fleet measure instead of modelling. Per-VM watts a hypervisor reports in its usage telemetry
 * (`tags.usage.power_w`) are picked up by the footprint the same way (`GreenService::serviceWatts()`).
 */
final class HostPowerReader
{
    /** @var array<string,HostPowerProvider> */
    private array $drivers = [];

    public function __construct(private readonly SecretStore $secrets, private readonly OutboxPublisher $outbox, private readonly CacheRepository $cache)
    {
        $this->drivers['redfish'] = new RedfishPowerProvider;
    }

    public function register(string $driver, HostPowerProvider $provider): void
    {
        $this->drivers[$driver] = $provider;
    }

    /** Reads every node with a controller. @return array{read:list<string>, failed:list<string>} */
    public function sweep(): array
    {
        $out = ['read' => [], 'failed' => []];
        foreach (Node::query()->whereIn('state', ['active', 'drain', 'draining', 'maintenance'])->get() as $node) {
            $bmc = (array) data_get($node->tags, 'bmc', []);
            if ($bmc === [] || empty($bmc['url'])) {
                continue;
            }
            $reading = $this->read($node);
            if ($reading === null) {
                $out['failed'][] = $node->name;

                continue;
            }
            $inventory = $this->inventory($node); // §5o-6: temperatures and PSU/fan health from the same controller
            $node->forceFill(['usage' => array_merge((array) ($node->usage ?? []), ['power_w' => $reading['watts'], 'power_sampled_at' => $reading['at'], 'power_source' => $reading['source']], $inventory !== null ? ['bmc' => $inventory + ['at' => $reading['at']]] : [])])->save();
            if ($inventory !== null) {
                $this->alert($node, $inventory);
            }
            $out['read'][] = $node->name;
        }

        return $out;
    }

    /** @return array{temp_max_c:?int, fans_failed:int, psus:list<array{name:string, health:string}>, psu_failed:int}|null */
    public function inventory(Node $node): ?array
    {
        $bmc = (array) data_get($node->tags, 'bmc', []);
        $driver = $this->drivers[(string) ($bmc['driver'] ?? 'redfish')] ?? null;
        if ($driver === null || empty($bmc['url'])) {
            return null;
        }
        try {
            $credentials = ! empty($bmc['secret_ref']) ? $this->secrets->read(SecretRef::parse((string) $bmc['secret_ref'])) : [];
        } catch (Throwable) {
            return null;
        }

        return $driver->inventory($bmc, $credentials);
    }

    /** A hot host or a failed PSU/fan reaches operations once a day per node (§5o-6). @param array<string,mixed> $inventory */
    private function alert(Node $node, array $inventory): void
    {
        $warn = max(1, (int) config('onhost.green.bmc.temp_warn_c', 75));
        $problems = [];
        if ($inventory['temp_max_c'] !== null && $inventory['temp_max_c'] >= $warn) {
            $problems[] = 'teplota '.$inventory['temp_max_c'].' °C';
        }
        if ((int) $inventory['psu_failed'] > 0) {
            $problems[] = $inventory['psu_failed'].'× zdroj mimo OK';
        }
        if ((int) $inventory['fans_failed'] > 0) {
            $problems[] = $inventory['fans_failed'].'× ventilátor mimo OK';
        }
        if ($problems === []) {
            return;
        }
        $key = 'onhost:bmc:alert:'.$node->id.':'.now()->toDateString();
        if ($this->cache->has($key)) {
            return;
        }
        $this->cache->put($key, 1, 86400);
        $this->outbox->publish(GenericEvent::of('node.bmc.alert', 'node', $node->id, ['node' => $node->name, 'region' => $node->region_code, 'role' => $node->role, 'problems' => $problems, 'temp_max_c' => $inventory['temp_max_c'], 'psus' => $inventory['psus'], 'fans_failed' => $inventory['fans_failed']]));
    }

    /** @return array{watts:int, at:string, source:string}|null */
    public function read(Node $node): ?array
    {
        $bmc = (array) data_get($node->tags, 'bmc', []);
        $driver = $this->drivers[(string) ($bmc['driver'] ?? 'redfish')] ?? null;
        if ($driver === null || empty($bmc['url'])) {
            return null;
        }
        $credentials = [];
        if (! empty($bmc['secret_ref'])) {
            try {
                $credentials = $this->secrets->read(SecretRef::parse((string) $bmc['secret_ref']));
            } catch (Throwable) {
                return null; // a missing secret is a configuration error the doctor reports, not a reason to fake a reading
            }
        }

        return $driver->read($bmc, $credentials);
    }
}
