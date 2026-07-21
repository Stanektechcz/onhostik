<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Queue-worker fallback: `services:provision-pending` provisions paid orders
 * even when no worker ran the ProvisionHostingServiceJob — the root cause of
 * "credit paid but hosting never provisioned on aaPanel".
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

it('provisions a paid order whose service is stuck in Pending', function (): void {
    $user  = customerUser();
    $order = placeOrder($user)['order'];
    // Simulate the paid-but-not-provisioned state (worker never ran the job).
    $order->update(['status' => OrderStatus::Processing, 'paid_at' => now()]);
    foreach ($order->items as $item) {
        Service::factory()->create([
            'customer_id'         => $user->customer->id,
            'order_item_id'       => $item->id,
            'provisioning_driver' => 'aapanel',
            'status'              => ServiceStatus::Pending,
            'external_id'         => null,
        ]);
    }

    $this->artisan('services:provision-pending --all')->assertSuccessful();

    $service = Service::where('order_item_id', $order->items->first()->id)->firstOrFail();
    expect($service->fresh()->status)->toBe(ServiceStatus::Active)
        ->and($service->fresh()->external_id)->not->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::Active);
});

it('heals a paid order whose item never got a service at all', function (): void {
    $user  = customerUser();
    $order = placeOrder($user)['order'];
    $order->update(['status' => OrderStatus::Processing, 'paid_at' => now()]);

    // No service exists for the item — the InvoicePaid provisioning was lost.
    expect(Service::where('order_item_id', $order->items->first()->id)->exists())->toBeFalse();

    $this->artisan('services:provision-pending --all')->assertSuccessful();

    $service = Service::where('order_item_id', $order->items->first()->id)->first();
    expect($service)->not->toBeNull()
        ->and($service->status)->toBe(ServiceStatus::Active)
        ->and($order->fresh()->status)->toBe(OrderStatus::Active);
});

it('never provisions an unpaid order', function (): void {
    $user  = customerUser();
    $order = placeOrder($user)['order']; // Pending, unpaid

    $this->artisan('services:provision-pending --all')->assertSuccessful();

    expect(Service::where('order_item_id', $order->items->first()->id)->exists())->toBeFalse()
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('completes a processing order whose services are already all active', function (): void {
    $user  = customerUser();
    $order = placeOrder($user)['order'];
    $order->update(['status' => OrderStatus::Processing, 'paid_at' => now()]);
    foreach ($order->items as $item) {
        Service::factory()->create([
            'customer_id'   => $user->customer->id,
            'order_item_id' => $item->id,
            'status'        => ServiceStatus::Active,
        ]);
    }

    $this->artisan('services:provision-pending --all')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Active);
});
