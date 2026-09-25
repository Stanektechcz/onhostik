<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Domain\Provisioning\PlacementService;
use Onhost\Domain\Provisioning\Scheduling\NodeScheduler;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandContext;

beforeEach(fn () => $this->seed([CatalogSeeder::class]));

function placementLab(): array
{
    Region::query()->firstOrCreate(['code' => 'cz1'], ['name' => 'Praha', 'country' => 'CZ', 'state' => 'active']);
    $isp = ProviderInstance::query()->firstOrCreate(['key' => 'ispconfig-shared01'], ['provider' => 'ispconfig', 'name' => 'ISPConfig shared01', 'region_code' => 'cz1', 'base_url' => 'https://shared01.mgmt.test:8080', 'secret_ref' => 'env://ISPCONFIG_SHARED01', 'state' => 'active', 'capabilities' => ['web.create' => true]]);
    $aap = ProviderInstance::query()->firstOrCreate(['key' => 'aapanel-managed01'], ['provider' => 'aapanel', 'name' => 'aaPanel managed01', 'region_code' => 'cz1', 'base_url' => 'https://managed01.mgmt.test:8888', 'secret_ref' => 'env://AAPANEL_MANAGED01', 'state' => 'active', 'capabilities' => ['web.create' => true]]);
    $pve = ProviderInstance::query()->firstOrCreate(['key' => 'proxmox-cz1'], ['provider' => 'proxmox', 'name' => 'PVE', 'region_code' => 'cz1', 'base_url' => 'https://pve.mgmt.test:8006', 'secret_ref' => 'env://PROXMOX_CZ1', 'state' => 'active', 'capabilities' => ['vm.create' => true]]);
    $nodes = [];
    foreach ([[$isp, 'isp-web01'], [$isp, 'isp-web02'], [$aap, 'aap-web01']] as [$instance, $name]) {
        $nodes[$name] = Node::query()->firstOrCreate(['provider_instance_id' => $instance->id, 'name' => $name], ['region_code' => 'cz1', 'role' => 'web', 'state' => 'active', 'capacity' => ['cpu_cores' => 32, 'ram_mb' => 131072, 'disk_gb' => 4000], 'usage' => ['cpu_pct' => 10, 'ram_used_mb' => 8192, 'disk_used_gb' => 100]]);
    }

    return [$isp, $aap, $pve, $nodes];
}

