<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\CapacityForecast;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Domain\Provisioning\Scheduling\CapacityBasis;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Errors\DomainError;

/*
 * Owner decision 19 (TASK-0023): the capacity of a node is judged per dimension — disk by what was SOLD on it, RAM and
 * CPU by what is MEASURED. There used to be one switch per panel (`capacity_basis: sold`) that moved RAM and disk
 * together, web nodes were judged by what their sites happen to store today, and every included site and staging copy
 * counted its share of the owner's space a second time. The disk switch stays off until an operator has read the
 * read-only comparison (`onhost:capacity:basis`); nothing already running is moved.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

/** One ISPConfig web node: 1000 GB of disk, 100 GB measured, sellable up to 85 % (850 GB). */
function capacityBasisWebNode(): Node
{
    Region::query()->firstOrCreate(['code' => 'cz1'], ['name' => 'Praha', 'country' => 'CZ', 'state' => 'active']);
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'ispconfig-shared01'], ['provider' => 'ispconfig', 'name' => 'ISPConfig shared01', 'region_code' => 'cz1', 'base_url' => 'https://shared01.mgmt.test:8080', 'secret_ref' => 'env://ISPCONFIG_SHARED01', 'state' => 'active', 'capabilities' => ['web.create' => true]]);

    return Node::query()->firstOrCreate(['provider_instance_id' => $instance->id, 'name' => 'isp-web01'], ['region_code' => 'cz1', 'role' => 'web', 'state' => 'active', 'capacity' => ['cpu_cores' => 32, 'ram_mb' => 65536, 'disk_gb' => 1000], 'usage' => ['cpu_pct' => 10, 'ram_used_mb' => 8192, 'disk_used_gb' => 100], 'last_seen_at' => now()]);
}

function capacityBasisSite(Organization $org, Node $node, int $gb, array $tags = []): Service
{
    return Service::query()->create(['organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Web '.$gb, 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $node->provider_instance_id, 'node_id' => $node->id,
        'desired_spec' => ['executor' => 'ispconfig'], 'entitlements' => ['sites' => 1, 'nvme_gb' => $gb], 'sla_class' => 'standard', 'activated_at' => now()->subDays(3), 'tags' => $tags]);
}

it('judges web disk by what was sold when the disk basis is sold', function () {
    [, $org] = $this->customerWithOrganization();
    $node = capacityBasisWebNode();
    foreach ([200, 200, 200, 200] as $gb) {
        capacityBasisSite($org, $node, $gb);
    }
    $want = ['role' => 'web', 'provider' => 'ispconfig', 'region' => 'cz1', 'disk_gb' => 100];
    $scheduler = app(NodeScheduler::class);

    // measured (today's default): 100 GB stored + 100 GB asked for, well inside 850 GB
    expect(CapacityBasis::defaults()['disk'])->toBe('measured')->and($scheduler->canHost($want))->toBeTrue();

    // sold: 800 GB promised + 100 GB asked for is over the 850 GB the node may sell
    config(['onhost.provisioning.capacity_basis.disk' => 'sold']);
    expect($scheduler->canHost($want))->toBeFalse()
        ->and(fn () => $scheduler->pick($want))->toThrow(fn (DomainError $e) => expect($e->getMessage())->toContain('disk 800+100 > 850'));
    expect($scheduler->headroom($node->fresh())['disk'])->toMatchArray(['total' => 1000.0, 'limit' => 850.0, 'sold' => 800.0, 'measured' => 100.0, 'used' => 800.0, 'free' => 50.0]);

    // the sell ratio is the operator's, per instance or for the platform
    config(['onhost.provisioning.disk_sell_ratio' => 0.95]);
    expect($scheduler->canHost($want))->toBeTrue();
});

