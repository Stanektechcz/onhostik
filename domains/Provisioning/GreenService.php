<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Illuminate\Support\Collection;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\NodeUsageSample;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Service;

/**
 * Green hosting (audit §5j-10): every region declares its energy source and carbon intensity (`config onhost.green`),
 * a node may override it (`tags.energy`), and the footprint of an organization is estimated from what it has running
 * — RAM sold × watts per GB × hours × PUE × gCO₂/kWh. The number is an estimate for RFPs and invoices (a
 * transparent model, stated as such), never a measured meter reading. Nothing here is customer-editable.
 */
final class GreenService
{
    /** @return array<string, array{label:string, source:string, gco2_per_kwh:float, pue:float, renewable_pct:int}> */
    public function regions(): array
    {
        $out = [];
        foreach ((array) config('onhost.green.regions', []) as $key => $region) {
            $out[(string) $key] = $this->normalize((array) $region, (string) $key);
        }

        return $out;
    }

    /** @return array{label:string, source:string, gco2_per_kwh:float, pue:float, renewable_pct:int} */
    public function region(?string $key): array
    {
        $regions = $this->regions();

        return $regions[$key ?? ''] ?? $this->normalize(['label' => $key ?? 'unknown', 'source' => 'grid', 'gco2_per_kwh' => (float) config('onhost.green.default_gco2_per_kwh', 400), 'pue' => 1.5, 'renewable_pct' => 0], $key ?? 'unknown');
    }

    /** A node inherits its region's profile unless the operator tagged it (`tags.energy.{source,gco2_per_kwh,pue,renewable_pct}`). @return array{label:string, source:string, gco2_per_kwh:float, pue:float, renewable_pct:int} */
    public function nodeEnergy(Node $node): array
    {
        $base = $this->region($node->region_code);
        $tag = (array) data_get($node->tags, 'energy', []);

        return $tag === [] ? $base : $this->normalize(array_merge($base, array_intersect_key($tag, array_flip(['label', 'source', 'gco2_per_kwh', 'pue', 'renewable_pct']))), (string) $node->region_code);
    }

    /**
     * Monthly estimate for an organization: per active service the RAM it is entitled to, the region it runs in.
     *
     * @return array{kwh:float, gco2:float, renewable_pct:int, services:int, rows:list<array{service_id:string,label:string,region:string,ram_gb:float,kwh:float,gco2:float,source:string}>, model:array{watts_per_gb_ram:float,hours:int}, statement:string}
     */
    public function footprint(Organization|string $organization): array
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;
        $services = Service::query()->where('organization_id', $organizationId)->whereIn('state', ['ACTIVE', 'DEGRADED', 'SUSPENDED'])->get();
        $nodes = Node::query()->whereIn('id', $services->pluck('node_id')->filter()->unique())->get()->keyBy('id');

