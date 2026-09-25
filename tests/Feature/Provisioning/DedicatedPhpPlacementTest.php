<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\PlanPlacement;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Domain\Provisioning\PlacementService;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\Scheduling\PlacementRules;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflows\ProvisionWebsiteWorkflow;
use Onhost\Domain\Provisioning\Workflows\Steps\ScheduleNodeStep;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\PlanFit;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/*
 * Owner decision 7 (TASK-0023): a plan that sells dedicated PHP workers runs only where a site gets a PHP pool of its
 * own. ISPConfig writes one FPM pool per site with pm_max_children = the plan's workers (IspConfigWebProvider); aaPanel
 * runs one pool per PHP version for the whole node and answers applied=false to every worker change. Staff could pin
 * web-hosting/profi to aaPanel, and a product-wide aaPanel placement took profi with it — the customer paid for
 * dedicated workers and got a share of a node-wide pool.
 *
 * Verified limit of the rule: eshop/shop-peak (a managed product that runs on aaPanel) is NOT moved to ISPConfig —
 * there it would lose the WAF rate limit its "pro" level promises (ISPConfig applies none) and the per-customer site
 * count ISPConfig enforces does not count a plan without a `sites` number. It stays where it runs and is reported
 * (onhost:doctor, onhost:capacity:basis) as a promise the platform does not keep.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class]);
    Http::preventStrayRequests();
});

/** ISPConfig with one web node, aaPanel with one managed node (as onhost:nodes:discover registers them). */
function dedicatedPhpLab(bool $withIspNode = true): array
{
    Region::query()->firstOrCreate(['code' => 'cz1'], ['name' => 'Praha', 'country' => 'CZ', 'state' => 'active']);
    $isp = ProviderInstance::query()->firstOrCreate(['key' => 'ispconfig-shared01'], ['provider' => 'ispconfig', 'name' => 'ISPConfig shared01', 'region_code' => 'cz1', 'base_url' => 'https://shared01.mgmt.test:8080', 'secret_ref' => 'env://ISPCONFIG_SHARED01', 'state' => 'active', 'capabilities' => ['web.create' => true]]);
    $aap = ProviderInstance::query()->firstOrCreate(['key' => 'aapanel-managed01'], ['provider' => 'aapanel', 'name' => 'aaPanel managed01', 'region_code' => 'cz1', 'base_url' => 'https://managed01.mgmt.test:8888', 'secret_ref' => 'env://AAPANEL_MANAGED01', 'state' => 'active', 'capabilities' => ['web.create' => true]]);
    $nodes = [];
    $plan = [[$aap, 'aap-managed01', 'managed'], [$aap, 'aap-web01', 'web']];
    if ($withIspNode) {
        $plan[] = [$isp, 'isp-web01', 'web'];
    }
    foreach ($plan as [$instance, $name, $role]) {
        $nodes[$name] = Node::query()->firstOrCreate(['provider_instance_id' => $instance->id, 'name' => $name], ['region_code' => 'cz1', 'role' => $role, 'state' => 'active', 'capacity' => ['cpu_cores' => 32, 'ram_mb' => 131072, 'disk_gb' => 4000], 'usage' => ['cpu_pct' => 10, 'ram_used_mb' => 8192, 'disk_used_gb' => 100], 'last_seen_at' => now()]);
    }

    return [$isp, $aap, $nodes];
}

function dedicatedPhpVersion(string $product, string $plan)
{
    return Product::query()->where('key', $product)->firstOrFail()->plans()->where('key', $plan)->firstOrFail()->currentVersion();
}

function dedicatedPhpPaidService(Organization $org, string $plan, array $desired): Service
{
    return Service::query()->create(['organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Webhosting', 'state' => ServiceStateMachine::PAID, 'region_code' => 'cz1',
        'desired_spec' => $desired + ['family' => 'web', 'product_key' => 'web-hosting', 'plan_key' => $plan], 'entitlements' => (array) dedicatedPhpVersion('web-hosting', $plan)->entitlements, 'sla_class' => 'standard']);
}

