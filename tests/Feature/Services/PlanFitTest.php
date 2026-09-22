<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\PlanFit;

/*
 * A plan change has to fit what the service already holds. Nothing checked it: a web hosting with three sites could be
 * moved to a plan that sells one, and the two the plan no longer paid for went on running — and the plan's space could
 * drop below what the sites already had allocated, which ISPConfig then refuses, after the order was paid.
 */

beforeEach(fn () => $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]));

/** Puts the service on a plan with a live subscription, the way a running customer looks. */
function onPlan(Service $service, string $planKey): Service
{
    $resolved = app(CatalogService::class)->resolve('web-hosting', $planKey, 'CZK', 'month');
    $service->forceFill(['plan_version_id' => $resolved['version']->id, 'entitlements' => $resolved['version']->entitlements])->save();
    $subscription = Subscription::query()->create([
        'organization_id' => $service->organization_id, 'service_id' => $service->id, 'plan_version_id' => $resolved['version']->id, 'price_id' => $resolved['price']->id,
        'currency' => 'CZK', 'period' => 'month', 'amount_minor' => $resolved['price']->renewalAmount()->minor, 'state' => Subscription::ACTIVE,
        'current_period_start' => now()->subDays(15), 'current_period_end' => now()->addDays(15), 'next_renewal_at' => now()->addDays(8), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);
    $service->forceFill(['subscription_id' => $subscription->id])->save();

    return $service->refresh();
}

/** A further site of the plan, as `ServiceSites::create` leaves it. */
function planSiteOf(Service $owner, string $domain, int $gb, int $remoteId = 42): Service
{
    $site = Service::query()->create([
        'organization_id' => $owner->organization_id, 'product_key' => $owner->product_key, 'plan_version_id' => $owner->plan_version_id, 'family' => $owner->family,
        'name' => $domain, 'state' => ServiceStateMachine::ACTIVE, 'region_code' => $owner->region_code, 'provider_instance_id' => $owner->provider_instance_id, 'node_id' => $owner->node_id,
        'entitlements' => array_replace((array) $owner->entitlements, ['sites' => 1, 'nvme_gb' => $gb]), 'sla_class' => $owner->sla_class, 'activated_at' => now(),
        'tags' => ['parent_service_id' => $owner->id, 'billing' => 'included', 'sites' => ['quota_gb' => $gb]],
        'desired_spec' => ['executor' => 'aapanel', 'family' => 'web', 'domain' => $domain, 'php_version' => '8.3'], 'hostname' => $domain,
    ]);
    ProviderBinding::query()->create([
        'service_id' => $site->id, 'provider_instance_id' => $owner->provider_instance_id, 'remote_type' => 'site', 'remote_id' => (string) $remoteId, 'remote_node' => 'aapanel-managed01',
        'meta' => ['name' => $domain, 'path' => '/www/wwwroot/'.$domain], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => 'plan-site:'.$site->id, 'adapter_version' => '1.0.0',
    ]);

    return $site->refresh();
}

it('refuses a plan the service does not fit into, before any money moves, and says what has to go first', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [$owner, $org] = $this->customerWithOrganization([], ['street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'billing_email' => 'billing@example.cz']);
    $service = onPlan(featureWebService($org, 'aapanel'), 'standard'); // 10 sites, 50 GB
    planSiteOf($service, 'druhy-web.cz', 20);
    $this->actingAs($owner, 'sanctum');

    // the offer marks the plans the service cannot move to, with the reason
    $plans = collect($this->getJson("/v1/services/{$service->id}/plans")->assertOk()->json('data.plans'));
    expect($plans->firstWhere('plan_key', 'start')['fits'])->toBeFalse()
        ->and($plans->firstWhere('plan_key', 'start')['blockers'][0]['key'])->toBe('sites')
        ->and($plans->firstWhere('plan_key', 'profi')['fits'])->toBeTrue();

    // and the cart refuses it: „Tarif nabízí 1 web a služba má 2. Nejdřív odeberte: druhy-web.cz.“
    $this->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start', 'qty' => 1, 'period' => 'month', 'config' => ['upgrade_of' => $service->id]]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $this->postJson('/v1/cart/quote')->assertStatus(409)->assertJsonPath('error', 'plan_change_does_not_fit');

    // the same service fits the bigger plan, and the quote goes through
    $this->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'profi', 'qty' => 1, 'period' => 'month', 'config' => ['upgrade_of' => $service->id]]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $this->postJson('/v1/cart/quote')->assertOk();
});

it('names every reason at once: the sites, the space, the test copy and the PHP version', function () {
    [, $org] = $this->customerWithOrganization();
    $service = onPlan(featureWebService($org, 'aapanel'), 'standard');
    $service->forceFill(['tags' => array_merge((array) $service->tags, ['sites' => ['quota_gb' => 30]]), 'desired_spec' => array_merge((array) $service->desired_spec, ['php_version' => '8.1'])])->save();
    planSiteOf($service->refresh(), 'druhy-web.cz', 20);

    $start = app(CatalogService::class)->resolve('web-hosting', 'start', 'CZK', 'month')['version']->entitlements;
    $keys = array_column(app(PlanFit::class)->shortfalls($service->refresh(), (array) $start), 'key');

    expect($keys)->toContain('sites')      // two sites, the plan sells one
        ->toContain('nvme_gb')             // the further site alone holds 20 GB and the plan sells 10
        ->toContain('php_versions');       // the site runs PHP 8.1, Start offers 8.2+
});

it('leaves a service with one site free to move anywhere', function () {
    [, $org] = $this->customerWithOrganization();
    $service = onPlan(featureWebService($org, 'aapanel'), 'standard');
    $start = app(CatalogService::class)->resolve('web-hosting', 'start', 'CZK', 'month')['version']->entitlements;

    expect(app(PlanFit::class)->shortfalls($service, (array) $start))->toBe([]);
});
