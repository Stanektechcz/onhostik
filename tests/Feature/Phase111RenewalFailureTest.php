<?php

declare(strict_types=1);

use App\Console\Commands\HandleRenewalPaymentFailuresCommand;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Notifications\RenewalFailureAdminSummaryNotification;
use App\Notifications\RenewalPaymentFailedNotification;
use Database\Factories\InvoiceFactory;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── Command skips when nothing is eligible ────────────────────────────────────

it('command does nothing when no overdue renewal invoices exist', function (): void {
    Notification::fake();
    Config::set('billing.renewal_failure_grace_days', 3);

    $this->artisan(HandleRenewalPaymentFailuresCommand::class)
        ->expectsOutput('No overdue renewal invoices pending failure notification.')
        ->assertExitCode(0);

    Notification::assertNothingSent();
});

it('command skips non-renewal overdue invoices', function (): void {
    Notification::fake();
    Config::set('billing.renewal_failure_grace_days', 3);

    $user = customerUser();
    InvoiceFactory::new()->create([
        'customer_id' => $user->customer->id,
        'status'      => InvoiceStatus::Overdue,
        'purpose'     => 'order',
        'due_date'    => now()->subDays(5),
    ]);

    $this->artisan(HandleRenewalPaymentFailuresCommand::class)
        ->expectsOutput('No overdue renewal invoices pending failure notification.')
        ->assertExitCode(0);

    Notification::assertNothingSent();
});

// ── Command notifies customer ─────────────────────────────────────────────────

it('command sends RenewalPaymentFailedNotification to customer', function (): void {
    Notification::fake();
    Config::set('billing.renewal_failure_grace_days', 3);
    Config::set('billing.renewal_failure_admin_threshold', 10);

    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Suspended,
    ]);
    $invoice = InvoiceFactory::new()->create([
        'customer_id'        => $user->customer->id,
        'status'             => InvoiceStatus::Overdue,
        'purpose'            => 'renewal',
        'renewal_service_id' => $service->id,
        'due_date'           => now()->subDays(5),
        'renewal_failure_notified_at' => null,
    ]);

    $this->artisan(HandleRenewalPaymentFailuresCommand::class)->assertExitCode(0);

    $invoice->refresh();
    expect($invoice->renewal_failure_notified_at)->not->toBeNull();

    Notification::assertSentTo(
        $user,
        RenewalPaymentFailedNotification::class,
        fn ($n) => $n->invoice->id === $invoice->id && $n->service->id === $service->id,
    );
});

// ── Command is idempotent ─────────────────────────────────────────────────────

it('command does not re-notify already handled invoice', function (): void {
    Notification::fake();
    Config::set('billing.renewal_failure_grace_days', 3);

    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);
    InvoiceFactory::new()->create([
        'customer_id'        => $user->customer->id,
        'status'             => InvoiceStatus::Overdue,
        'purpose'            => 'renewal',
        'renewal_service_id' => $service->id,
        'due_date'           => now()->subDays(5),
        'renewal_failure_notified_at' => now()->subDays(1),
    ]);

    $this->artisan(HandleRenewalPaymentFailuresCommand::class)
        ->expectsOutput('No overdue renewal invoices pending failure notification.')
        ->assertExitCode(0);

    Notification::assertNothingSent();
});

// ── Command skips invoices not past grace period ──────────────────────────────

it('command skips renewal invoices within grace period', function (): void {
    Notification::fake();
    Config::set('billing.renewal_failure_grace_days', 7);

    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);
    InvoiceFactory::new()->create([
        'customer_id'        => $user->customer->id,
        'status'             => InvoiceStatus::Overdue,
        'purpose'            => 'renewal',
        'renewal_service_id' => $service->id,
        'due_date'           => now()->subDays(3),
        'renewal_failure_notified_at' => null,
    ]);

    $this->artisan(HandleRenewalPaymentFailuresCommand::class)
        ->expectsOutput('No overdue renewal invoices pending failure notification.')
        ->assertExitCode(0);

    Notification::assertNothingSent();
});

// ── Admin summary is sent when threshold reached ──────────────────────────────

it('command dispatches admin summary when failure count meets threshold', function (): void {
    Notification::fake();
    Config::set('billing.renewal_failure_grace_days', 3);
    Config::set('billing.renewal_failure_admin_threshold', 2);

    $admin = adminUser();

    foreach (range(1, 2) as $_) {
        $u       = customerUser();
        $service = Service::factory()->create(['customer_id' => $u->customer->id]);
        InvoiceFactory::new()->create([
            'customer_id'        => $u->customer->id,
            'status'             => InvoiceStatus::Overdue,
            'purpose'            => 'renewal',
            'renewal_service_id' => $service->id,
            'due_date'           => now()->subDays(5),
            'renewal_failure_notified_at' => null,
        ]);
    }

    $this->artisan(HandleRenewalPaymentFailuresCommand::class)->assertExitCode(0);

    Notification::assertSentTo($admin, RenewalFailureAdminSummaryNotification::class);
});

// ── Notification respects preferences ────────────────────────────────────────

it('RenewalPaymentFailedNotification via() respects renewal mail opt-out', function (): void {
    $user    = customerUser();
    $user->update(['notification_preferences' => ['mail' => ['renewal']]]);

    $service = Service::factory()->create(['customer_id' => $user->customer->id]);
    $invoice = InvoiceFactory::new()->create(['customer_id' => $user->customer->id]);

    $notif = new RenewalPaymentFailedNotification($invoice, $service);
    $via   = $notif->via($user);

    expect($via)->not->toContain('mail')
        ->and($via)->toContain('database');
});
