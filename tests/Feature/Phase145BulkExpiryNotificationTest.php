<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Notifications\ServiceExpiryAlertNotification;
use Illuminate\Support\Facades\Notification;

it('admin can send bulk expiry notifications', function (): void {
    Notification::fake();
    $admin    = adminUser();
    $customer = customerUser();

    \App\Domains\Provisioning\Models\Service::factory()->create([
        'customer_id'   => $customer->customer->id,
        'status'        => ServiceStatus::Active,
        'next_due_date' => now()->addDays(5)->toDateString(),
        'label'         => 'Test VPS',
    ]);

    $this->actingAs($admin)
         ->post(route('admin.bulk.service-expiry-notify'), ['days' => 7])
         ->assertRedirect();

    Notification::assertSentTo($customer, ServiceExpiryAlertNotification::class);
});

it('bulk expiry notification respects days parameter', function (): void {
    Notification::fake();
    $admin    = adminUser();
    $customer = customerUser();

    \App\Domains\Provisioning\Models\Service::factory()->create([
        'customer_id'   => $customer->customer->id,
        'status'        => ServiceStatus::Active,
        'next_due_date' => now()->addDays(20)->toDateString(),
        'label'         => 'Far-future VPS',
    ]);

    $this->actingAs($admin)
         ->post(route('admin.bulk.service-expiry-notify'), ['days' => 7])
         ->assertRedirect();

    Notification::assertNotSentTo($customer, ServiceExpiryAlertNotification::class);
});

it('invalid days value is rejected', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->post(route('admin.bulk.service-expiry-notify'), ['days' => 99])
         ->assertSessionHasErrors(['days']);
});

it('suspended services are excluded from bulk expiry notification', function (): void {
    Notification::fake();
    $admin    = adminUser();
    $customer = customerUser();

    \App\Domains\Provisioning\Models\Service::factory()->create([
        'customer_id'   => $customer->customer->id,
        'status'        => ServiceStatus::Suspended,
        'next_due_date' => now()->addDays(3)->toDateString(),
        'label'         => 'Suspended VPS',
    ]);

    $this->actingAs($admin)
         ->post(route('admin.bulk.service-expiry-notify'), ['days' => 7])
         ->assertRedirect();

    Notification::assertNotSentTo($customer, ServiceExpiryAlertNotification::class);
});

it('customer cannot send bulk expiry notifications', function (): void {
    adminUser();
    $user = customerUser();

    $this->actingAs($user)
         ->post(route('admin.bulk.service-expiry-notify'), ['days' => 7])
         ->assertForbidden();
});
