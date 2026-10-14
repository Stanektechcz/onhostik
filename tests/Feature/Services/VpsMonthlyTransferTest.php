<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Catalog\PlanPromises;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Metering\MetricRegistry;
use Onhost\Domain\Services\Metering\UsageMetrics;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\UsageWatch;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Phase T, metering M2 (docs/proposals/metering-after-launch.md §2): VPS and VDS plans sell a monthly transfer (`traffic_tb`) that
 * nothing read — UsageWatch measured transfer only for web hosting. The month's transfer of the VM now comes from Proxmox (RRD
 * netin + netout) into the usage watch as the soft metric `transfer`: it warns from 85 %, and it never blocks, throttles, disconnects
 * or orders anything. It is a metric new on the cloud family, so it tells the customer only once the operator switches
 * `onhost.metering.enforce_new_metrics` on (P-R24, D+30); until then it is measured and kept as an observed value.
 */

const T3_TIB = 1024 ** 4;

/** A VPS on Proxmox selling 1 TB a month. */
function t3Vps(Organization $org, array $entitlements = ['vcpu' => 2, 'ram_mb' => 4096, 'nvme_gb' => 80, 'traffic_tb' => 1], array $tags = []): Service
{
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 2', 'hostname' => 'vm-t3.cust.onhost.cz', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1',
        'provider_instance_id' => $instance->id, 'node_id' => $node->id, 'desired_spec' => ['executor' => 'proxmox', 'family' => 'cloud'], 'entitlements' => $entitlements, 'sla_class' => 'standard',
        'activated_at' => now()->subMonths(2), 'tags' => $tags,
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => [], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "provision:{$service->id}:qemu", 'adapter_version' => '1.0.0']);

    return $service;
}

/** Proxmox answering a VM that has moved `$share` of 1 TiB since the start of the month (12-hour RRD steps), or no RRD data at all. */
function t3Transfer(?float $share, array &$rrdAsked): void
{
    $points = [];
    $from = Carbon::parse('2026-10-01 00:00:00', 'UTC')->getTimestamp();
    $steps = 39; // 1 Oct 00:00 … 20 Oct 00:00
    $rate = $share === null ? null : (int) floor($share * T3_TIB / ($steps * 43200));
    for ($i = 0; $i < $steps; $i++) {
        $points[] = ['time' => $from + $i * 43200] + ($rate === null ? [] : ['netin' => $rate, 'netout' => 0]);
    }
    Http::fake(function (Request $request) use ($points, &$rrdAsked) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        if (str_ends_with($path, '/rrddata')) {
            $rrdAsked[] = $path;

            return Http::response(['data' => $points]);
        }

        return str_ends_with($path, '/status/current')
            ? Http::response(['data' => ['status' => 'running', 'cpu' => 0.1, 'mem' => 1024 ** 3, 'maxmem' => 4 * 1024 ** 3, 'maxdisk' => 80 * 1024 ** 3]])
            : Http::response(['errors' => ['unexpected' => $path]], 500);
    });
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-20 10:00:00', 'UTC')); // travel first, then seed (time bomb, TASK-0192)
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('measures the month\'s transfer of a VPS against traffic_tb and keeps it observed until new metrics are enforced', function () {
    [, $org] = $this->customerWithOrganization();
    $service = t3Vps($org);
    $asked = [];
    t3Transfer(0.9, $asked);
    $watch = app(UsageWatch::class);

    $transfer = collect($watch->readings($service))->firstWhere('metric', 'transfer');
    expect($transfer)->not->toBeNull()->and($transfer->limit)->toBe(T3_TIB)->and($transfer->pct())->toBe(90)
        ->and($asked)->toHaveCount(1);
    // new on the cloud family: observed, tells nobody (P-R24: enforce_new_metrics at D+30)
    expect($watch->measure($service))->not->toHaveKey('transfer');

    config(['onhost.metering.enforce_new_metrics' => true]);
    $metrics = $watch->measure($service);
    expect($metrics['transfer'] ?? null)->toMatchArray(['limit' => T3_TIB, 'pct' => 90])
        ->and(UsageWatch::level($metrics))->toBe('warn')
        ->and(UsageWatch::metricLabel('transfer'))->toBe('měsíční přenos dat')
        // a soft limit: it tells, it never blocks or orders
        ->and(UsageMetrics::isSoft('transfer'))->toBeTrue()
        ->and(UsageMetrics::drivesConsequences('transfer', 'cloud'))->toBeFalse()
        ->and(UsageWatch::confirmedCritical(['transfer' => ['pct' => 120]], ['transfer' => ['used' => 2, 'limit' => 1, 'pct' => 130]], 'cloud'))->toBeFalse();
});