it('does not count included sites and staging copies twice', function () {
    [, $org] = $this->customerWithOrganization();
    $node = capacityBasisWebNode();
    $owner = capacityBasisSite($org, $node, 200);
    capacityBasisSite($org, $node, 50, ['parent_service_id' => $owner->id, 'billing' => 'included', 'sites' => ['quota_gb' => 50]]); // carved out of the owner's 200
    capacityBasisSite($org, $node, 200, ['parent_service_id' => $owner->id, 'staging_of' => $owner->id, 'billing' => 'included']);     // the test copy is not sold

    $room = app(NodeScheduler::class)->headroom($node->fresh(), ['disk' => 'sold', 'ram' => 'measured', 'cpu' => 'measured']);
    expect($room['disk']['sold'])->toBe(200.0)->and($room['basis']['disk'])->toBe('sold');
});

it('keeps RAM and CPU measured by default', function () {
    [, $org] = $this->customerWithOrganization();
    $instance = pveLab();
    $node = Node::query()->where('name', 'prg1-n2')->firstOrFail();
    $node->forceFill(['capacity' => ['cpu_cores' => 16, 'ram_mb' => 32768, 'disk_gb' => 1000], 'usage' => ['cpu_pct' => 5, 'ram_used_mb' => 2048, 'disk_used_gb' => 50, 'io_wait_pct' => 1], 'last_seen_at' => now()])->save();
    foreach ([1, 2, 3] as $i) { // 24 GB of RAM sold: the whole sellable share, while the guests idle at 2 GB
        Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute '.$i, 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id, 'desired_spec' => [], 'entitlements' => ['vcpu' => 2, 'ram_mb' => 8192, 'nvme_gb' => 80], 'sla_class' => 'standard', 'activated_at' => now()->subDays(3)]);
    }
    config(['onhost.provisioning.capacity_basis.disk' => 'sold']); // the disk switch does not move RAM with it

    expect(CapacityBasis::for($instance->fresh()))->toBe(['disk' => 'sold', 'ram' => 'measured', 'cpu' => 'measured'])
        ->and(app(NodeScheduler::class)->canHost(['role' => 'compute', 'provider' => 'proxmox', 'region' => 'cz1', 'ram_mb' => 8192, 'cpu_cores' => 2, 'disk_gb' => 80]))->toBeTrue();
});

it('honours the old one-word capacity_basis option of a panel and a per-dimension one', function () {
    $instance = pveLab();
    expect(CapacityBasis::isLegacy($instance))->toBeFalse();

    $instance->forceFill(['options' => array_merge((array) $instance->options, ['capacity_basis' => 'sold'])])->save();
    expect(CapacityBasis::for($instance->fresh()))->toBe(['disk' => 'sold', 'ram' => 'sold', 'cpu' => 'measured'])->and(CapacityBasis::isLegacy($instance->fresh()))->toBeTrue();

    $instance->forceFill(['options' => array_merge((array) $instance->options, ['capacity_basis' => ['disk' => 'sold', 'cpu' => 'sold'], 'disk_sell_ratio' => 0.9])])->save();
    expect(CapacityBasis::for($instance->fresh()))->toBe(['disk' => 'sold', 'ram' => 'measured', 'cpu' => 'measured']) // there is no sold CPU
        ->and(CapacityBasis::isLegacy($instance->fresh()))->toBeFalse()
        ->and(CapacityBasis::diskSellRatio($instance->fresh()))->toBe(0.9)
        ->and(CapacityBasis::diskSellRatio(null))->toBe(0.85);
});

it('refuses a web hosting in the cart when no web node has room under sold disk', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    [, $org] = $this->customerWithOrganization();
    $node = capacityBasisWebNode();
    foreach ([200, 200, 200, 200] as $gb) {
        capacityBasisSite($org, $node, $gb);
    }
    $quote = fn (string $plan) => app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => $plan, 'config' => ['domain' => $plan.'-disk.cz']]], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c', 'vat_status' => 'unknown'], 1, null, $org);

    expect($quote('profi')->subtotal_minor)->toBeGreaterThan(0); // measured: 100 GB stored, 200 GB fit

    config(['onhost.provisioning.capacity_basis.disk' => 'sold']);
    $before = [Quote::query()->count(), Order::query()->count()];
    expect(fn () => $quote('profi'))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('capacity_sold_out')->and($e->status)->toBe(409));
    expect([Quote::query()->count(), Order::query()->count()])->toBe($before)
        ->and($quote('start')->subtotal_minor)->toBeGreaterThan(0); // 10 GB still fit into the 50 left

    config(['onhost.provisioning.capacity_gate' => false]); // the operator's emergency brake still accepts it
    expect($quote('profi')->subtotal_minor)->toBeGreaterThan(0);
});

