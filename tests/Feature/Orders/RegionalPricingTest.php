<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Catalog\PricingRules;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Platform\Money\Money;

/*
 * Regional pricing and the customer's currency (audit §5j-8): country groups carry a suggested currency and a percentage
 * on the catalogue price; staff edit the table; the customer picks the account currency (CZK or EUR).
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
});

it('applies the region percentage to the list price, lets staff edit the table and customers pick their currency', function () {
    $rules = app(PricingRules::class);
    expect($rules->regionFor('SK'))->toMatchArray(['key' => 'sk', 'currency' => 'EUR', 'adjust_pct' => 0.0])->and($rules->regionFor('DE'))->toMatchArray(['key' => 'eu', 'currency' => 'EUR', 'adjust_pct' => 5.0])->and($rules->regionFor('CZ')['key'])->toBe('home')->and($rules->regionFor(null)['key'])->toBe('home');
    expect($this->getJson('/v1/catalog/regions?country=de')->assertOk()->json('data.region.key'))->toBe('eu');
    $this->getJson('/v1/catalog/regions?country=deu')->assertStatus(422);

    [$owner, $org] = $this->customerWithOrganization([], ['country' => 'DE']);
    // the price group follows the country of the ORGANIZATION: what the request says about the country does not move it
    $quote = function (string $country) use ($org) {
        $org->forceFill(['country' => $country])->save();

        return app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2b', 'vat_status' => 'valid'], 1, null, $org->fresh());
    };
    $home = $quote('CZ');
    $eu = $quote('DE');
    expect($eu->subtotal_minor)->toBe($home->subtotal_minor + Money::minor($home->subtotal_minor, 'CZK')->percent('5')->minor)->and($eu->lines[0]['config']['price_region'])->toBe('eu')->and($eu->versions['price_region'])->toBe('eu')->and($home->lines[0]['config']['price_region'])->toBe('home');
    expect($eu->lines[0]['config'])->not->toHaveKey('region'); // `region` on a line is the placement region the provisioning reads — never the price group
    expect($eu->renewal_total_minor)->toBe($home->renewal_total_minor + Money::minor($home->renewal_total_minor, 'CZK')->percent('5')->minor); // the renewal follows the region too

    // staff replace the table: Slovakia at −10 %, everything else at the list price
    $this->actingAs($this->staff('platform_owner'), 'sanctum');
    $this->putJson('/v1/staff/pricing/regions', ['regions' => [['key' => 'sk', 'countries' => ['SK'], 'currency' => 'EUR', 'adjust_pct' => -10]]])->assertOk()->assertJsonPath('regions.sk.adjust_pct', -10);
    $this->putJson('/v1/staff/pricing/regions', ['regions' => [['key' => 'x', 'countries' => ['Slovensko']]]])->assertStatus(422);
    expect($rules->regionFor('DE')['key'])->toBe('home');
    $sk = $quote('SK');
    expect($sk->subtotal_minor)->toBe($home->subtotal_minor - Money::minor($home->subtotal_minor, 'CZK')->percent('10')->minor);
    $pricing = $this->getJson('/v1/staff/pricing')->assertOk()->json();
    expect(($pricing['data'] ?? $pricing)['regions'][0]['key'])->toBe('sk');
    $this->putJson('/v1/staff/pricing/regions', ['regions' => []])->assertOk()->assertJsonPath('regions.eu.adjust_pct', 5); // back to the defaults

    // the customer picks the account currency
    $this->actingAs($owner, 'sanctum');
    $h = ['X-Organization' => $org->id];
    $this->withHeaders($h + ['Idempotency-Key' => 'cur-1'])->patchJson("/v1/organizations/{$org->id}", ['currency' => 'eur'])->assertOk()->assertJsonPath('organization.currency', 'EUR');
    $this->withHeaders($h + ['Idempotency-Key' => 'cur-2'])->patchJson("/v1/organizations/{$org->id}", ['currency' => 'USD'])->assertStatus(422);
    expect($org->refresh()->currency)->toBe('EUR');
});