function dedicatedPhpRunStep(Service $service, ScheduleNodeStep $step): StepResult
{
    $operation = Operation::query()->create(['service_id' => $service->id, 'organization_id' => $service->organization_id, 'kind' => 'provision.website', 'workflow' => ProvisionWebsiteWorkflow::class, 'state' => Operation::RUNNING, 'step' => 0, 'steps_total' => 1, 'actor_type' => 'system', 'idempotency_key' => 'dedicated-php:'.$service->id, 'correlation_id' => 'c', 'desired' => (array) $service->desired_spec, 'context' => [], 'queue' => 'q', 'queued_at' => now(), 'next_run_at' => now(), 'retry_until' => now()->addHour()]);

    return $step->run(new StepContext($operation, $service, app(ProviderRegistry::class), app(), CommandContext::system('test')));
}

it('refuses to place a dedicated-PHP plan on a panel that runs one PHP pool for the whole node', function () {
    [$isp, $aap] = dedicatedPhpLab();
    $this->actingAs($this->staff('platform_owner'), 'sanctum');

    $refused = $this->putJson('/v1/staff/placements', ['product_key' => 'web-hosting', 'plan_key' => 'profi', 'provider_instance_key' => 'aapanel-managed01'], ['Idempotency-Key' => 'dp-1'])
        ->assertUnprocessable()->assertJsonPath('error', 'placement_requires_dedicated_php')->json('message');
    expect($refused)->not->toMatch('/aapanel|ispconfig/i');
    // a plan without dedicated workers may still go there, and profi may go to ISPConfig
    $this->putJson('/v1/staff/placements', ['product_key' => 'web-hosting', 'plan_key' => 'standard', 'provider_instance_key' => 'aapanel-managed01'], ['Idempotency-Key' => 'dp-2'])->assertCreated();
    $this->putJson('/v1/staff/placements', ['product_key' => 'web-hosting', 'plan_key' => 'profi', 'provider_instance_key' => 'ispconfig-shared01'], ['Idempotency-Key' => 'dp-3'])->assertCreated();

    // the one-order staff pin is held to the same rule
    $product = Product::query()->where('key', 'web-hosting')->firstOrFail();
    $profi = (array) dedicatedPhpVersion('web-hosting', 'profi')->entitlements;
    expect(fn () => app(PlacementService::class)->pinned($product, 'aapanel-managed01', $profi))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('placement_requires_dedicated_php'));
    expect(app(PlacementService::class)->pinned($product, 'ispconfig-shared01', $profi)->providerInstance->key)->toBe('ispconfig-shared01');
});

it('ignores a product-wide aaPanel placement for a dedicated-PHP plan and marks the new spec', function () {
    dedicatedPhpLab();
    app(PlacementService::class)->upsert(['product_key' => 'web-hosting', 'provider_instance_key' => 'aapanel-managed01'], CommandContext::system('test'));
    $profi = (array) dedicatedPhpVersion('web-hosting', 'profi')->entitlements;
    $placements = app(PlacementService::class);
    expect($placements->resolve('web-hosting', 'profi', 'cz1', $profi))->toBeNull()
        ->and($placements->resolve('web-hosting', 'standard', 'cz1', (array) dedicatedPhpVersion('web-hosting', 'standard')->entitlements)->providerInstance->key)->toBe('aapanel-managed01');

    [$owner, $org] = $this->customerWithOrganization();
    $product = Product::query()->where('key', 'web-hosting')->firstOrFail();
    $services = app(ServiceService::class);
    $profiService = $services->create($org, $product, dedicatedPhpVersion('web-hosting', 'profi'), ['domain' => 'dedicated.cz'], $this->contextFor($owner, $org));
    expect($profiService->desired_spec['executor'])->toBe('ispconfig')->and($profiService->desired_spec['placement'])->toBeNull()
        ->and($profiService->desired_spec['requires'])->toBe(['php' => 'dedicated']);
    $standard = $services->create($org, $product, dedicatedPhpVersion('web-hosting', 'standard'), ['domain' => 'shared.cz'], $this->contextFor($owner, $org));
    expect($standard->desired_spec['executor'])->toBe('aapanel')->and($standard->desired_spec)->not->toHaveKey('requires');
});

