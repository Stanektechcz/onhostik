<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Notifications\WeeklyDigestNotification;
use Brick\Money\Money;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Command: sends digest when customer has overdue invoice ───────────────────

it('sends weekly digest to customer with overdue invoice', function (): void {
    Notification::fake();

    $user   = customerUser();
    $result = placeOrder($user);
    /** @var Invoice $invoice */
    $invoice = $result['invoice'];
    $invoice->update([
        'status'   => InvoiceStatus::Overdue,
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    $this->artisan('notifications:send-weekly-digest')->assertExitCode(0);

    Notification::assertSentTo($user, WeeklyDigestNotification::class);
});

it('sends weekly digest to customer with upcoming renewal', function (): void {
    Notification::fake();

    $user   = customerUser();
    $result = placeOrder($user);
    /** @var Invoice $invoice */
    $invoice = $result['invoice'];
    $invoice->update([
        'status'   => InvoiceStatus::Sent,
        'due_date' => now()->addDays(7)->toDateString(),
    ]);

    $this->artisan('notifications:send-weekly-digest')->assertExitCode(0);

    Notification::assertSentTo($user, WeeklyDigestNotification::class);
});

it('sends weekly digest to customer with open ticket', function (): void {
    Notification::fake();

    $user = customerUser();
    SupportTicket::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => TicketStatus::Open,
    ]);

    $this->artisan('notifications:send-weekly-digest')->assertExitCode(0);

    Notification::assertSentTo($user, WeeklyDigestNotification::class);
});

it('sends weekly digest to customer with positive credit balance', function (): void {
    Notification::fake();

    $user = customerUser();
    app(CreditLedger::class)->deposit(
        customer: $user->customer,
        amount: Money::ofMinor(50000, 'CZK'),
        description: 'Test deposit',
    );

    $this->artisan('notifications:send-weekly-digest')->assertExitCode(0);

    Notification::assertSentTo($user, WeeklyDigestNotification::class);
});

it('does not send digest when customer has nothing to report', function (): void {
    Notification::fake();

    customerUser(); // No invoices, no tickets, no credit

    $this->artisan('notifications:send-weekly-digest')->assertExitCode(0);

    Notification::assertNothingSent();
});

it('does not include invoices due more than 14 days away in upcoming renewals', function (): void {
    Notification::fake();

    $user   = customerUser();
    $result = placeOrder($user);
    /** @var Invoice $invoice */
    $invoice = $result['invoice'];
    $invoice->update([
        'status'   => InvoiceStatus::Sent,
        'due_date' => now()->addDays(20)->toDateString(),
    ]);

    $this->artisan('notifications:send-weekly-digest')->assertExitCode(0);

    Notification::assertNotSentTo($user, WeeklyDigestNotification::class);
});

// ── Notification content ──────────────────────────────────────────────────────

it('WeeklyDigestNotification includes overdue invoice in mail', function (): void {
    $user   = customerUser();
    $result = placeOrder($user);
    /** @var Invoice $invoice */
    $invoice = $result['invoice'];
    $invoice->update([
        'status'   => InvoiceStatus::Overdue,
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    $notification = new WeeklyDigestNotification(
        creditBalance: Money::ofMinor(0, 'CZK'),
        overdueInvoices: collect([$invoice]),
        upcomingRenewals: collect(),
        openTickets: 0,
    );

    $mail = $notification->toMail($user);

    expect($mail->subject)->toContain('týdenní přehled');
    expect(collect($mail->introLines)->join(' '))->toContain('Nezaplacené faktury');
});

it('WeeklyDigestNotification shows all-good message when nothing is outstanding', function (): void {
    $user = customerUser();

    $notification = new WeeklyDigestNotification(
        creditBalance: Money::ofMinor(1000, 'CZK'),
        overdueInvoices: collect(),
        upcomingRenewals: collect(),
        openTickets: 0,
    );

    $mail = $notification->toMail($user);
    expect(collect($mail->introLines)->join(' '))->toContain('Vše je v pořádku');
});