it('lets staff pin a plan to a panel and a server, validates compatibility and drives the scheduler', function () {
    [$isp, $aap, $pve, $nodes] = placementLab();
    $staff = $this->staff('platform_owner');
    $this->actingAs($staff, 'sanctum');

    $overview = $this->getJson('/v1/staff/placements')->assertOk()->json('data');
    expect(collect($overview['products'])->firstWhere('key', 'web-hosting')['compatible'])->toContain('ispconfig', 'aapanel')
        ->and(collect($overview['instances'])->firstWhere('key', 'ispconfig-shared01')['nodes'])->toHaveCount(2)
        ->and($overview['placements'])->toBe([]);

    // the Standard plan of web hosting runs on the aaPanel instance, the rest of the product on ISPConfig node isp-web02
    $this->putJson('/v1/staff/placements', ['product_key' => 'web-hosting', 'plan_key' => 'standard', 'provider_instance_key' => 'aapanel-managed01'], ['Idempotency-Key' => 'pl-1'])->assertCreated()->assertJsonPath('provider', 'aapanel');
    // Profi sells dedicated PHP workers: never on a panel with one PHP pool for the whole node (decision 7, TASK-0023)
    $this->putJson('/v1/staff/placements', ['product_key' => 'web-hosting', 'plan_key' => 'profi', 'provider_instance_key' => 'aapanel-managed01'], ['Idempotency-Key' => 'pl-1b'])->assertUnprocessable()->assertJsonPath('error', 'placement_requires_dedicated_php');
    $this->putJson('/v1/staff/placements', ['product_key' => 'web-hosting', 'provider_instance_key' => 'ispconfig-shared01', 'node_id' => 'isp-web02'], ['Idempotency-Key' => 'pl-2'])->assertCreated()->assertJsonPath('node_name', 'isp-web02');
    $this->putJson('/v1/staff/placements', ['product_key' => 'web-hosting', 'plan_key' => 'start', 'provider_instance_key' => 'proxmox-cz1'], ['Idempotency-Key' => 'pl-3'])->assertUnprocessable()->assertJsonPath('error', 'placement_incompatible');
    $this->putJson('/v1/staff/placements', ['product_key' => 'web-hosting', 'plan_key' => 'nope', 'provider_instance_key' => 'ispconfig-shared01'], ['Idempotency-Key' => 'pl-4'])->assertUnprocessable()->assertJsonPath('error', 'placement_plan_unknown');
    $this->putJson('/v1/staff/placements', ['product_key' => 'web-hosting', 'provider_instance_key' => 'aapanel-managed01', 'node_id' => 'isp-web01'], ['Idempotency-Key' => 'pl-5'])->assertUnprocessable()->assertJsonPath('error', 'placement_node_unknown');

    $placements = app(PlacementService::class);
    expect($placements->resolve('web-hosting', 'standard', 'cz1')->providerInstance->key)->toBe('aapanel-managed01')
        ->and($placements->resolve('web-hosting', 'start', 'cz1')->node->name)->toBe('isp-web02')
        ->and($placements->resolve('vps', 'compute-2', 'cz1'))->toBeNull();

    // the scheduler honours the pin: only the pinned node is a candidate even though isp-web01 is emptier
    $scheduler = app(NodeScheduler::class);
    $pick = $scheduler->pick(['role' => 'web', 'region' => 'cz1', 'provider' => 'ispconfig', 'placement' => ['instance_id' => $isp->id, 'node_id' => $nodes['isp-web02']->id]]);
    expect($pick['node']->name)->toBe('isp-web02')->and($pick['instance']->key)->toBe('ispconfig-shared01');
    $free = $scheduler->pick(['role' => 'web', 'region' => 'cz1', 'provider' => 'aapanel', 'placement' => ['instance_id' => $aap->id]]);
    expect($free['node']->name)->toBe('aap-web01');

    // customers never see placements or vendor names
    [$customer] = $this->customerWithOrganization();
    $this->actingAs($customer, 'sanctum');
    $this->getJson('/v1/staff/placements')->assertForbidden();

    $this->actingAs($staff, 'sanctum');
    $id = collect($this->getJson('/v1/staff/placements')->json('data.placements'))->firstWhere('plan_key', 'standard')['id'];
    $this->deleteJson("/v1/staff/placements/{$id}")->assertOk()->assertJsonPath('deleted', true);
    expect($placements->resolve('web-hosting', 'standard', 'cz1')->node->name)->toBe('isp-web02'); // falls back to the product-wide placement
    expect(AuditEvent::query()->whereIn('action', ['placement.upsert', 'placement.delete'])->count())->toBe(3);
});

it('provisions a new web service on the panel chosen for its plan', function () {
    [$isp, $aap] = placementLab();
    app(PlacementService::class)->upsert(['product_key' => 'web-hosting', 'plan_key' => 'standard', 'provider_instance_key' => 'aapanel-managed01'], CommandContext::system('test'));
    [$owner, $org] = $this->customerWithOrganization();
    $product = Product::query()->where('key', 'web-hosting')->firstOrFail();
    $version = fn (string $plan) => $product->plans()->where('key', $plan)->firstOrFail()->currentVersion();
    $services = app(ServiceService::class);
    $service = $services->create($org, $product, $version('standard'), ['domain' => 'placed.cz'], $this->contextFor($owner, $org));
    expect($service->desired_spec['executor'])->toBe('aapanel')->and($service->desired_spec['placement']['instance_key'])->toBe('aapanel-managed01');
    $other = $services->create($org, $product, $version('start'), ['domain' => 'free.cz'], $this->contextFor($owner, $org));
    expect($other->desired_spec['executor'])->toBe('ispconfig')->and($other->desired_spec['placement'])->toBeNull();
});