it('leaves the e-shop plans on the panel their product runs on, and says the dedicated promise is not kept there', function () {
    dedicatedPhpLab();
    [$owner, $org] = $this->customerWithOrganization();
    $eshop = Product::query()->where('key', 'eshop')->firstOrFail();
    $peak = app(ServiceService::class)->create($org, $eshop, dedicatedPhpVersion('eshop', 'shop-peak'), ['domain' => 'peak-shop.cz'], $this->contextFor($owner, $org));

    // moving it to ISPConfig would break the WAF rate limit "pro" promises and the client-wide site count (see the header)
    expect($peak->desired_spec['executor'])->toBe('aapanel')->and($peak->desired_spec)->not->toHaveKey('requires');
    $peakEnt = (array) dedicatedPhpVersion('eshop', 'shop-peak')->entitlements;
    expect(PlacementRules::requires('aapanel', $peakEnt))->toBe([])
        ->and(PlacementRules::undelivered('aapanel', $peakEnt))->toBeTrue()
        ->and(PlacementRules::undelivered('ispconfig', (array) dedicatedPhpVersion('web-hosting', 'profi')->entitlements))->toBeFalse()
        ->and(PlacementRules::undelivered('aapanel', (array) dedicatedPhpVersion('eshop', 'shop-start')->entitlements))->toBeFalse();
});

it('never schedules a dedicated-PHP service on an aaPanel node', function () {
    [, , $nodes] = dedicatedPhpLab(withIspNode: false);
    [, $org] = $this->customerWithOrganization();

    // a spec that says aaPanel but requires a pool per site (a pin made before the rule, edited by hand): refused, no retry
    $odd = dedicatedPhpPaidService($org, 'profi', ['executor' => 'aapanel', 'requires' => ['php' => 'dedicated']]);
    $result = dedicatedPhpRunStep($odd, new ScheduleNodeStep('managed', 'aapanel'));
    expect($result->outcome)->toBe(StepResult::FAIL)->and($result->retryable)->toBeFalse()->and((string) $result->error)->toContain('placement_rule_violation')
        ->and($odd->fresh()->node_id)->toBeNull();

    // the ordinary new spec: ISPConfig is asked for and only aaPanel nodes exist — waits for capacity, never lands on aaPanel
    $new = dedicatedPhpPaidService($org, 'profi', ['executor' => 'ispconfig', 'requires' => ['php' => 'dedicated']]);
    $wait = dedicatedPhpRunStep($new, new ScheduleNodeStep('web', 'ispconfig'));
    expect($wait->outcome)->toBe(StepResult::FAIL)->and($wait->retryable)->toBeTrue()->and($new->fresh()->node_id)->toBeNull();
    expect(collect($nodes)->pluck('id')->all())->not->toContain($new->fresh()->node_id);
});

it('leaves a service created before the rule alone', function () {
    [, , $nodes] = dedicatedPhpLab();
    [, $org] = $this->customerWithOrganization();
    // paid before the deploy: its spec carries no `requires` marker and names aaPanel — it is placed exactly as before
    $old = dedicatedPhpPaidService($org, 'profi', ['executor' => 'aapanel']);

    $result = dedicatedPhpRunStep($old, new ScheduleNodeStep('managed', 'aapanel'));
    expect($result->outcome)->toBe(StepResult::DONE)->and($old->fresh()->node_id)->toBe($nodes['aap-managed01']->id);
});

it('refuses a plan change to a dedicated-PHP plan for a service that runs on the node-wide pool', function () {
    [$isp, $aap, $nodes] = dedicatedPhpLab();
    [, $org] = $this->customerWithOrganization();
    $onAaPanel = dedicatedPhpPaidService($org, 'standard', ['executor' => 'aapanel']);
    $onAaPanel->forceFill(['state' => ServiceStateMachine::ACTIVE, 'provider_instance_id' => $aap->id, 'node_id' => $nodes['aap-managed01']->id])->save();
    $profi = (array) dedicatedPhpVersion('web-hosting', 'profi')->entitlements;

    $shortfalls = app(PlanFit::class)->shortfalls($onAaPanel->fresh(), $profi);
    $php = collect($shortfalls)->firstWhere('key', 'php_workers_dedicated');
    expect($php)->not->toBeNull()->and($php['message'])->not->toMatch('/aapanel|ispconfig/i')->and($php['message'])->toContain('podpora');
    expect(fn () => app(PlanFit::class)->assertFits($onAaPanel->fresh(), $profi))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('plan_change_does_not_fit')->and($e->status)->toBe(409));

    // the same change on ISPConfig fits
    $onIsp = dedicatedPhpPaidService($org, 'standard', ['executor' => 'ispconfig']);
    $onIsp->forceFill(['state' => ServiceStateMachine::ACTIVE, 'provider_instance_id' => $isp->id, 'node_id' => $nodes['isp-web01']->id])->save();
    expect(collect(app(PlanFit::class)->shortfalls($onIsp->fresh(), $profi))->pluck('key')->all())->not->toContain('php_workers_dedicated');
});

