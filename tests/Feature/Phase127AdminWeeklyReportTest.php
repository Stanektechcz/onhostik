<?php

declare(strict_types=1);

use App\Notifications\AdminWeeklyReportNotification;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

it('reports:send-admin-weekly command sends notification to admin', function (): void {
    Notification::fake();

    $admin = adminUser();

    Artisan::call('reports:send-admin-weekly');

    Notification::assertSentTo($admin, AdminWeeklyReportNotification::class);
});

it('reports:send-admin-weekly sends to all admin users', function (): void {
    Notification::fake();

    $admin1 = adminUser();
    $admin2 = adminUser();

    Artisan::call('reports:send-admin-weekly');

    Notification::assertSentTo($admin1, AdminWeeklyReportNotification::class);
    Notification::assertSentTo($admin2, AdminWeeklyReportNotification::class);
});

it('reports:send-admin-weekly does not send to customer users', function (): void {
    Notification::fake();

    adminUser(); // ensure admin role exists in DB
    $customer = customerUser();

    Artisan::call('reports:send-admin-weekly');

    Notification::assertNotSentTo($customer, AdminWeeklyReportNotification::class);
});

it('weekly report notification contains expected subject', function (): void {
    Notification::fake();

    $admin = adminUser();

    Artisan::call('reports:send-admin-weekly');

    Notification::assertSentTo($admin, AdminWeeklyReportNotification::class, function ($notification) {
        $mail = $notification->toMail($notification);
        return str_contains($mail->subject, 'Týdenní přehled OnHost');
    });
});

it('weekly report notification contains new customer count', function (): void {
    Notification::fake();

    $admin = adminUser();
    customerUser(); // one new customer this week

    Artisan::call('reports:send-admin-weekly');

    Notification::assertSentTo($admin, AdminWeeklyReportNotification::class, function ($notification) {
        return $notification->newCustomers >= 1;
    });
});

it('AdminWeeklyReportNotification sends via mail', function (): void {
    $notification = new AdminWeeklyReportNotification(
        newCustomers:   5,
        revenueMinor:   100000,
        newTickets:     3,
        newOrders:      7,
        activeServices: 42,
        weekStart:      now()->subDays(7),
        weekEnd:        now(),
    );

    $admin = adminUser();

    expect($notification->via($admin))->toBe(['mail']);
});
