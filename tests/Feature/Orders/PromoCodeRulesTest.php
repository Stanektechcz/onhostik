<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Catalog\Models\PromoCode;
use Onhost\Domain\Orders\QuoteService;

/*
 * A promo code does what its form says. A code for a fixed amount is spent ONCE per order — it used to be applied to every
 * line, so "100 Kč off" took 100 Kč off each of ten lines. And the box "only the first period" means something when it is
 * unticked: the discount goes on into the renewals — it used to be stored and never read, so a customer promised a lasting
 * discount renewed at the full price.
 */

beforeEach(fn () => $this->seed([CatalogSeeder::class, TaxRuleSeeder::class]));

function promoRulesCode(string $code, string $kind, float $value, bool $firstOnly = true): PromoCode
{
    return PromoCode::query()->create(['code' => $code, 'kind' => $kind, 'value' => $value, 'currency' => $kind === 'amount' ? 'CZK' : null, 'applies_to' => null, 'first_period_only' => $firstOnly, 'state' => 'active', 'uses' => 0]);
}

it('spends a fixed-amount code once per order, however many lines the order has', function () {
    [, $org] = $this->customerWithOrganization();
    promoRulesCode('STOVKA', 'amount', 100);
    $customer = ['country' => 'CZ', 'customer_class' => 'b2c'];
    $profi = ['product_key' => 'web-hosting', 'plan_key' => 'profi'];
    $quotes = app(QuoteService::class);

    $one = $quotes->quote([$profi], 'CZK', $customer, 1, 'STOVKA', $org);
    $three = $quotes->quote([$profi, $profi + ['line_id' => 'b'], $profi + ['line_id' => 'c']], 'CZK', $customer, 1, 'STOVKA', $org);

    expect($one->discount_minor)->toBe(10000)->and($three->discount_minor)->toBe(10000); // it used to be 30 000: 100 Kč off every line
    // a code worth more than the first line goes on to the next one, and never below zero
    promoRulesCode('PETISTOVKA', 'amount', 500);
    $start = ['product_key' => 'web-hosting', 'plan_key' => 'start']; // 89 Kč a month
    $spread = $quotes->quote([$start, $start + ['line_id' => 'b']], 'CZK', $customer, 1, 'PETISTOVKA', $org);
    expect($spread->discount_minor)->toBe(17800)->and(collect($spread->lines)->sum(fn ($l) => (int) (is_array($l['net']) ? $l['net']['minor'] : (is_object($l['net']) ? $l['net']->minor : $l['net']))))->toBe(0);
});

it('keeps a percent code per line, and carries a discount into the renewals only when the code says so', function () {
    [, $org] = $this->customerWithOrganization();
    $customer = ['country' => 'CZ', 'customer_class' => 'b2c'];
    $profi = ['product_key' => 'web-hosting', 'plan_key' => 'profi'];
    $quotes = app(QuoteService::class);
    $minor = fn ($money) => (int) (is_array($money) ? $money['minor'] : (is_object($money) ? $money->minor : $money));

    promoRulesCode('PRVNI20', 'percent', 20, true);
    $first = $quotes->quote([$profi, $profi + ['line_id' => 'b']], 'CZK', $customer, 1, 'PRVNI20', $org);
    $list = $minor($first->lines[0]['unit_net']);
    expect($minor($first->lines[0]['discount']))->toBe((int) round($list * 0.2))->and($minor($first->lines[1]['discount']))->toBe((int) round($list * 0.2))
        ->and($minor($first->lines[0]['renewal_net']))->toBe($list); // only the first period: renewals at the list price

    promoRulesCode('NAPORAD20', 'percent', 20, false);
    $lasting = $quotes->quote([$profi], 'CZK', $customer, 1, 'NAPORAD20', $org);
    // it used to renew at the full list price although the code was set to last
    expect($minor($lasting->lines[0]['renewal_net']))->toBe($list - (int) round($list * 0.2))->and($lasting->lines[0]['config']['renewal_promo'] ?? null)->toBe('NAPORAD20');
});