it('refuses a dedicated-PHP plan in the cart when only aaPanel web nodes exist', function () {
    $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]);
    dedicatedPhpLab(withIspNode: false);
    [, $org] = $this->customerWithOrganization();
    $quote = fn (string $plan) => app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => $plan, 'config' => ['domain' => $plan.'-cart.cz']]], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c', 'vat_status' => 'unknown'], 1, null, $org);
    $before = [Quote::query()->count(), Order::query()->count()];

    expect(fn () => $quote('profi'))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('capacity_sold_out')->and($e->status)->toBe(409));
    expect([Quote::query()->count(), Order::query()->count()])->toBe($before);
    // a plan without dedicated workers is judged as before: no ISPConfig web node registered, nothing to judge by
    expect($quote('standard')->subtotal_minor)->toBeGreaterThan(0);

    // with an ISPConfig web node the dedicated plan is on sale again
    dedicatedPhpLab();
    expect($quote('profi')->subtotal_minor)->toBeGreaterThan(0);
});

it('keeps a service that already holds dedicated workers on its node-wide pool free to change its billing period', function () {
    [, $aap, $nodes] = dedicatedPhpLab();
    [, $org] = $this->customerWithOrganization();
    $old = dedicatedPhpPaidService($org, 'profi', ['executor' => 'aapanel']); // placed before the rule
    $old->forceFill(['state' => ServiceStateMachine::ACTIVE, 'provider_instance_id' => $aap->id, 'node_id' => $nodes['aap-managed01']->id])->save();

    expect(collect(app(PlanFit::class)->shortfalls($old->fresh(), (array) dedicatedPhpVersion('web-hosting', 'profi')->entitlements))->pluck('key')->all())->not->toContain('php_workers_dedicated');
});

it('reports dedicated PHP workers on a node-wide pool and the capacity basis in the doctor, never as a failure', function () {
    [, $aap, $nodes] = dedicatedPhpLab();
    [, $org] = $this->customerWithOrganization();
    $old = dedicatedPhpPaidService($org, 'profi', ['executor' => 'aapanel']);
    $old->forceFill(['state' => ServiceStateMachine::ACTIVE, 'provider_instance_id' => $aap->id, 'node_id' => $nodes['aap-managed01']->id])->save();
    PlanPlacement::query()->create(['product_key' => 'web-hosting', 'plan_key' => null, 'region_code' => null, 'provider_instance_id' => $aap->id, 'priority' => 100, 'state' => 'active']); // made before the rule

    Artisan::call('onhost:doctor', ['--json' => true]); // WARN rows never fail the command
    $checks = collect(json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'])->where('area', 'capacity')->keyBy('check');
    expect($checks->get('dedicated PHP workers are sold only where a site has its own pool'))->toMatchArray(['status' => 'WARN'])
        ->and($checks->get('dedicated PHP workers are sold only where a site has its own pool')['detail'])->toContain('eshop/shop-peak')
        ->and($checks->get('no service with dedicated PHP workers runs on a node-wide pool')['detail'])->toContain('1 service(s), not moved')
        ->and($checks->get('no placement sends a dedicated-PHP plan to a node-wide pool')['detail'])->toContain('web-hosting/profi → aapanel-managed01')
        ->and($checks->get('plans with dedicated PHP workers have a panel to run on')['status'])->toBe('OK')
        ->and($checks->get('capacity basis as decided (disk sold, RAM and CPU measured)'))->toMatchArray(['status' => 'WARN']);
    expect($old->fresh()->node_id)->toBe($nodes['aap-managed01']->id); // reported, not moved
});
