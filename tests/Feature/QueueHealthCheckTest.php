<?php

declare(strict_types=1);

use App\Notifications\QueueHealthAlertNotification;
use Illuminate\Support\Facades\Notification;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Queue health alert (audit 500 #44) — warns admins when the queue backs up or
 * accumulates failures (stalled worker).
 */

it('does not alert when the queue is within thresholds', function (): void {
    Notification::fake();
    adminUser();
    config(['queue.health.max_pending' => 500, 'queue.health.max_failed' => 25]);

    $this->artisan('queue:health-check')->assertExitCode(0);

    Notification::assertNothingSent();
});

it('alerts admins when the pending queue exceeds the threshold', function (): void {
    Notification::fake();
    $admin = adminUser();
    config(['queue.health.max_pending' => -1]); // any pending count now exceeds it

    $this->artisan('queue:health-check')->assertExitCode(0);

    Notification::assertSentTo($admin, QueueHealthAlertNotification::class);
});

it('does not alert customers, only admins', function (): void {
    Notification::fake();
    $admin    = adminUser();
    $customer = customerUser();
    config(['queue.health.max_failed' => -1]);

    $this->artisan('queue:health-check');

    Notification::assertSentTo($admin, QueueHealthAlertNotification::class);
    Notification::assertNotSentTo($customer, QueueHealthAlertNotification::class);
});
