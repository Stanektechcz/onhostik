<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Notifications\RenewalReminderNotification;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Model columns ─────────────────────────────────────────────────────────────

it('Service has renewal reminder tracking columns', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    $service  = \App\Domains\Provisioning\Models\Service::factory()->create([
        'customer_id' => $customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    expect($service->renewal_reminder_30d_sent_at)->toBeNull()
        ->and($service->renewal_reminder_14d_sent_at)->toBeNull()
        ->and($service->renewal_reminder_7d_sent_at)->toBeNull()
        ->and($service->renewal_reminder_1d_sent_at)->toBeNull();
});

// ── Command ───────────────────────────────────────────────────────────────────

it('command sends 30d reminder and marks sent_at', function (): void {
    Notification::fake();

    $user     = customerUser();
    $customer = $user->customer;

    $service = Service::factory()->create([
        'customer_id'  => $customer->id,
        'status'       => ServiceStatus::Active,
        'auto_renew'   => true,
        'next_due_date' => now()->addDays(30)->toDateString(),
    ]);

    // Create an open renewal invoice
    $result  = placeOrder($user);
    $invoice = $result['invoice'];
    $invoice->update([
        'renewal_service_id' => $service->id,
        'status'             => InvoiceStatus::Sent,
    ]);

    $this->artisan('billing:send-renewal-reminders')->assertExitCode(0);

    Notification::assertSentTo($user, RenewalReminderNotification::class);
    expect($service->fresh()->renewal_reminder_30d_sent_at)->not->toBeNull();
});

it('command does not resend if already sent', function (): void {
    Notification::fake();

    $user     = customerUser();
    $customer = $user->customer;

    $service = Service::factory()->create([
        'customer_id'                  => $customer->id,
        'status'                       => ServiceStatus::Active,
        'auto_renew'                   => true,
        'next_due_date'                => now()->addDays(30)->toDateString(),
        'renewal_reminder_30d_sent_at' => now()->subDay(),
    ]);

    $result  = placeOrder($user);
    $invoice = $result['invoice'];
    $invoice->update([
        'renewal_service_id' => $service->id,
        'status'             => InvoiceStatus::Sent,
    ]);

    $this->artisan('billing:send-renewal-reminders')->assertExitCode(0);

    Notification::assertNotSentTo($user, RenewalReminderNotification::class);
});

it('command skips services with no open renewal invoice', function (): void {
    Notification::fake();

    $user     = customerUser();
    $customer = $user->customer;

    Service::factory()->create([
        'customer_id'  => $customer->id,
        'status'       => ServiceStatus::Active,
        'auto_renew'   => true,
        'next_due_date' => now()->addDays(7)->toDateString(),
    ]);

    $this->artisan('billing:send-renewal-reminders')->assertExitCode(0);

    Notification::assertNotSentTo($user, RenewalReminderNotification::class);
});

it('command handles all four thresholds', function (): void {
    Notification::fake();

    $user     = customerUser();
    $customer = $user->customer;

    // Create services for each threshold
    foreach ([30, 14, 7, 1] as $days) {
        $service = Service::factory()->create([
            'customer_id'  => $customer->id,
            'status'       => ServiceStatus::Active,
            'auto_renew'   => true,
            'next_due_date' => now()->addDays($days)->toDateString(),
        ]);

        $result  = placeOrder($user);
        $invoice = $result['invoice'];
        $invoice->update([
            'renewal_service_id' => $service->id,
            'status'             => InvoiceStatus::Sent,
        ]);
    }

    $this->artisan('billing:send-renewal-reminders')->assertExitCode(0);

    Notification::assertSentToTimes($user, RenewalReminderNotification::class, 4);
});

// ── Admin page ────────────────────────────────────────────────────────────────

it('admin can view upcoming renewals page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.upcoming-renewals.index'))
        ->assertOk()
        ->assertSee('Nadcházející obnovy');
});

it('upcoming renewals page filters by days parameter', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.upcoming-renewals.index', ['days' => 7]))
        ->assertOk();
});

it('non-admin cannot view upcoming renewals', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.upcoming-renewals.index'))
        ->assertStatus(403);
});
