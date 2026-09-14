<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Catalog\Models\ProductOption;
use Onhost\Domain\Catalog\Models\PromoCode;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Platform\Money\Money;

/* Staff set every discount, promo code, add-on mapping and option price in the settings; the public data script follows. */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
});

it('lets staff approve commitment and domain discounts, manage promo codes, add-on mappings and option prices', function () {
    $this->actingAs($this->staff('platform_owner'), 'sanctum');
    $index = $this->getJson('/v1/staff/pricing')->assertOk()->json('data');
    expect($index['commit_discounts'])->toBe(['default' => [], 'families' => []])->and($index['domain_discounts'])->toBe([])->and($index['commit_months'])->toBe([1, 12, 24])
        ->and(collect($index['promo_codes'])->pluck('code')->all())->toContain('ONHOST10')->and(collect($index['products'])->firstWhere('key', 'web-hosting')['addon_products'])->toBe(['ssl', 'cdn', 'backup-plus'])
        ->and(collect(collect($index['products'])->firstWhere('key', 'web-custom')['options'])->pluck('key')->all())->toContain('sites', 'nvme_gb', 'backup_days')->and(collect($index['addon_candidates'])->pluck('key')->all())->toContain('ssl', 'cdn');

    $this->putJson('/v1/staff/pricing/commit-discounts', ['default' => [12 => 0], 'families' => ['web' => [12 => 5, 24 => 10]]])->assertOk()->assertJsonPath('commit_discounts.families.web.24', 10);
    $this->putJson('/v1/staff/pricing/commit-discounts', ['families' => ['web' => [24 => 120]]])->assertUnprocessable();
    $this->putJson('/v1/staff/pricing/domain-discounts', ['tld' => '.cz', 'register' => 15, 'label' => 'Podzimní akce'])->assertOk()->assertJsonPath('domain_discount.register', 15)->assertJsonPath('tld', 'cz');
    $this->putJson('/v1/staff/pricing/promo-codes', ['code' => 'jaro-2026', 'kind' => 'percent', 'value' => 15, 'applies_to' => ['web', 'domain'], 'max_uses' => 100])->assertOk()->assertJsonPath('promo.code', 'JARO-2026');
    $this->putJson('/v1/staff/pricing/addon-products', ['product_key' => 'wordpress', 'addon_products' => ['ssl']])->assertOk()->assertJsonPath('addon_products', ['ssl']);
    $this->putJson('/v1/staff/pricing/addon-products', ['product_key' => 'wordpress', 'addon_products' => ['nope']])->assertUnprocessable()->assertJsonPath('error', 'addon_product_unknown');
    $this->putJson('/v1/staff/pricing/options', ['product_key' => 'web-hosting', 'key' => 'malware_scan', 'kind' => 'addon', 'label' => ['cs' => 'Sken malwaru', 'en' => 'Malware scan'], 'desc' => ['cs' => 'denní kontrola souborů'], 'price_czk' => 39])->assertOk()->assertJsonPath('option.key', 'malware_scan');
    $this->putJson('/v1/staff/pricing/options', ['product_key' => 'web-custom', 'key' => 'sites', 'kind' => 'slider', 'label' => ['cs' => 'Weby'], 'unit' => 'ks', 'min' => 1, 'max' => 100, 'step' => 1, 'default' => 1, 'price_czk' => 35, 'entitlement' => ['key' => 'sites', 'mode' => 'absolute']])->assertOk();
    $this->putJson('/v1/staff/pricing/options', ['product_key' => 'web-hosting', 'key' => 'bad key', 'kind' => 'addon', 'label' => ['cs' => 'x'], 'price_czk' => 1])->assertUnprocessable()->assertJsonPath('error', 'option_key_invalid');

    // the rules reach the quote …
    [$owner, $org] = $this->customerWithOrganization();
    $quote = app(QuoteService::class)->quote([
        ['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['options' => ['malware_scan' => true]]],
        ['product_key' => 'domain', 'config' => ['fqdn' => 'akce.cz']],
        ['product_key' => 'web-custom', 'plan_key' => 'custom', 'config' => ['options' => ['sites' => 3]]],
    ], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c'], 24, 'JARO-2026', $org);
    $webGross = (89000 + 3900 * 12) * 2; // yearly periods carry twelve monthly option prices
    $commit = Money::minor($webGross, 'CZK')->percent(10)->minor;
    expect($quote->lines[0]['discount'])->toBe($commit + Money::minor($webGross - $commit, 'CZK')->percent(15)->minor)
        ->and($quote->lines[1]['discount'])->toBe(Money::minor(17900, 'CZK')->percent(15)->minor + Money::minor(17900 - Money::minor(17900, 'CZK')->percent(15)->minor, 'CZK')->percent(15)->minor)
        ->and($quote->lines[2]['unit_net'])->toBe((49000 + 2 * 3500 * 12) * 2);

    // … and the public web (pricing, add-ons, configurator prices come from the same catalogue)
    $js = $this->get('/surfaces/onhost-data.js')->assertOk()->getContent();
    preg_match('/var D = (\{.*\});\n  function L/s', $js, $m);
    $data = json_decode($m[1] ?? '{}', true)['cs'];
    expect($data['pricing']['commit']['families']['web'])->toBe(['12' => 5, '24' => 10])->and($data['pricing']['domain_discounts']['cz']['register'])->toBe(15)->and($data['pricing']['product_families']['cdn'])->toBe('addon')
        ->and(collect($data['addons']['web-hosting']['options'])->firstWhere('key', 'malware_scan'))->toMatchArray(['kind' => 'addon', 'label' => 'Sken malwaru', 'price' => 39, 'desc' => 'denní kontrola souborů'])
        ->and(collect($data['addons']['web-hosting']['products'])->pluck('key')->all())->toBe(['ssl', 'cdn', 'backup-plus'])->and(collect($data['addons']['wordpress']['products'])->pluck('key')->all())->toBe(['ssl'])
        ->and(collect($data['pages']['web-hosting']['builder']['options'])->firstWhere('key', 'sites'))->toMatchArray(['price' => 35, 'min' => 1, 'max' => 100])
        ->and($data['pages']['web-hosting']['builder']['base_price'])->toBe(49)->and($data['pages']['web-hosting']['details']['cols'])->toBe(['Start', 'Standard', 'Profi'])->and(count($data['pages']['web-hosting']['details']['rows']))->toBeGreaterThan(10)
        ->and($data['tlds'][0])->toMatchArray(['tld' => 'cz', 'register' => 179, 'discount' => 15, 'default_period' => 1]);
    expect($this->getJson('/v1/catalog/promo?code=jaro-2026')->assertOk()->json('data'))->toMatchArray(['code' => 'JARO-2026', 'kind' => 'percent', 'value' => 15]);
    $this->getJson('/v1/catalog/promo?code=nope')->assertUnprocessable()->assertJsonPath('error', 'promo_invalid');

    $this->deleteJson('/v1/staff/pricing/domain-discounts/cz')->assertOk()->assertJsonPath('deleted', true);
    $this->deleteJson('/v1/staff/pricing/promo-codes/JARO-2026')->assertOk()->assertJsonPath('deleted', true);
    $this->deleteJson('/v1/staff/pricing/options/web-hosting/malware_scan')->assertOk()->assertJsonPath('deleted', true);
    expect(PromoCode::query()->where('code', 'JARO-2026')->exists())->toBeFalse()->and(ProductOption::query()->where('key', 'malware_scan')->exists())->toBeFalse();
});

it('keeps pricing behind the catalogue permission', function () {
    [$user] = $this->customerWithOrganization();
    $this->actingAs($user, 'sanctum');
    $this->getJson('/v1/staff/pricing')->assertForbidden();
    $this->putJson('/v1/staff/pricing/commit-discounts', ['families' => ['web' => [24 => 10]]])->assertForbidden();
});
