<?php

declare(strict_types=1);

use App\Domains\Billing\Models\Invoice;
use App\Notifications\InvoiceReminderNotification;
use Illuminate\Support\Facades\Notification;

it('escalate reminders command sends first reminder after 7 days', function (): void {
    Notification::fake();
    $customer = customerUser();

    $invoice = Invoice::factory()->create([
        'customer_id'         => $customer->customer->id,
        'status'              => 'sent',
        'due_date'            => now()->subDays(8)->toDateString(),
        'reminder_sent_count' => 0,
    ]);

    $this->artisan('billing:escalate-reminders')->assertSuccessful();

    Notification::assertSentTo($customer, InvoiceReminderNotification::class);
    $invoice->refresh();
    expect($invoice->reminder_sent_count)->toBe(1);
});

it('escalate reminders command does not send reminder before threshold', function (): void {
    Notification::fake();
    $customer = customerUser();

    Invoice::factory()->create([
        'customer_id'         => $customer->customer->id,
        'status'              => 'sent',
        'due_date'            => now()->subDays(3)->toDateString(),
        'reminder_sent_count' => 0,
    ]);

    $this->artisan('billing:escalate-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

it('escalate reminders command sends second reminder at 14 days', function (): void {
    Notification::fake();
    $customer = customerUser();

    $invoice = Invoice::factory()->create([
        'customer_id'         => $customer->customer->id,
        'status'              => 'overdue',
        'due_date'            => now()->subDays(15)->toDateString(),
        'reminder_sent_count' => 1,
    ]);

    $this->artisan('billing:escalate-reminders')->assertSuccessful();

    Notification::assertSentTo($customer, InvoiceReminderNotification::class);
    $invoice->refresh();
    expect($invoice->reminder_sent_count)->toBe(2);
});

it('escalate reminders command stops after third reminder', function (): void {
    Notification::fake();
    $customer = customerUser();

    Invoice::factory()->create([
        'customer_id'         => $customer->customer->id,
        'status'              => 'overdue',
        'due_date'            => now()->subDays(40)->toDateString(),
        'reminder_sent_count' => 3,
    ]);

    $this->artisan('billing:escalate-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

it('invoice reminder notification contains invoice number', function (): void {
    $customer = customerUser();

    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->customer->id,
    ]);

    $notification = new InvoiceReminderNotification($invoice, 1);
    $mail         = $notification->toMail($customer);

    expect($mail)->not->toBeNull();
});
