<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Orders\Models\Cart;

/*
 * The public cart shows the server's quote, not an estimate: a visitor without an account keeps one server cart through
 * X-Cart-Token, and a 12-month term is priced at the yearly list price (not twelve monthly ones) — the amount the
 * checkout summary shows must be the amount the proforma will carry.
 */

beforeEach(fn () => $this->seed([CatalogSeeder::class, TaxRuleSeeder::class]));

function guestCartLines(): array
{
    return [
        ['line_id' => 'l1', 'product_key' => 'web-hosting', 'plan_key' => 'standard', 'qty' => 1, 'config' => []],
        ['line_id' => 'l2', 'product_key' => 'web-hosting', 'plan_key' => 'start', 'qty' => 1, 'config' => []],
    ];
}

it('keeps a guest cart by token and quotes a 12-month term at the yearly list price', function () {
    $first = $this->putJson('/v1/cart', ['items' => [guestCartLines()[0]], 'commit_months' => 12, 'currency' => 'CZK'])->assertOk();
    $token = $first->json('data.token');
    expect($token)->toBeString()->and(strlen((string) $token))->toBeGreaterThanOrEqual(16);

    // the token names the same cart again; without it a new visitor would get a cart of their own
    $again = $this->withHeader('X-Cart-Token', $token)->putJson('/v1/cart', ['items' => guestCartLines(), 'commit_months' => 12, 'currency' => 'CZK'])->assertOk();
    expect($again->json('data.id'))->toBe($first->json('data.id'))->and($again->json('data.commit_months'))->toBe(12)->and(Cart::query()->count())->toBe(1);

    $yearly = $this->postJson('/v1/cart/quote')->assertOk()->json('data');
    expect($yearly['versions']['commit_months'])->toBe(12)
        ->and(array_column($yearly['lines'], 'period'))->toBe(['year', 'year'])
        ->and(array_column($yearly['lines'], 'unit_net'))->toBe([189000, 89000])
        ->and(array_column(array_column($yearly['lines'], 'config'), 'line_id'))->toBe(['l1', 'l2'])
        ->and($yearly['subtotal'])->toBe(278000)->and($yearly['tax'])->toBe(58380)->and($yearly['total'])->toBe(336380);

    // the same lines for a month: the monthly list prices
    $this->putJson('/v1/cart', ['items' => guestCartLines(), 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $monthly = $this->postJson('/v1/cart/quote')->assertOk()->json('data');
    expect(array_column($monthly['lines'], 'period'))->toBe(['month', 'month'])
        ->and(array_column($monthly['lines'], 'unit_net'))->toBe([18900, 8900])
        ->and($monthly['total'])->toBe(33638);
    $this->flushHeaders();

    // a malformed token is ignored, not an error
    $this->withHeader('X-Cart-Token', 'nope')->putJson('/v1/cart', ['items' => [guestCartLines()[0]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $this->flushHeaders();
    expect(Cart::query()->count())->toBe(2);
});
