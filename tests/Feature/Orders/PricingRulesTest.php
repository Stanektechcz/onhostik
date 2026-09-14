<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\PromoCode;
use Onhost\Domain\Catalog\PricingRules;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/*
 * Commercial rules of the cart: no discount is implied by anything the customer picks — a longer commitment, a domain
 * or an add-on costs the list price unless staff approved a discount in the settings. Domains sell in whole years
 * (at least one), add-ons belong to the line they extend and are priced from the catalogue's option prices.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
});

function pricingQuote($org, array $items, int $commit = 1, ?string $promo = null)
{
    return app(QuoteService::class)->quote($items, 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c', 'vat_status' => 'none'], $commit, $promo, $org);
}

it('never discounts a longer commitment on its own; staff-approved percentages apply per family', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $rules = app(PricingRules::class);
    expect($rules->commitTable('web'))->toBe([[1, 0.0], [12, 0.0], [24, 0.0]]);

    $twelve = pricingQuote($org, [['product_key' => 'web-hosting', 'plan_key' => 'start']], 12);
    expect($twelve->subtotal_minor)->toBe(89000)->and($twelve->discount_minor)->toBe(0)->and($twelve->lines[0]['period'])->toBe('year');
    $twentyFour = pricingQuote($org, [['product_key' => 'web-hosting', 'plan_key' => 'start']], 24);
    expect($twentyFour->subtotal_minor)->toBe(2 * 89000)->and($twentyFour->discount_minor)->toBe(0);

    $rules->setCommitDiscounts(['default' => [12 => 3], 'families' => ['web' => [12 => 5, 24 => 10]]]);
    expect($rules->commitTable('web'))->toBe([[1, 0.0], [12, 5.0], [24, 10.0]])->and($rules->commitTable('cloud'))->toBe([[1, 0.0], [12, 3.0], [24, 0.0]]);
    $web = pricingQuote($org, [['product_key' => 'web-hosting', 'plan_key' => 'start']], 12);
    expect($web->discount_minor)->toBe(Money::minor(89000, 'CZK')->percent(5)->minor);
    $vps = pricingQuote($org, [['product_key' => 'vps', 'plan_key' => 'compute-2']], 12);
    expect($vps->discount_minor)->toBe(Money::minor(249000, 'CZK')->percent(3)->minor);

    expect(fn () => $rules->setCommitDiscounts(['default' => [6 => 5]]))->toThrow(DomainError::class, '12 and 24');
    expect(fn () => $rules->setCommitDiscounts(['default' => [12 => 95]]))->toThrow(DomainError::class, 'between 0 and 90');
});

it('sells domains in whole years at list price: no commitment discount, a TLD discount only when staff set one', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $rules = app(PricingRules::class);

    $default = pricingQuote($org, [['product_key' => 'domain', 'config' => ['fqdn' => 'skladomat.cz']]]);
    expect($default->lines[0]['config']['period_years'])->toBe(1)->and($default->lines[0]['net'])->toBe(17900)->and($default->discount_minor)->toBe(0);
    $long = pricingQuote($org, [['product_key' => 'domain', 'config' => ['fqdn' => 'skladomat.cz', 'period_years' => 3]], ['product_key' => 'web-hosting', 'plan_key' => 'start']], 24);
    expect($long->lines[0]['net'])->toBe(3 * 17900)->and($long->lines[0]['discount'])->toBe(0)->and($long->discount_minor)->toBe(0);
    expect(fn () => pricingQuote($org, [['product_key' => 'domain', 'config' => ['fqdn' => 'skladomat.cz', 'period_years' => 0]]]))->toThrow(DomainError::class, 'at least');
    expect(fn () => pricingQuote($org, [['product_key' => 'domain', 'config' => ['fqdn' => 'skladomat.io', 'period_years' => 4]]]))->toThrow(DomainError::class, 'allowed: 1, 2, 3, 5');

    // the seeded promo names web/cloud families only: domains keep the list price
    $promo = pricingQuote($org, [['product_key' => 'domain', 'config' => ['fqdn' => 'skladomat.cz']]], 1, 'ONHOST10');
    expect($promo->discount_minor)->toBe(0);

    $rules->setDomainDiscount('cz', ['register' => 20, 'label' => 'Akce .cz', 'valid_to' => now()->addMonth()->toIso8601String()]);
    expect($rules->domainDiscount('cz', 'register'))->toBe(['percent' => 20.0, 'label' => 'Akce .cz'])->and($rules->domainDiscount('cz', 'renew')['percent'])->toBe(0.0)->and($rules->domainDiscount('com', 'register')['percent'])->toBe(0.0);
    $discounted = pricingQuote($org, [['product_key' => 'domain', 'config' => ['fqdn' => 'skladomat.cz', 'period_years' => 2]]]);
    expect($discounted->lines[0]['discount'])->toBe(Money::minor(2 * 17900, 'CZK')->percent(20)->minor)->and($discounted->lines[0]['net'])->toBe(2 * 17900 - Money::minor(2 * 17900, 'CZK')->percent(20)->minor)
        ->and($discounted->lines[0]['config']['discount_label'])->toBe('Akce .cz')->and($discounted->renewal_total_minor)->toBe(2 * 17900);
    $transfer = pricingQuote($org, [['product_key' => 'domain', 'config' => ['fqdn' => 'skladomat.cz', 'action' => 'transfer']]]);
    expect($transfer->discount_minor)->toBe(0); // the discount was set for registrations only

    $rules->setDomainDiscount('cz', ['register' => 20, 'valid_to' => now()->subDay()->toIso8601String()]);
    expect(pricingQuote($org, [['product_key' => 'domain', 'config' => ['fqdn' => 'skladomat.cz']]])->discount_minor)->toBe(0); // expired window
    $rules->deleteDomainDiscount('cz');
    expect($rules->domainDiscounts())->toBe([]);

    PromoCode::query()->create(['code' => 'DOMENY5', 'kind' => 'percent', 'value' => 5, 'applies_to' => ['domain'], 'first_period_only' => true, 'state' => 'active']);
    expect(pricingQuote($org, [['product_key' => 'domain', 'config' => ['fqdn' => 'skladomat.cz']]], 1, 'DOMENY5')->discount_minor)->toBe(Money::minor(17900, 'CZK')->percent(5)->minor);
});

it('prices per-item add-ons from the catalogue options and ties add-on products to the line they extend', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $quote = pricingQuote($org, [
        ['line_id' => 'web', 'product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['options' => ['nvme_gb' => 20, 'staging' => true, 'mailboxes' => 0, 'unknown' => 5]]],
        ['line_id' => 'cdn', 'product_key' => 'cdn', 'plan_key' => 'cdn-start', 'config' => ['parent_line_id' => 'web']],
    ]);
    expect($quote->lines[0]['net'])->toBe(8900 + 20 * 300 + 4900)
        ->and(array_column($quote->lines[0]['config']['options_priced'], 'key'))->toBe(['nvme_gb', 'staging'])
        ->and($quote->lines[1]['config']['parent_line_id'])->toBe('web')->and($quote->lines[1]['net'])->toBe(19000);

    expect(fn () => pricingQuote($org, [['line_id' => 'vps', 'product_key' => 'vps', 'plan_key' => 'compute-2'], ['product_key' => 'ssl', 'plan_key' => 'ov-business', 'config' => ['parent_line_id' => 'vps']]]))
        ->toThrow(DomainError::class, 'not offered as an add-on');
    expect(fn () => pricingQuote($org, [['product_key' => 'cdn', 'plan_key' => 'cdn-start', 'config' => ['parent_line_id' => 'nope']]]))
        ->toThrow(DomainError::class, 'does not exist');

    // staff can change what a product may carry
    $rules = app(PricingRules::class);
    expect($rules->addonProducts('web-hosting'))->toBe(['ssl', 'cdn', 'backup-plus']);
    $rules->setAddonProducts('web-hosting', ['ssl']);
    expect($rules->addonProducts('web-hosting'))->toBe(['ssl']);
    expect(fn () => pricingQuote($org, [['line_id' => 'web', 'product_key' => 'web-hosting', 'plan_key' => 'start'], ['product_key' => 'cdn', 'plan_key' => 'cdn-start', 'config' => ['parent_line_id' => 'web']]]))
        ->toThrow(DomainError::class, 'not offered as an add-on');
    expect(fn () => $rules->setAddonProducts('web-hosting', ['nope']))->toThrow(DomainError::class, 'does not exist');
});

it('prices the web hosting configurator from the option unit prices and turns the choices into service entitlements', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $options = ['sites' => 3, 'nvme_gb' => 20, 'mailboxes' => 3, 'databases' => 2, 'backup_days' => 'backup-30', 'ssh' => true, 'staging' => false];
    $quote = pricingQuote($org, [['product_key' => 'web-custom', 'plan_key' => 'custom', 'config' => ['options' => $options]]]);
    expect($quote->lines[0]['net'])->toBe(4900 + 2 * 2900 + 15 * 250 + 1 * 1500 + 3900 + 2900)
        ->and(array_column($quote->lines[0]['config']['options_priced'], 'key'))->toBe(['sites', 'nvme_gb', 'databases', 'backup_days', 'ssh']);

    $product = Product::query()->with('options')->where('key', 'web-custom')->firstOrFail();
    $base = $product->plans->first()->versions->first()->entitlements;
    $entitlements = (new ReflectionMethod(ServiceService::class, 'applyOptions'))->invoke(app(ServiceService::class), $base, $options, $product);
    expect($entitlements)->toMatchArray(['sites' => 3, 'nvme_gb' => 20, 'mailboxes' => 3, 'databases' => 2, 'backup_days' => 30, 'ssh' => true, 'staging' => false, 'php_workers' => 2]);

    // fixed plans: the extras add to the plan
    $web = Product::query()->with('options')->where('key', 'web-hosting')->firstOrFail();
    $start = $web->plans->firstWhere('key', 'start')->versions->first()->entitlements;
    $extended = (new ReflectionMethod(ServiceService::class, 'applyOptions'))->invoke(app(ServiceService::class), $start, ['nvme_gb' => 20, 'waf_cdn' => true, 'priority_support' => true, 'dedicated_ipv4' => true], $web);
    expect($extended)->toMatchArray(['nvme_gb' => 30, 'waf' => 'pro + CDN', 'support' => 'priority 10 min', 'ipv4' => 1, 'sites' => 1]);
});