it('tells the customer a used-up transfer without cutting anything off or ordering a bigger plan', function () {
    config(['onhost.metering.enforce_new_metrics' => true]);
    [, $org] = $this->customerWithOrganization();
    $service = t3Vps($org, tags: ['policy' => ['auto_upgrade' => true], 'usage' => ['metrics' => ['transfer' => ['used' => 1, 'limit' => 1, 'pct' => 110]]]]);
    $asked = [];
    t3Transfer(1.2, $asked);

    $stats = app(UsageWatch::class)->run();
    app(OutboxPublisher::class)->relayPending();

    expect($stats['upgraded'])->toBe(0)
        ->and(Order::query()->where('organization_id', $org->id)->exists())->toBeFalse()
        ->and($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE)
        ->and(data_get($service->fresh()->tags, 'usage.metrics.transfer.pct'))->toBe(120);
    $notice = Notification::query()->where('organization_id', $org->id)->where('title', 'like', '%(měsíční přenos dat)%')->first();
    expect($notice)->not->toBeNull()
        ->and((string) $notice?->body)->toContain('neomezujeme')->not->toContain('nejde přidávat další obsah');
});

it('records a month without RRD data as not measured, never as zero', function () {
    config(['onhost.metering.enforce_new_metrics' => true]);
    [, $org] = $this->customerWithOrganization();
    $service = t3Vps($org);
    $asked = [];
    t3Transfer(null, $asked);

    $transfer = collect(app(UsageWatch::class)->readings($service))->firstWhere('metric', 'transfer');
    expect($transfer->value)->toBeNull();
    [, , $unavailable] = UsageWatch::split(app(UsageWatch::class)->readings($service), 'cloud');
    expect($unavailable)->toHaveKey('transfer');
});

it('does not ask Proxmox for the transfer of a plan that sells none', function () {
    [, $org] = $this->customerWithOrganization();
    $service = t3Vps($org, ['vcpu' => 2, 'ram_mb' => 4096, 'nvme_gb' => 80]);
    $asked = [];
    t3Transfer(0.5, $asked);

    expect(collect(app(UsageWatch::class)->readings($service))->firstWhere('metric', 'transfer'))->toBeNull()->and($asked)->toBe([]);
});

it('keeps traffic_tb for VPS/VDS and still names the CDN add-on that nothing measures', function () {
    expect(MetricRegistry::get('traffic_tb')['status'])->toBe(MetricRegistry::MEASURED)
        ->and(MetricRegistry::isKept('traffic_tb', 'cloud'))->toBeTrue()
        ->and(MetricRegistry::isKept('traffic_tb', 'addon'))->toBeFalse()
        ->and(MetricRegistry::get('traffic_tb')['gap_families'] ?? [])->toHaveKey('addon')
        // the CDN add-on (not on sale, P-R46) still sells it, so the ratchet keeps the key until the CDN is decided
        ->and(PlanPromises::KNOWN_GAPS)->toHaveKey('traffic_tb')
        ->and(PlanPromises::KNOWN_GAPS['traffic_tb'])->toContain('CDN');
});
