<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\SyncOrderCompletionAction;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * The order-completion lifecycle fix + the admin accept/cancel/edit actions
 * and per-line domain capture in the cart.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Order → Active sync ────────────────────────────────────────────────────────

it('advances a paid order to Active once all its services are live', function (): void {
    $user  = customerUser();
    $order = placeOrder($user)['order'];

    // Simulate the paid state: order Processing, every item has an Active service.
    $order->update(['status' => OrderStatus::Processing, 'paid_at' => now()]);

    foreach ($order->items as $item) {
        Service::factory()->create([
            'customer_id'   => $user->customer->id,
            'order_item_id' => $item->id,
            'status'        => ServiceStatus::Active,
        ]);
    }

    app(SyncOrderCompletionAction::class)->execute($order->fresh());

    expect($order->fresh()->status)->toBe(OrderStatus::Active);
});

it('does not advance an order while a service is still pending', function (): void {
    $user  = customerUser();
    $order = placeOrder($user)['order'];
    $order->update(['status' => OrderStatus::Processing, 'paid_at' => now()]);

    foreach ($order->items as $item) {
        Service::factory()->create([
            'customer_id'   => $user->customer->id,
            'order_item_id' => $item->id,
            'status'        => ServiceStatus::Pending,
        ]);
    }

    app(SyncOrderCompletionAction::class)->execute($order->fresh());

    expect($order->fresh()->status)->toBe(OrderStatus::Processing);
});

it('never touches a pending (unpaid) order', function (): void {
    $user  = customerUser();
    $order = placeOrder($user)['order'];

    expect($order->status)->toBe(OrderStatus::Pending);

    app(SyncOrderCompletionAction::class)->execute($order);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

// ── Admin accept / cancel / update ──────────────────────────────────────────────

it('lets an admin accept an order — paying its proforma and moving it forward', function (): void {
    $customerUser = customerUser();
    ['order' => $order, 'invoice' => $invoice] = placeOrder($customerUser);

    expect($invoice->status)->toBe(InvoiceStatus::Sent)
        ->and($order->status)->toBe(OrderStatus::Pending);

    $this->actingAs(adminUser())
        ->post(route('admin.orders.accept', $order))
        ->assertRedirect();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($order->fresh()->paid_at)->not->toBeNull()
        ->and($order->fresh()->status)->not->toBe(OrderStatus::Pending);
});

it('is idempotent when an admin accepts twice', function (): void {
    $customerUser = customerUser();
    ['order' => $order, 'invoice' => $invoice] = placeOrder($customerUser);

    $admin = adminUser();
    $this->actingAs($admin)->post(route('admin.orders.accept', $order));
    $this->actingAs($admin)->post(route('admin.orders.accept', $order));

    expect($invoice->fresh()->payments()->where('status', 'completed')->count())->toBe(1);
});

it('lets an admin cancel an order', function (): void {
    $order = placeOrder(customerUser())['order'];

    $this->actingAs(adminUser())
        ->post(route('admin.orders.cancel', $order))
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->cancelled_at)->not->toBeNull();
});

it('lets an admin edit order notes, status and a line domain', function (): void {
    $order = placeOrder(customerUser())['order'];
    $item  = $order->items->first();

    $this->actingAs(adminUser())
        ->patch(route('admin.orders.update', $order), [
            'status'      => 'active',
            'notes'       => 'Ověřeno telefonicky.',
            'item_domain' => [$item->id => 'Priklad.CZ'],
        ])
        ->assertRedirect();

    $fresh = $order->fresh();
    expect($fresh->status)->toBe(OrderStatus::Active)
        ->and($fresh->notes)->toBe('Ověřeno telefonicky.')
        ->and($fresh->items->firstWhere('id', $item->id)->config['domain'])->toBe('priklad.cz');
});

it('forbids a customer from accepting an order', function (): void {
    $order = placeOrder(customerUser())['order'];

    $this->actingAs(customerUser())
        ->post(route('admin.orders.accept', $order))
        ->assertForbidden();
});

it('renders the admin order detail with accept + edit controls', function (): void {
    $order = placeOrder(customerUser())['order'];

    $this->actingAs(adminUser())
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('Akceptovat a aktivovat')
        ->assertSee('Upravit objednávku')
        ->assertSee(route('admin.orders.accept', $order))
        ->assertSee(route('admin.orders.update', $order));
});

// ── Cart captures the domain per line ────────────────────────────────────────────

it('stores the domain entered in the cart on the order item', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));

    $this->actingAs($user)
        ->post(route('panel.cart.checkout'), [
            'payment_method' => 'bank',
            'domains'        => [$plan->id => 'mujweb.cz'],
        ])
        ->assertRedirect();

    $item = \App\Domains\Billing\Models\OrderItem::where('pricing_plan_id', $plan->id)->latest('id')->first();

    expect($item)->not->toBeNull()
        ->and($item->config['domain'] ?? null)->toBe('mujweb.cz');
});

it('rejects an invalid domain in the cart checkout', function (): void {
    $user = customerUser();
    $plan = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs($user)->post(route('panel.cart.add', $plan->id));

    $this->actingAs($user)
        ->post(route('panel.cart.checkout'), [
            'payment_method' => 'bank',
            'domains'        => [$plan->id => 'not a domain'],
        ])
        ->assertSessionHasErrors('domains.' . $plan->id);
});