        return $this->estimate($services, $nodes);
    }

    /** The same estimate over the whole fleet (public transparency page). @return array<string,mixed> */
    public function platform(): array
    {
        $nodes = Node::query()->whereIn('state', ['active', 'drain', 'draining', 'maintenance'])->get();
        $byRegion = [];
        $renewableCapacity = 0.0;
        $capacity = 0.0;
        $measuredNodes = 0;
        $measuredWatts = 0;
        foreach ($nodes as $node) {
            $energy = $this->nodeEnergy($node);
            if (($m = $this->measuredWatts($node)) !== null) {
                $measuredNodes++;
                $measuredWatts += $m['watts'];
            }
            $ram = (float) $node->cap('ram_mb');
            $capacity += $ram;
            $renewableCapacity += $ram * $energy['renewable_pct'] / 100;
            $key = (string) ($node->region_code ?? 'unknown');
            $byRegion[$key] = $byRegion[$key] ?? ['region' => $key, 'label' => $energy['label'], 'source' => $energy['source'], 'gco2_per_kwh' => $energy['gco2_per_kwh'], 'pue' => $energy['pue'], 'renewable_pct' => $energy['renewable_pct'], 'nodes' => 0];
            $byRegion[$key]['nodes']++;
        }

        return [
            'renewable_pct' => $capacity > 0 ? (int) round($renewableCapacity / $capacity * 100) : (int) round(collect($this->regions())->avg('renewable_pct') ?? 0),
            'regions' => array_values($byRegion) ?: array_values(array_map(fn ($r, $k) => $r + ['region' => $k, 'nodes' => 0], $this->regions(), array_keys($this->regions()))),
            'model' => ['watts_per_gb_ram' => (float) config('onhost.green.watts_per_gb_ram', 3.5), 'hours' => 730],
            'measured' => ['nodes' => $measuredNodes, 'of' => $nodes->count(), 'watts' => $measuredWatts], // §5k-6: how much of the fleet reports real power
            'statement' => (string) config('onhost.green.statement', 'Odhad podle prodané paměti, PUE datového centra a uhlíkové intenzity dodavatele energie.'),
        ];
    }

    /** What an invoice carries (`meta.green`): the footprint of the month for the buyer, computed at issue. @return array{kwh:float, gco2:float, renewable_pct:int, services:int, statement:string} */
    public function invoiceSnapshot(Organization $organization): array
    {
        $footprint = $this->footprint($organization);

        return ['kwh' => $footprint['kwh'], 'gco2' => $footprint['gco2'], 'renewable_pct' => $footprint['renewable_pct'], 'services' => $footprint['services'], 'statement' => $footprint['statement']];
    }

    /** Badge SVG for a customer's own site ("hosted on renewable energy") — a static drawing, no tracking. */
    public function badgeSvg(int $renewablePct, string $locale = 'cs'): string
    {
        $text = $renewablePct >= 90 ? ($locale === 'en' ? '100 % renewable hosting' : 'Hosting ze 100 % obnovitelné energie') : ($locale === 'en' ? "{$renewablePct} % renewable hosting" : "Hosting z {$renewablePct} % obnovitelné energie");
        $width = 60 + (int) (mb_strlen($text) * 6.4);
        $safe = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="24" role="img" aria-label="'.$safe.'"><rect width="'.$width.'" height="24" rx="4" fill="#1a3d2b"/><circle cx="14" cy="12" r="6" fill="#b8ff2e"/><text x="27" y="16" font-family="system-ui,Segoe UI,sans-serif" font-size="11" fill="#f3f2f2">'.$safe.'</text></svg>';
    }

    /**
     * @param  Collection<int, Service>  $services
     * @param  Collection<string, Node>  $nodes
     * @return array{kwh:float, gco2:float, renewable_pct:int, services:int, rows:list<array<string,mixed>>, model:array{watts_per_gb_ram:float,hours:int}, statement:string}
     */
    private function estimate(Collection $services, Collection $nodes): array
    {
        $wattsPerGb = (float) config('onhost.green.watts_per_gb_ram', 3.5);
        $hours = 730;
        $rows = [];
        $kwhTotal = 0.0;
        $gco2Total = 0.0;
        $renewableKwh = 0.0;
        $nodeWeights = [];
        foreach ($services as $service) {
            $node = $service->node_id ? $nodes->get($service->node_id) : null;
            $energy = $node !== null ? $this->nodeEnergy($node) : $this->region($service->region_code);
            $ramGb = max(0.5, ((float) data_get($service->entitlements, 'ram_mb', 0) ?: (float) data_get($service->desired_spec, 'ram_mb', 0) ?: 512) / 1024);
            $measured = $node !== null ? $this->measuredWatts($node) : null;
            $vmWatts = $this->serviceWatts($service);
            if ($vmWatts !== null) { // §5m-6: the host reported this VM's own power
                $kwh = round($vmWatts * $hours / 1000, 2);
                $telemetry = true;
            } elseif ($measured !== null) { // §5k-6: a probe reported the node's power; the service takes its share of the node by RAM (the PUE is already in the wall reading)
                // §5l-6: the split follows the hypervisor telemetry of every service on the node (measured memory in `tags.usage`) when there is any, the sold RAM otherwise
                $nodeWeights[$node->id] ??= $this->nodeWeights($node);
                $weights = $nodeWeights[$node->id];
                $telemetry = ! empty($weights['telemetry'][$service->id]);
                $denominator = max($weights['total'], (float) $node->cap('ram_mb') * 1048576); // unused capacity is the host's idle overhead, charged to nobody
                $share = $denominator > 0 && isset($weights['services'][$service->id]) ? min(1.0, $weights['services'][$service->id] / $denominator) : min(1.0, $ramGb * 1024 / max(1.0, (float) ($node->cap('ram_mb') ?: $ramGb * 1024)));
                $kwh = round(max(0, $measured['watts'] - $weights['vm_watts']) * $hours / 1000 * $share, 2);
            } else {
                $kwh = round($ramGb * $wattsPerGb * $hours * $energy['pue'] / 1000, 2);
            }
            $gco2 = round($kwh * $energy['gco2_per_kwh'], 1);
            $kwhTotal += $kwh;
            $gco2Total += $gco2;
            $renewableKwh += $kwh * $energy['renewable_pct'] / 100;
            $rows[] = ['service_id' => $service->id, 'label' => $service->name ?: $service->product_key, 'region' => (string) ($service->region_code ?? ''), 'ram_gb' => round($ramGb, 2), 'kwh' => $kwh, 'gco2' => $gco2, 'source' => $energy['source'], 'basis' => $vmWatts !== null || $measured !== null ? 'measured' : 'model', 'split' => $vmWatts !== null ? 'vm' : ($measured !== null ? ($telemetry ? 'telemetry' : 'ram') : null)];
        }

        return [
            'kwh' => round($kwhTotal, 2), 'gco2' => round($gco2Total, 1), 'renewable_pct' => $kwhTotal > 0 ? (int) round($renewableKwh / $kwhTotal * 100) : (int) round(collect($this->regions())->avg('renewable_pct') ?? 0),
            'services' => count($rows), 'rows' => $rows, 'model' => ['watts_per_gb_ram' => $wattsPerGb, 'hours' => $hours],
            'statement' => (string) config('onhost.green.statement', 'Odhad podle prodané paměti, PUE datového centra a uhlíkové intenzity dodavatele energie.'),
        ];
    }

    /** Per-VM watts (§5m-6) when a host reported them recently enough; null otherwise. */
    public function serviceWatts(Service $service): ?int
    {
        $watts = data_get($service->tags, 'power.watts');
        $at = data_get($service->tags, 'power.at');
        if ($watts === null) { // §5n-6: a hypervisor that reports the VM's own draw in its usage telemetry
            $watts = data_get($service->tags, 'usage.power_w');
            $at = data_get($service->tags, 'usage.sampled_at', data_get($service->tags, 'usage.at'));
        }
        if ($watts === null || $at === null) {
            return null;
        }

        return now()->parse((string) $at) >= now()->subHours(max(1, (int) config('onhost.green.measured_max_age_hours', 24))) ? max(0, (int) $watts) : null;
    }

    /**
     * How a measured node's power splits between its services (§5l-6): the memory the hypervisor reports per service
     * (`tags.usage.memory.used`, written by the usage watch) weighs the split; a service without telemetry weighs its sold RAM.
     *
     * @return array{services:array<string,float>, telemetry:array<string,bool>, total:float, vm_watts:int}
     */
    public function nodeWeights(Node $node): array
    {
        $out = ['services' => [], 'telemetry' => [], 'total' => 0.0, 'vm_watts' => 0];
        foreach (Service::query()->where('node_id', $node->id)->whereIn('state', ['ACTIVE', 'DEGRADED', 'SUSPENDED'])->get() as $peer) {
            if (($own = $this->serviceWatts($peer)) !== null) {
                $out['vm_watts'] += $own; // a VM with its own reading is charged directly (§5m-6); the rest of the node splits what is left

                continue;
            }
            $measured = (float) data_get($peer->tags, 'usage.memory.used', 0);
            $weight = $measured > 0 ? $measured : max(512.0, (float) (data_get($peer->entitlements, 'ram_mb', 0) ?: data_get($peer->desired_spec, 'ram_mb', 0) ?: 512)) * 1048576;
            $out['services'][$peer->id] = $weight;
            $out['telemetry'][$peer->id] = $measured > 0;
            $out['total'] += $weight;
        }

        return $out;
    }

    /**
     * Measured power (§5k-6): a PDU or IPMI probe reported the node's wall watts recently enough (`green.measured_max_age_hours`).
     *
     * @return array{watts:int, at:string}|null
     */
    public function measuredWatts(Node $node): ?array
    {
        $watts = data_get($node->usage, 'power_w');
        $at = data_get($node->usage, 'power_sampled_at');
        if ($watts === null || $at === null) {
            return null;
        }
        $maxAge = max(1, (int) config('onhost.green.measured_max_age_hours', 24));
        $sampled = now()->parse((string) $at);

        return $sampled >= now()->subHours($maxAge) ? ['watts' => max(0, (int) $watts), 'at' => $sampled->toIso8601String()] : null;
    }

    /**
     * Probe ingest (§5k-6): `[{node, watts, at?}]` — the node by name or id; the reading lands on the node (`usage.power_w`)
     * and in the hourly samples, so the footprint and the fleet read the measurement instead of the model.
     *
     * @param  list<array{node:string, watts:int|float, at?:string|null}>  $readings
     * @return array{accepted:int, unknown:list<string>}
     */
    public function ingest(array $readings): array
    {
        $accepted = 0;
        $unknown = [];
        foreach ($readings as $reading) {
            $key = (string) ($reading['node'] ?? '');
            $node = Node::query()->where('name', $key)->orWhere('id', $key)->first();
            if ($node === null) {
                $unknown[] = $key;

                continue;
            }
            $at = isset($reading['at']) && $reading['at'] !== null ? now()->parse((string) $reading['at']) : now();
            $watts = max(0, (int) round((float) ($reading['watts'] ?? 0)));
            $node->forceFill(['usage' => array_merge((array) ($node->usage ?? []), ['power_w' => $watts, 'power_sampled_at' => $at->toIso8601String()])])->save();
            foreach ((array) ($reading['vms'] ?? []) as $vm) { // §5m-6: per-VM watts from IPMI/Redfish per-slot sensors or hypervisor telemetry, matched to the service through its provider binding
                $binding = ProviderBinding::query()->where('provider_instance_id', $node->provider_instance_id)->where('remote_id', (string) ($vm['id'] ?? ''))->first();
                $service = $binding !== null ? Service::query()->find($binding->service_id) : null;
                if ($service === null) {
                    $unknown[] = $key.'/'.(string) ($vm['id'] ?? '');

                    continue;
                }
                $service->forceFill(['tags' => array_merge((array) ($service->tags ?? []), ['power' => ['watts' => max(0, (int) round((float) ($vm['watts'] ?? 0))), 'at' => $at->toIso8601String()]])])->save();
            }
            NodeUsageSample::query()->create([
                'node_id' => $node->id, 'sampled_at' => $at, 'cpu_pct' => (int) data_get($node->usage, 'cpu_pct', 0), 'ram_used_mb' => (int) data_get($node->usage, 'ram_used_mb', 0), 'disk_used_gb' => (int) data_get($node->usage, 'disk_used_gb', 0), 'power_w' => $watts, 'source' => 'probe',
            ]);
            $accepted++;
        }

        return ['accepted' => $accepted, 'unknown' => $unknown];
    }

    /** @param  array<string,mixed>  $region @return array{label:string, source:string, gco2_per_kwh:float, pue:float, renewable_pct:int} */
    private function normalize(array $region, string $key): array
    {
        $source = (string) ($region['source'] ?? 'grid');
        $renewable = isset($region['renewable_pct']) ? (int) $region['renewable_pct'] : ($source === 'renewable' ? 100 : 0);

        return [
            'label' => (string) ($region['label'] ?? strtoupper($key)), 'source' => $source, 'gco2_per_kwh' => (float) ($region['gco2_per_kwh'] ?? config('onhost.green.default_gco2_per_kwh', 400)),
            'pue' => max(1.0, (float) ($region['pue'] ?? 1.5)), 'renewable_pct' => max(0, min(100, $renewable)),
        ];
    }
}
