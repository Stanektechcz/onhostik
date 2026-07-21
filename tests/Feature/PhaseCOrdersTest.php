<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\OrderStatus;
use App\Notifications\OrderReceivedNotification;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * Phase C order-flow extensions: customer order cancellation, order-received
 * confirmation e-mail, order status history, preferred-currency switch.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

it('lets a customer cancel their own unpaid order and cancels the proforma', function (): void {
    $user = customerUser();
    ['order' => $order, 'invoice' => $invoice] = placeOrder($user);

    $this->actingAs($user)
        ->post(route('panel.orders.cancel', $order))
        ->assertRedirect(route('panel.orders.index'));

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->cancelled_at)->not->toBeNull()
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Cancelled);
});

it('refuses to cancel a paid order', function (): void {
    $user  = customerUser();
    $order = placeOrder($user)['order'];
    $order->update(['status' => OrderStatus::Processing, 'paid_at' => now()]);

    $this->actingAs($user)
        ->post(route('panel.orders.cancel', $order))
        ->assertSessionHasErrors('order');

    expect($order->fresh()->status)->toBe(OrderStatus::Processing);
});

it('forbids cancelling another customer\'s order', function (): void {
    $order = placeOrder(customerUser())['order'];

    $this->actingAs(customerUser())
        ->post(route('panel.orders.cancel', $order))
        ->assertForbidden();
});

it('sends an order-received notification when an order is created', function (): void {
    Notification::fake();
    $user = customerUser();

    placeOrder($user); // uses CreateOrderAction

    Notification::assertSentTo($user, OrderReceivedNotification::class);
});

it('lets a customer switch their preferred currency', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->put(route('panel.account.profile.update'), ['name' => $user->name, 'preferred_currency' => 'EUR'])
        ->assertRedirect();

    expect($user->customer->fresh()->preferred_currency->value)->toBe('EUR');
});

it('shows the order status history on the order detail', function (): void {
    $user  = customerUser();
    $order = placeOrder($user)['order'];

    $this->actingAs($user)
        ->get(route('panel.orders.show', $order))
        ->assertOk()
        ->assertSee('Historie objednávky')
        ->assertSee('Objednávka vytvořena');
});
