<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Notifications\InvoiceDueSoonNotification;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Migration column ───────────────────────────────────────────────────────────

it('Invoice has reminder_before_1d_sent_at column', function (): void {
    $user   = customerUser();
    $result = placeOrder($user);
    $invoice = $result['invoice'];

    expect($invoice->reminder_before_1d_sent_at)->toBeNull();
});

// ── Command: send-due-reminders ────────────────────────────────────────────────

it('command sends reminder for invoice due tomorrow', function (): void {
    Notification::fake();

    $user   = customerUser();
    $result = placeOrder($user);
    $invoice = $result['invoice'];
    $invoice->update([
        'status'   => InvoiceStatus::Sent,
        'due_date' => now()->addDay()->toDateString(),
    ]);

    $this->artisan('billing:send-due-reminders')->assertExitCode(0);

    Notification::assertSentTo($user, InvoiceDueSoonNotification::class);
    expect($invoice->fresh()->reminder_before_1d_sent_at)->not->toBeNull();
});

it('command does not resend already-sent pre-due reminder', function (): void {
    Notification::fake();

    $user   = customerUser();
    $result = placeOrder($user);
    $invoice = $result['invoice'];
    $invoice->update([
        'status'                   => InvoiceStatus::Sent,
        'due_date'                 => now()->addDay()->toDateString(),
        'reminder_before_1d_sent_at' => now()->subHour(),
    ]);

    $this->artisan('billing:send-due-reminders')->assertExitCode(0);

    Notification::assertNotSentTo($user, InvoiceDueSoonNotification::class);
});

it('command skips invoices due today (not tomorrow)', function (): void {
    Notification::fake();

    $user   = customerUser();
    $result = placeOrder($user);
    $invoice = $result['invoice'];
    $invoice->update([
        'status'   => InvoiceStatus::Sent,
        'due_date' => now()->toDateString(),
    ]);

    $this->artisan('billing:send-due-reminders')->assertExitCode(0);

    Notification::assertNotSentTo($user, InvoiceDueSoonNotification::class);
});

it('command skips paid invoices', function (): void {
    Notification::fake();

    $user   = customerUser();
    $result = placeOrder($user);
    $invoice = $result['invoice'];
    $invoice->update([
        'status'   => InvoiceStatus::Paid,
        'due_date' => now()->addDay()->toDateString(),
    ]);

    $this->artisan('billing:send-due-reminders')->assertExitCode(0);

    Notification::assertNotSentTo($user, InvoiceDueSoonNotification::class);
});

it('command skips invoices due in 2 or more days', function (): void {
    Notification::fake();

    $user   = customerUser();
    $result = placeOrder($user);
    $invoice = $result['invoice'];
    $invoice->update([
        'status'   => InvoiceStatus::Sent,
        'due_date' => now()->addDays(5)->toDateString(),
    ]);

    $this->artisan('billing:send-due-reminders')->assertExitCode(0);

    Notification::assertNotSentTo($user, InvoiceDueSoonNotification::class);
});

// ── Full dunning escalation sequence ──────────────────────────────────────────

it('overdue 1d reminder still fires after pre-due was sent', function (): void {
    Notification::fake();

    $user   = customerUser();
    $result = placeOrder($user);
    $invoice = $result['invoice'];
    $invoice->update([
        'status'                     => InvoiceStatus::Overdue,
        'due_date'                   => now()->subDay()->toDateString(),
        'reminder_before_1d_sent_at' => now()->subDays(2),
    ]);

    $this->artisan('billing:send-overdue-reminders')->assertExitCode(0);

    expect($invoice->fresh()->reminder_1d_sent_at)->not->toBeNull();
});

// ── Notification content ──────────────────────────────────────────────────────

it('InvoiceDueSoonNotification toArray contains correct data', function (): void {
    $user   = customerUser();
    $result = placeOrder($user);
    $invoice = $result['invoice'];
    $invoice->update(['due_date' => now()->addDay()->toDateString()]);

    $notification = new InvoiceDueSoonNotification($invoice, 1);
    $data         = $notification->toArray($user);

    expect($data['color'])->toBe('warning')
        ->and($data['icon'])->toBe('clock')
        ->and($data['title'])->toMatch('/1|zítra/u');
});

it('InvoiceDueSoonNotification via returns database when no preferences set', function (): void {
    $user   = customerUser();
    $result = placeOrder($user);
    $invoice = $result['invoice'];

    $notification = new InvoiceDueSoonNotification($invoice, 1);
    $via          = $notification->via($user);

    expect($via)->toContain('database');
});
