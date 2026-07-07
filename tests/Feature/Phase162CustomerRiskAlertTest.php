<?php

declare(strict_types=1);

use App\Notifications\CustomerRiskAlertNotification;
use Illuminate\Support\Facades\Notification;

it('risk alert command notifies admins for at-risk customers', function (): void {
    Notification::fake();
    $admin    = adminUser();
    $customer = customerUser();

    $customer->customer->update(['health_score' => 20]);

    $this->artisan('alerts:customer-risk --threshold=30')->assertSuccessful();

    Notification::assertSentTo($admin, CustomerRiskAlertNotification::class);
});

it('risk alert command does not notify for healthy customers', function (): void {
    Notification::fake();
    adminUser();
    $customer = customerUser();

    $customer->customer->update(['health_score' => 80]);

    $this->artisan('alerts:customer-risk --threshold=30')->assertSuccessful();

    Notification::assertNothingSent();
});

it('risk alert command respects 7-day throttle', function (): void {
    Notification::fake();
    adminUser();
    $customer = customerUser();

    $customer->customer->update([
        'health_score'       => 15,
        'risk_alert_sent_at' => now()->subDays(3),
    ]);

    $this->artisan('alerts:customer-risk --threshold=30')->assertSuccessful();

    Notification::assertNothingSent();
});

it('risk alert command updates risk_alert_sent_at after sending', function (): void {
    Notification::fake();
    adminUser();
    $customer = customerUser();

    $customer->customer->update(['health_score' => 10]);

    $this->artisan('alerts:customer-risk --threshold=30');

    expect($customer->customer->fresh()->risk_alert_sent_at)->not->toBeNull();
});

it('risk alert command uses custom threshold option', function (): void {
    Notification::fake();
    $admin    = adminUser();
    $customer = customerUser();

    $customer->customer->update(['health_score' => 45]);

    $this->artisan('alerts:customer-risk --threshold=50')->assertSuccessful();

    Notification::assertSentTo($admin, CustomerRiskAlertNotification::class);
});
