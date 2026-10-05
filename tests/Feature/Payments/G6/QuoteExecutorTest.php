<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Orders\Models\Quote;

/*
 * G6: the quote a customer (or a visitor) gets for the cart carried `config.executor` in every line — the name of the vendor
 * panel the product runs on. Customers never learn which panels run behind their services (the order lines strip it already,
 * Presenters::orderItem); the quote kept by the server still has it, for the order it becomes.
 */

beforeEach(fn () => $this->seed([CatalogSeeder::class, TaxRuleSeeder::class]));

it('never names the executor in the quote answer, and keeps it in the stored quote for the order', function () {
    $token = $this->putJson('/v1/cart', ['items' => [['line_id' => 'l1', 'product_key' => 'web-hosting', 'plan_key' => 'standard', 'qty' => 1, 'config' => []]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk()->json('data.token');

    $quote = $this->withHeader('X-Cart-Token', (string) $token)->postJson('/v1/cart/quote')->assertOk()->json('data');

    expect($quote['lines'])->not->toBe([])
        ->and(collect($quote['lines'])->every(fn (array $line) => ! array_key_exists('executor', (array) ($line['config'] ?? []))))->toBeTrue()
        ->and(json_encode($quote))->not->toContain('"executor"')
        ->and(array_column(array_column($quote['lines'], 'config'), 'line_id'))->toBe(['l1']);
    $stored = Quote::query()->findOrFail($quote['quote_id']);
    expect(collect((array) $stored->lines)->every(fn (array $line) => array_key_exists('executor', (array) ($line['config'] ?? []))))->toBeTrue();
});
