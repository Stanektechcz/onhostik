<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Platform\Audit\AuditEvent;

beforeEach(fn () => $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]));

it('returns the same unpaid order when the same cart is placed again within the duplicate window, even with a new quote and key', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($owner, $org);
    $consents = ['terms' => ['version' => '4.0'], 'privacy' => ['version' => '4.0'], 'withdrawal_waiver' => ['version' => '4.0'], 'dpa' => ['version' => '4.0'], 'sla' => ['version' => '4.0']];
    $items = [['product_key' => 'web-hosting', 'plan_key' => 'profi'], ['product_key' => 'backup-hourly', 'plan_key' => 'hourly-30']];
    $quotes = app(QuoteService::class);

    $first = app(CheckoutService::class)->placeOrder($quotes->quote($items, 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c'], 12, null, $org), $org, $owner, $consents, ['mode' => 'bank'], 'cart-a:q1', $ctx);
    expect($first['order']->state)->toBe('PENDING_PAYMENT')->and($first['order']->meta['fingerprint'])->toHaveLength(64);

    // the browser retried with a fresh quote (new id) and therefore a different idempotency key
    $second = app(CheckoutService::class)->placeOrder($quotes->quote($items, 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c'], 12, null, $org), $org, $owner, $consents, ['mode' => 'bank'], 'cart-a:q2', $ctx);
    expect($second['order']->id)->toBe($first['order']->id)->and(Order::query()->count())->toBe(1);
    expect(AuditEvent::query()->where('action', 'order.place')->where('result', 'replayed')->exists())->toBeTrue();

    // a different cart (or payment mode) is a new order; after the window the same cart may be ordered again
    $other = app(CheckoutService::class)->placeOrder($quotes->quote([['product_key' => 'web-hosting', 'plan_key' => 'start']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c'], 12, null, $org), $org, $owner, $consents, ['mode' => 'bank'], 'cart-b:q1', $ctx);
    expect($other['order']->id)->not->toBe($first['order']->id);
    $this->travelTo(now()->addMinutes(20));
    $later = app(CheckoutService::class)->placeOrder($quotes->quote($items, 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c'], 12, null, $org), $org, $owner, $consents, ['mode' => 'bank'], 'cart-a:q3', $ctx);
    expect($later['order']->id)->not->toBe($first['order']->id)->and(Order::query()->count())->toBe(3);
});
