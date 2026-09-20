<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\CommerceHousekeeping;
use Onhost\Domain\Orders\Models\Cart;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentStateMachineStates;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Browsing leaves quotes and carts behind (every cart change with the drawer open is a quote, every visitor a cart);
 * the nightly prune drops what no order will ever reference and keeps the paper trail of real orders.
 */

beforeEach(fn () => $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]));

it('prunes expired unordered quotes and stale open carts, keeps ordered quotes, fresh quotes and converted carts', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($owner, $org);
    $quotes = app(QuoteService::class);
    $customer = ['country' => 'CZ', 'customer_class' => 'b2c'];
    $consents = ['terms' => ['version' => '4.0'], 'privacy' => ['version' => '4.0'], 'withdrawal_waiver' => ['version' => '4.0'], 'dpa' => ['version' => '4.0'], 'sla' => ['version' => '4.0']];

    $stale = $quotes->quote([['product_key' => 'web-hosting', 'plan_key' => 'start']], 'CZK', $customer, 1, null, null);          // a guest's browsing, long expired
    $recent = $quotes->quote([['product_key' => 'web-hosting', 'plan_key' => 'start']], 'CZK', $customer, 1, null, null);         // expired an hour ago: still inside the grace window
    $fresh = $quotes->quote([['product_key' => 'web-hosting', 'plan_key' => 'standard']], 'CZK', $customer, 12, null, $org);     // valid
    $ordered = $quotes->quote([['product_key' => 'web-hosting', 'plan_key' => 'profi']], 'CZK', $customer, 12, null, $org);      // became an order
    app(CheckoutService::class)->placeOrder($ordered, $org, $owner, $consents, ['mode' => 'bank'], 'prune-test:q1', $ctx);
    $stale->forceFill(['valid_until' => now()->subDays(3)])->save();
    $recent->forceFill(['valid_until' => now()->subHours(2)])->save();
    $ordered->forceFill(['valid_until' => now()->subDays(3), 'state' => 'open'])->save(); // even if its state were left open, the order keeps it

    $old = Cart::query()->create(['session_token' => str_repeat('a', 40), 'state' => 'open', 'currency' => 'CZK', 'commit_months' => 1, 'items' => [], 'expires_at' => now()->subDays(9)]);
    $justExpired = Cart::query()->create(['session_token' => str_repeat('b', 40), 'state' => 'open', 'currency' => 'CZK', 'commit_months' => 1, 'items' => [], 'expires_at' => now()->subDays(2)]);
    $live = Cart::query()->create(['session_token' => str_repeat('c', 40), 'state' => 'open', 'currency' => 'CZK', 'commit_months' => 1, 'items' => [], 'expires_at' => now()->addDays(20)]);
    $converted = Cart::query()->create(['session_token' => str_repeat('d', 40), 'state' => 'converted', 'converted_order_id' => 'ord_x', 'currency' => 'CZK', 'commit_months' => 1, 'items' => [], 'expires_at' => now()->subDays(30)]);

    $this->artisan('onhost:commerce:prune')->assertSuccessful();

    expect(Quote::query()->find($stale->id))->toBeNull()
        ->and(Quote::query()->find($recent->id))->not->toBeNull()
        ->and(Quote::query()->find($fresh->id))->not->toBeNull()
        ->and(Quote::query()->find($ordered->id))->not->toBeNull();
    expect(Cart::query()->find($old->id))->toBeNull()
        ->and(Cart::query()->find($justExpired->id))->not->toBeNull()
        ->and(Cart::query()->find($live->id))->not->toBeNull()
        ->and(Cart::query()->find($converted->id))->not->toBeNull();

    // the counts the operator sees; tighter windows take the rest
    $counts = app(CommerceHousekeeping::class)->prune(0, 1);
    expect($counts)->toBe(['quotes' => 1, 'carts' => 1])->and(Quote::query()->find($recent->id))->toBeNull()->and(Cart::query()->find($justExpired->id))->toBeNull();
});

it('cancels an order nobody paid in time: the proforma is voided and a late transfer no longer buys it at old prices', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($owner, $org);
    $consents = ['terms' => ['version' => '4.0'], 'privacy' => ['version' => '4.0'], 'withdrawal_waiver' => ['version' => '4.0'], 'dpa' => ['version' => '4.0'], 'sla' => ['version' => '4.0']];
    $place = fn (string $key) => app(CheckoutService::class)->placeOrder(app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['n' => $key]]], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c'], 12, null, $org), $org, $owner, $consents, ['mode' => 'bank'], $key, $ctx)['order'];
    $old = $place('unpaid-old');
    $recent = $place('unpaid-recent');
    Order::query()->whereKey($old->id)->update(['created_at' => now()->subDays(15)]);
    Order::query()->whereKey($recent->id)->update(['created_at' => now()->subDays(13)]);

    expect(app(CommerceHousekeeping::class)->expireUnpaid())->toBe(1);
    $old->refresh();
    expect($old->state)->toBe(OrderStateMachine::CANCELLED)->and($recent->refresh()->state)->toBe(OrderStateMachine::PENDING_PAYMENT);
    expect(Invoice::query()->findOrFail($old->invoice_id)->state)->toBe(Invoice::CANCELLED);
    expect(PaymentIntent::query()->findOrFail($old->payment_intent_id)->state)->toBe(PaymentStateMachineStates::CANCELED);
    // the customer is told once, in their own words — a staff note would not be shown
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', "Objednávka {$old->number} byla zrušena")->count())->toBe(1);
    expect(app(CommerceHousekeeping::class)->expireUnpaid())->toBe(0);
});
