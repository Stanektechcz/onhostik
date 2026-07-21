<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\CreateCartOrderAction;
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

it('caps a provisioning product at quantity 1 in the cart (C37)', function (): void {
    $user = customerUser();
    // Every catalogue product provisions a distinct 1:1 instance, so a second
    // one is a separate order line — not qty>1 on the same line.
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    expect($plan->product?->provisionsInstance())->toBeTrue();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));
    $this->actingAs($user)->patch(route('panel.cart.update', $plan->id), ['qty' => 5]);

    expect(session('panel_cart')[$plan->id])->toBe(1);

    // A second add does not stack it either.
    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));
    expect(session('panel_cart')[$plan->id])->toBe(1);
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
        ->post(route('panel.cart.checkout'), ['payment_method' => 'bank', 'terms' => '1'])
        ->assertRedirect();

    $order = Order::where('customer_id', $user->customer->id)->latest('id')->firstOrFail();

    expect($order->items)->toHaveCount(3)
        ->and($order->items->pluck('pricing_plan_id')->sort()->values()->all())
        ->toBe($plans->pluck('id')->sort()->values()->all());
});

it('persists quantity on order items created through the cart action', function (): void {
    // The cart UI caps provisioning products at 1 (C37), but the action still
    // supports qty>1 for admin/reseller/API callers — guard that it persists.
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $order = app(CreateCartOrderAction::class)->execute($user->customer, [
        ['plan' => $plan, 'qty' => 4],
    ]);

    expect($order->items->first()->quantity)->toBe(4);
});

it('empties the cart after a successful checkout', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));
    $this->actingAs($user)->post(route('panel.cart.checkout'), ['payment_method' => 'bank', 'terms' => '1']);

    expect(session('panel_cart'))->toBeNull();
});

it('rejects checkout of an empty cart', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->from(route('panel.cart.index'))
        ->post(route('panel.cart.checkout'), ['payment_method' => 'bank', 'terms' => '1'])
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
    $this->actingAs($user)->post(route('panel.cart.checkout'), ['payment_method' => 'credit', 'terms' => '1']);

    $order   = Order::where('customer_id', $user->customer->id)->latest('id')->firstOrFail();
    $invoice = $order->invoices()->latest('id')->firstOrFail();

    expect($invoice->status)->toBe(InvoiceStatus::Paid);
});

it('leaves the proforma unpaid for bank transfer', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));
    $this->actingAs($user)->post(route('panel.cart.checkout'), ['payment_method' => 'bank', 'terms' => '1']);

    $order   = Order::where('customer_id', $user->customer->id)->latest('id')->firstOrFail();
    $invoice = $order->invoices()->latest('id')->firstOrFail();

    expect($invoice->status)->not->toBe(InvoiceStatus::Paid);
});

it('warns instead of failing when credit does not cover the order', function (): void {
    $user = customerUser(); // no credit deposited
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));
    $this->actingAs($user)
        ->post(route('panel.cart.checkout'), ['payment_method' => 'credit', 'terms' => '1'])
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
        ->post(route('panel.cart.checkout'), ['payment_method' => 'bitcoin', 'terms' => '1'])
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

// ── Phase C cart extensions (C38/C40/C41/C44) ─────────────────────────────────

it('shows VAT, total with VAT and the recurring price in the cart summary (C38/C41)', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));

    $this->actingAs($user)->get(route('panel.cart.index'))
        ->assertOk()
        ->assertSee('DPH')
        ->assertSee('Celkem k úhradě')
        ->assertSee('Opakovaná platba');
});

it('registers a new domain flagged in the cart (C40)', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)
        ->whereHas('product', fn ($q) => $q->where('provisioning_driver', 'aapanel'))
        ->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));
    $this->actingAs($user)->post(route('panel.cart.checkout'), [
        'payment_method'   => 'bank',
        'terms'            => '1',
        'domains'          => [$plan->id => 'moje-nova-domena.cz'],
        'register_domains' => [$plan->id => '1'],
    ])->assertRedirect();

    $item = Order::where('customer_id', $user->customer->id)->latest('id')->firstOrFail()
        ->items->firstOrFail();

    expect($item->config['domain'] ?? null)->toBe('moje-nova-domena.cz')
        ->and($item->config['register_domain'] ?? false)->toBeTrue();
});

it('refuses to register an unavailable new domain from the cart (C40)', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)
        ->whereHas('product', fn ($q) => $q->where('provisioning_driver', 'aapanel'))
        ->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));
    $this->actingAs($user)
        ->from(route('panel.cart.index'))
        ->post(route('panel.cart.checkout'), [
            'payment_method'   => 'bank',
            'terms'            => '1',
            'domains'          => [$plan->id => 'taken-domena.cz'],
            'register_domains' => [$plan->id => '1'],
        ])
        ->assertSessionHasErrors('register_domains');

    expect(Order::where('customer_id', $user->customer->id)->count())->toBe(0);
});

it('still orders an existing domain without registering it (C40)', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)
        ->whereHas('product', fn ($q) => $q->where('provisioning_driver', 'aapanel'))
        ->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));
    // register_domains omitted → existing domain, no registration.
    $this->actingAs($user)->post(route('panel.cart.checkout'), [
        'payment_method' => 'bank',
        'terms'          => '1',
        'domains'        => [$plan->id => 'muj-existujici-web.cz'],
    ])->assertRedirect();

    $item = Order::where('customer_id', $user->customer->id)->latest('id')->firstOrFail()
        ->items->firstOrFail();

    expect($item->config['domain'] ?? null)->toBe('muj-existujici-web.cz')
        ->and($item->config['register_domain'] ?? false)->toBeFalse();
});

it('defaults to credit and hides the shortfall note when the balance covers the cart (C44)', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    app(CreditLedger::class)->deposit(
        $user->customer,
        Money::of(100_000, $user->customer->preferred_currency->value),
        'Test topup',
    );

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));

    $this->actingAs($user)->get(route('panel.cart.index'))
        ->assertOk()
        ->assertSee('Ihned uhrazeno a zřízeno')
        ->assertDontSee('Nedostatečný zůstatek');
});

it('refuses checkout without consent to the terms (C50)', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));
    $this->actingAs($user)
        ->from(route('panel.cart.index'))
        ->post(route('panel.cart.checkout'), ['payment_method' => 'bank']) // no terms
        ->assertSessionHasErrors('terms');

    expect(Order::where('customer_id', $user->customer->id)->count())->toBe(0);
});

it('shows the terms consent and provisioning estimate in the cart (C49/C50)', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));

    $this->actingAs($user)->get(route('panel.cart.index'))
        ->assertOk()
        ->assertSee('obchodními podmínkami')
        ->assertSee('Zřízení služby obvykle do několika minut', false);
});

it('marks credit unavailable when the balance does not cover the cart (C44)', function (): void {
    $user = customerUser(); // no credit
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));

    $this->actingAs($user)->get(route('panel.cart.index'))
        ->assertOk()
        ->assertSee('Nedostatečný zůstatek');
});