it('reports both bases with onhost:capacity:basis and writes nothing', function () {
    $this->seed([CatalogSeeder::class]);
    [, $org] = $this->customerWithOrganization();
    $node = capacityBasisWebNode();
    foreach ([200, 200, 200, 250] as $gb) {
        capacityBasisSite($org, $node, $gb);
    }
    $stamp = $node->fresh()->updated_at;
    $before = [Node::query()->count(), Service::query()->count(), AuditEvent::query()->count(), DB::table('outbox_messages')->count()];

    expect(Artisan::call('onhost:capacity:basis', ['--json' => true]))->toBe(0);
    $report = json_decode(Artisan::output(), true);
    $row = collect($report['nodes'])->firstWhere('node', 'isp-web01');
    expect($report['defaults'])->toBe(['disk' => 'measured', 'ram' => 'measured', 'cpu' => 'measured'])
        ->and($row['disk']['measured']['used'])->toEqual(100)->and($row['disk']['sold']['used'])->toEqual(850)
        ->and($row['loses_under_sold_disk'])->toContain('web-hosting/profi', 'web-hosting/start')
        ->and($row['closes_under_sold_disk'])->toBeTrue() // 850 GB sold of 850 sellable: not even the smallest web plan fits
        ->and($report['would_close'])->toContain('isp-web01');

    expect(Artisan::call('onhost:capacity:basis'))->toBe(0);
    expect(Artisan::output())->toContain('isp-web01')->toContain('sold');
    expect([Node::query()->count(), Service::query()->count(), AuditEvent::query()->count(), DB::table('outbox_messages')->count()])->toBe($before)
        ->and($node->fresh()->updated_at?->toIso8601String())->toBe($stamp?->toIso8601String());
});

it('reports the sold disk headroom of web pools in the forecast', function () {
    [, $org] = $this->customerWithOrganization();
    $node = capacityBasisWebNode();
    foreach ([200, 200, 200, 250] as $gb) {
        capacityBasisSite($org, $node, $gb);
    }
    $pool = collect(app(CapacityForecast::class)->forecast())->first(fn ($p) => $p['role'] === 'web' && $p['region'] === 'cz1');
    expect($pool)->toMatchArray(['disk_basis' => 'measured', 'disk_sold_gb' => 850, 'disk_used_gb' => 100, 'disk_sellable_gb' => 850, 'low' => false]);

    config(['onhost.provisioning.capacity_basis.disk' => 'sold']);
    $pool = collect(app(CapacityForecast::class)->forecast())->first(fn ($p) => $p['role'] === 'web' && $p['region'] === 'cz1');
    expect($pool)->toMatchArray(['disk_basis' => 'sold', 'disk_headroom_gb' => 0, 'low' => true]);
});

it('shows staff the basis of the platform and of every panel', function () {
    $instance = pveLab();
    $instance->forceFill(['options' => array_merge((array) $instance->options, ['capacity_basis' => 'sold'])])->save();
    capacityBasisWebNode();

    $basis = $this->actingAs($this->staff('platform_owner'), 'sanctum')->getJson('/v1/staff/capacity')->assertOk()->json('data.basis');
    expect($basis['defaults'])->toBe(['disk' => 'measured', 'ram' => 'measured', 'cpu' => 'measured'])
        ->and($basis['instances']['proxmox-cz1'])->toMatchArray(['provider' => 'proxmox', 'disk' => 'sold', 'ram' => 'sold', 'legacy' => true])
        ->and($basis['instances']['ispconfig-shared01'])->toMatchArray(['disk' => 'measured', 'legacy' => false, 'disk_sell_ratio' => 0.85]);
});
