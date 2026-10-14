<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\SellSwitch;
use Onhost\Domain\Services\PlanChangeService;
use Onhost\Platform\Errors\DomainError;

/*
 * TASK-0229 (plan W3): the launch sell-switch matrix is fail-closed. Default off = nothing is listed or orderable; a family key
 * overrides the default either way. Only NEW sales read it: a plan change of an existing service still works.
 */

beforeEach(fn () => $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]));

it('lists nothing and refuses to resolve while the default is off', function () {
    config(['onhost-sell.default' => false, 'onhost-sell.families' => []]);
    $catalog = app(CatalogService::class);

    expect($catalog->products())->toHaveCount(0)
        ->and($catalog->products(sellableOnly: false)->count())->toBeGreaterThan(0)
        ->and(Product::query()->where('key', 'web-hosting')->firstOrFail()->isSellable())->toBeFalse();

    try {
        $catalog->resolve('web-hosting', 'standard', 'CZK');
        $this->fail('a switched-off family must not resolve');
    } catch (DomainError $e) {
        expect($e->error)->toBe('product_not_sellable');
    }
});

it('turns one family on with its own key while the default stays off', function () {
    config(['onhost-sell.default' => false, 'onhost-sell.families' => ['web' => true]]);
    $catalog = app(CatalogService::class);

    expect($catalog->products()->pluck('family')->unique()->all())->toBe(['web'])
        ->and($catalog->resolve('web-hosting', 'standard', 'CZK')['product']->key)->toBe('web-hosting')
        ->and(fn () => $catalog->resolve('vps', 'compute-4', 'CZK'))->toThrow(DomainError::class);
});

it('lets a family key switch one family off while the default is on', function () {
    config(['onhost-sell.default' => true, 'onhost-sell.families' => ['web' => false]]);
    $catalog = app(CatalogService::class);

    expect($catalog->products()->pluck('family')->contains('web'))->toBeFalse()
        ->and($catalog->products()->pluck('family')->contains('cloud'))->toBeTrue()
        ->and(SellSwitch::allows('web'))->toBeFalse()
        ->and(SellSwitch::allows('cloud'))->toBeTrue()
        ->and(SellSwitch::allows('unknown-family'))->toBeTrue();
});

it('still sells everything with the default on', function () {
    config(['onhost-sell.default' => true, 'onhost-sell.families' => []]);

    expect(app(CatalogService::class)->resolve('web-hosting', 'standard', 'CZK')['plan']->key)->toBe('standard')
        ->and(app(CatalogService::class)->products()->count())->toBeGreaterThan(0);
});

it('does not block the plan change of an existing service when its family is off', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'billing_email' => 'billing@example.cz']);
    $service = featureWebService($org, 'aapanel');
    $catalog = app(CatalogService::class);
    $start = $catalog->resolve('web-hosting', 'start', 'CZK', 'month');
    $service->forceFill(['plan_version_id' => $start['version']->id, 'entitlements' => $start['version']->entitlements])->save();
    $subscription = Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $start['version']->id, 'price_id' => $start['price']->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => $start['price']->renewalAmount()->minor,
        'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(15), 'current_period_end' => now()->addDays(15), 'next_renewal_at' => now()->addDays(8), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);
    $service->forceFill(['subscription_id' => $subscription->id])->save();

    config(['onhost-sell.default' => false, 'onhost-sell.families' => []]);

    $options = app(PlanChangeService::class)->options($service->fresh(), 'CZK');
    expect(collect($options['plans'])->pluck('plan_key')->all())->toContain('standard');
});
