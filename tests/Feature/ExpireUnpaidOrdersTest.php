<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\OrderStatus;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Phase P (P195): unpaid proforma orders expire instead of sitting in Pending
 * forever.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

it('expires an unpaid order past the validity window', function (): void {
    $user  = customerUser();
    ['order' => $order, 'invoice' => $invoice] = placeOrder($user);

    // Age it past the window.
    $days = (int) config('billing.proforma_validity_days', 10);
    $order->forceFill(['created_at' => now()->subDays($days + 1)])->save();

    $this->artisan('billing:expire-unpaid-orders')->assertSuccessful();

    expect($order->refresh()->status)->toBe(OrderStatus::Expired)
        // The open proforma must also stop being payable.
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Cancelled);
});

it('leaves a recent unpaid order alone', function (): void {
    $user = customerUser();
    ['order' => $order] = placeOrder($user);

    $order->forceFill(['created_at' => now()->subDay()])->save();

    $this->artisan('billing:expire-unpaid-orders')->assertSuccessful();

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});

it('never expires an order that has already been paid', function (): void {
    $user = customerUser();
    ['order' => $order, 'invoice' => $invoice] = placeOrder($user);

    $order->forceFill([
        'created_at' => now()->subDays(60),
        'paid_at'    => now(),
        'status'     => OrderStatus::Processing,
    ])->save();
    $invoice->update(['status' => InvoiceStatus::Paid]);

    $this->artisan('billing:expire-unpaid-orders')->assertSuccessful();

    // A paying customer's order must never be expired out from under them.
    expect($order->refresh()->status)->toBe(OrderStatus::Processing);
});

it('does not touch an order with a paid invoice even if paid_at is unset', function (): void {
    $user = customerUser();
    ['order' => $order, 'invoice' => $invoice] = placeOrder($user);

    // Belt and braces: a manual payment could mark the invoice without the
    // order row's paid_at. Still must not expire.
    $order->forceFill(['created_at' => now()->subDays(60)])->save();
    $invoice->update(['status' => InvoiceStatus::Paid]);

    $this->artisan('billing:expire-unpaid-orders')->assertSuccessful();

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});

it('changes nothing on a dry run', function (): void {
    $user = customerUser();
    ['order' => $order] = placeOrder($user);
    $order->forceFill(['created_at' => now()->subDays(60)])->save();

    $this->artisan('billing:expire-unpaid-orders --dry-run')->assertSuccessful();

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});

it('logs an activity entry when it expires an order', function (): void {
    $user = customerUser();
    ['order' => $order] = placeOrder($user);
    $order->forceFill(['created_at' => now()->subDays(60)])->save();

    $this->artisan('billing:expire-unpaid-orders')->assertSuccessful();

    $this->assertDatabaseHas('activity_log', [
        'log_name'    => 'billing',
        'description' => 'order.expired',
        'subject_id'  => $order->id,
    ]);
});
