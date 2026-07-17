<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Order;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Products\Models\PricingPlan;
use Brick\Money\Money;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Cart → one order with several services, and the payment method the
 * customer picked actually reaching the server.
 *
 * Regression guard: the checkout radios used to live OUTSIDE the submitted
 * form and the controller never accepted `payment_method`, so the choice was
 * silently discarded and every order stayed an unpaid proforma.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Cart basics ───────────────────────────────────────────────────────────────

it('adds a plan to the cart and shows it', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)
        ->from(route('panel.cart.index'))
        ->post(route('panel.cart.add', $plan->id))
        ->assertRedirect(route('panel.cart.index'));

    $this->actingAs($user)
        ->get(route('panel.cart.index'))
        ->assertOk()
        ->assertSee($plan->name);
});

it('refuses to add an inactive plan', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();
    $plan->update(['is_active' => false]);

    $this->actingAs($user)
        ->from(route('panel.cart.index'))
        ->post(route('panel.cart.add', $plan->id))
        ->assertSessionHasErrors('cart');
});

it('updates the quantity of a cart line', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));
    $this->actingAs($user)
        ->from(route('panel.cart.index'))
        ->patch(route('panel.cart.update', $plan->id), ['qty' => 3])
        ->assertRedirect(route('panel.cart.index'));

    expect(session('panel_cart')[$plan->id])->toBe(3);
});

it('removes a line when the quantity is set to zero', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));
    $this->actingAs($user)->patch(route('panel.cart.update', $plan->id), ['qty' => 0]);

    expect(session('panel_cart'))->not->toHaveKey($plan->id);
});

// ── The point of the feature: many services, ONE order ────────────────────────

it('orders several different services in a single order', function (): void {
    $user  = customerUser();
    $plans = PricingPlan::where('is_active', true)
        ->whereHas('product', fn ($q) => $q->where('is_active', true))
        ->take(3)
        ->get();

    expect($plans)->toHaveCount(3);

    foreach ($plans as $plan) {
        $this->actingAs($user)->post(route('panel.cart.add', $plan->id));
    }

    $this->actingAs($user)
        ->post(route('panel.cart.checkout'), ['payment_method' => 'bank'])
        ->assertRedirect();

    $order = Order::where('customer_id', $user->customer->id)->latest('id')->firstOrFail();

    expect($order->items)->toHaveCount(3)
        ->and($order->items->pluck('pricing_plan_id')->sort()->values()->all())
        ->toBe($plans->pluck('id')->sort()->values()->all());
});

it('keeps quantities on the order items', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));
    $this->actingAs($user)->patch(route('panel.cart.update', $plan->id), ['qty' => 4]);
    $this->actingAs($user)->post(route('panel.cart.checkout'), ['payment_method' => 'bank']);

    $order = Order::where('customer_id', $user->customer->id)->latest('id')->firstOrFail();

    expect($order->items->first()->quantity)->toBe(4);
});

it('empties the cart after a successful checkout', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));
    $this->actingAs($user)->post(route('panel.cart.checkout'), ['payment_method' => 'bank']);

    expect(session('panel_cart'))->toBeNull();
});

it('rejects checkout of an empty cart', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->from(route('panel.cart.index'))
        ->post(route('panel.cart.checkout'), ['payment_method' => 'bank'])
        ->assertSessionHasErrors('cart');
});

// ── Payment method must be honoured ───────────────────────────────────────────

it('pays the cart order from credit when credit is chosen', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    app(CreditLedger::class)->deposit(
        $user->customer,
        Money::of(50_000, $user->customer->preferred_currency->value),
        'Test topup',
    );

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));
    $this->actingAs($user)->post(route('panel.cart.checkout'), ['payment_method' => 'credit']);

    $order   = Order::where('customer_id', $user->customer->id)->latest('id')->firstOrFail();
    $invoice = $order->invoices()->latest('id')->firstOrFail();

    expect($invoice->status)->toBe(InvoiceStatus::Paid);
});

it('leaves the proforma unpaid for bank transfer', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));
    $this->actingAs($user)->post(route('panel.cart.checkout'), ['payment_method' => 'bank']);

    $order   = Order::where('customer_id', $user->customer->id)->latest('id')->firstOrFail();
    $invoice = $order->invoices()->latest('id')->firstOrFail();

    expect($invoice->status)->not->toBe(InvoiceStatus::Paid);
});

it('warns instead of failing when credit does not cover the order', function (): void {
    $user = customerUser(); // no credit deposited
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));
    $this->actingAs($user)
        ->post(route('panel.cart.checkout'), ['payment_method' => 'credit'])
        ->assertSessionHas('warning');

    // The order still exists — the customer can pay the proforma another way.
    expect(Order::where('customer_id', $user->customer->id)->count())->toBe(1);
});

it('rejects an unknown payment method', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));
    $this->actingAs($user)
        ->from(route('panel.cart.index'))
        ->post(route('panel.cart.checkout'), ['payment_method' => 'bitcoin'])
        ->assertSessionHasErrors('payment_method');
});

// ── Single-plan checkout must honour the method too ───────────────────────────

it('single-plan order pays from credit when credit is chosen', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    app(CreditLedger::class)->deposit(
        $user->customer,
        Money::of(50_000, $user->customer->preferred_currency->value),
        'Test topup',
    );

    $this->actingAs($user)->post(route('panel.orders.store'), [
        'pricing_plan_id' => $plan->id,
        'payment_method'  => 'credit',
    ]);

    $order   = Order::where('customer_id', $user->customer->id)->latest('id')->firstOrFail();
    $invoice = $order->invoices()->latest('id')->firstOrFail();

    expect($invoice->status)->toBe(InvoiceStatus::Paid);
});

it('single-plan order without a method leaves the proforma unpaid', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.orders.store'), ['pricing_plan_id' => $plan->id]);

    $order   = Order::where('customer_id', $user->customer->id)->latest('id')->firstOrFail();
    $invoice = $order->invoices()->latest('id')->firstOrFail();

    expect($invoice->status)->not->toBe(InvoiceStatus::Paid);
});
