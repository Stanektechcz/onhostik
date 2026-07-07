<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Notifications\AdminDailyDigestNotification;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

it('daily digest command completes successfully', function (): void {
    adminUser();

    $this->artisan('reports:send-admin-daily')->assertSuccessful();
});

it('daily digest sends notification to admins', function (): void {
    Notification::fake();
    $admin = adminUser();

    $this->artisan('reports:send-admin-daily')->assertSuccessful();

    Notification::assertSentTo($admin, AdminDailyDigestNotification::class);
});

it('daily digest counts overdue invoices', function (): void {
    Notification::fake();
    $admin    = adminUser();
    $customer = customerUser();

    \Illuminate\Support\Facades\DB::table('invoices')->insert([
        'uuid'                     => (string) \Illuminate\Support\Str::uuid(),
        'customer_id'              => $customer->customer->id,
        'status'                   => InvoiceStatus::Overdue->value,
        'type'                     => 'invoice',
        'series'                   => 'CZ',
        'number'                   => 'CZ-2026-TEST01',
        'vat_scenario'             => 'cz_standard',
        'currency'                 => 'CZK',
        'subtotal'                 => 50000,
        'tax_amount'               => 10500,
        'total'                    => 60500,
        'due_date'                 => now()->subDays(5)->toDateString(),
        'snapshot_name'            => 'Test Customer',
        'snapshot_street'          => 'Testovací 1',
        'snapshot_city'            => 'Praha',
        'snapshot_zip'             => '11000',
        'snapshot_country_code'    => 'CZ',
        'created_at'               => now(),
        'updated_at'               => now(),
    ]);

    $this->artisan('reports:send-admin-daily')->assertSuccessful();

    Notification::assertSentTo($admin, AdminDailyDigestNotification::class, function ($n) {
        return $n->overdueInvoices >= 1;
    });
});

it('daily digest counts services due in 7 days', function (): void {
    Notification::fake();
    $admin    = adminUser();
    $customer = customerUser();

    Service::factory()->create([
        'customer_id'   => $customer->customer->id,
        'next_due_date' => now()->addDays(3),
    ]);

    $this->artisan('reports:send-admin-daily')->assertSuccessful();

    Notification::assertSentTo($admin, AdminDailyDigestNotification::class, function ($n) {
        return $n->servicesDueIn7Days >= 1;
    });
});

it('notification mail contains expected content', function (): void {
    adminUser();
    $notification = new AdminDailyDigestNotification(
        overdueInvoices:    5,
        openTickets:        3,
        servicesDueIn7Days: 2,
        date:               now(),
    );

    $mail = $notification->toMail(new \App\Models\User());

    expect($mail->subject)->toContain('Denní přehled');
});

it('digest not sent to customer users', function (): void {
    Notification::fake();
    adminUser();
    $customer = customerUser();

    $this->artisan('reports:send-admin-daily')->assertSuccessful();

    Notification::assertNotSentTo($customer, AdminDailyDigestNotification::class);
});
