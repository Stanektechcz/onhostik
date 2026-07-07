<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Events\InvoicePaid;
use App\Domains\Billing\Listeners\HandleInvoicePaid;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\ChangeServiceStateJob;
use App\Domains\Provisioning\Models\Service;
use App\Notifications\ServiceSuspensionWarningNotification;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Migration column ──────────────────────────────────────────────────────────

it('Invoice has suspension_warning_sent_at column', function (): void {
    $user   = customerUser();
    $result = placeOrder($user);

    expect($result['invoice']->suspension_warning_sent_at)->toBeNull();
});

// ── Warning command: happy path ────────────────────────────────────────────────

it('sends suspension warning for overdue invoice past warning threshold', function (): void {
    Notification::fake();
    Bus::fake();

    $user   = customerUser();
    $result = placeOrder($user);
    /** @var Invoice $invoice */
    $invoice = $result['invoice'];
    $order   = $result['order'];

    $orderItem = $order->items->first();

    $invoice->update([
        'status'   => InvoiceStatus::Overdue,
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'order_item_id' => $orderItem->id,
        'status'        => ServiceStatus::Active,
    ]);

    $this->artisan('billing:send-suspension-warnings')->assertExitCode(0);

    Notification::assertSentTo($user, ServiceSuspensionWarningNotification::class);
    expect($invoice->fresh()->suspension_warning_sent_at)->not->toBeNull();
});

it('does not resend warning when suspension_warning_sent_at is already set', function (): void {
    Notification::fake();

    $user   = customerUser();
    $result = placeOrder($user);
    /** @var Invoice $invoice */
    $invoice = $result['invoice'];
    $order   = $result['order'];

    $orderItem = $order->items->first();

    $invoice->update([
        'status'                      => InvoiceStatus::Overdue,
        'due_date'                    => now()->subDays(5)->toDateString(),
        'suspension_warning_sent_at'  => now()->subHour(),
    ]);

    Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'order_item_id' => $orderItem->id,
        'status'        => ServiceStatus::Active,
    ]);

    $this->artisan('billing:send-suspension-warnings')->assertExitCode(0);

    Notification::assertNotSentTo($user, ServiceSuspensionWarningNotification::class);
});

it('does not send warning when invoice is not yet past warning threshold', function (): void {
    Notification::fake();

    $user   = customerUser();
    $result = placeOrder($user);
    /** @var Invoice $invoice */
    $invoice = $result['invoice'];
    $order   = $result['order'];

    $orderItem = $order->items->first();

    // Only 1 day overdue — warning threshold is 3 days
    $invoice->update([
        'status'   => InvoiceStatus::Overdue,
        'due_date' => now()->subDays(1)->toDateString(),
    ]);

    Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'order_item_id' => $orderItem->id,
        'status'        => ServiceStatus::Active,
    ]);

    $this->artisan('billing:send-suspension-warnings')->assertExitCode(0);

    Notification::assertNotSentTo($user, ServiceSuspensionWarningNotification::class);
});

it('skips dunning-paused invoices', function (): void {
    Notification::fake();

    $user   = customerUser();
    $result = placeOrder($user);
    /** @var Invoice $invoice */
    $invoice = $result['invoice'];
    $order   = $result['order'];

    $orderItem = $order->items->first();

    $invoice->update([
        'status'               => InvoiceStatus::Overdue,
        'due_date'             => now()->subDays(5)->toDateString(),
        'dunning_paused_until' => now()->addDays(3),
    ]);

    Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'order_item_id' => $orderItem->id,
        'status'        => ServiceStatus::Active,
    ]);

    $this->artisan('billing:send-suspension-warnings')->assertExitCode(0);

    Notification::assertNotSentTo($user, ServiceSuspensionWarningNotification::class);
});

it('skips invoice when linked service is already suspended', function (): void {
    Notification::fake();

    $user   = customerUser();
    $result = placeOrder($user);
    /** @var Invoice $invoice */
    $invoice = $result['invoice'];
    $order   = $result['order'];

    $orderItem = $order->items->first();

    $invoice->update([
        'status'   => InvoiceStatus::Overdue,
        'due_date' => now()->subDays(5)->toDateString(),
    ]);

    // Service already suspended — warning not applicable
    Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'order_item_id' => $orderItem->id,
        'status'        => ServiceStatus::Suspended,
    ]);

    $this->artisan('billing:send-suspension-warnings')->assertExitCode(0);

    Notification::assertNotSentTo($user, ServiceSuspensionWarningNotification::class);
});

it('skips paid invoices', function (): void {
    Notification::fake();

    $user   = customerUser();
    $result = placeOrder($user);
    /** @var Invoice $invoice */
    $invoice = $result['invoice'];

    $invoice->update([
        'status'   => InvoiceStatus::Paid,
        'due_date' => now()->subDays(5)->toDateString(),
        'paid_at'  => now(),
    ]);

    $this->artisan('billing:send-suspension-warnings')->assertExitCode(0);

    Notification::assertNotSentTo($user, ServiceSuspensionWarningNotification::class);
});

// ── Existing suspension command ───────────────────────────────────────────────

it('billing:suspend-overdue dispatches suspend job for grace-exceeded invoice', function (): void {
    Bus::fake([ChangeServiceStateJob::class]);

    $user   = customerUser();
    $result = placeOrder($user);
    /** @var Invoice $invoice */
    $invoice = $result['invoice'];
    $order   = $result['order'];

    $orderItem = $order->items->first();

    $invoice->update([
        'status'   => InvoiceStatus::Overdue,
        'due_date' => now()->subDays(10)->toDateString(),
    ]);

    Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'order_item_id' => $orderItem->id,
        'status'        => ServiceStatus::Active,
    ]);

    $this->artisan('billing:suspend-overdue')->assertExitCode(0);

    Bus::assertDispatched(ChangeServiceStateJob::class, function ($job): bool {
        return $job->operation === 'suspend';
    });
});

it('billing:suspend-overdue does not dispatch for invoice within grace period', function (): void {
    Bus::fake([ChangeServiceStateJob::class]);

    $user   = customerUser();
    $result = placeOrder($user);
    /** @var Invoice $invoice */
    $invoice = $result['invoice'];
    $order   = $result['order'];

    $orderItem = $order->items->first();

    // Only 3 days overdue — grace period is 7 days
    $invoice->update([
        'status'   => InvoiceStatus::Overdue,
        'due_date' => now()->subDays(3)->toDateString(),
    ]);

    Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'order_item_id' => $orderItem->id,
        'status'        => ServiceStatus::Active,
    ]);

    $this->artisan('billing:suspend-overdue')->assertExitCode(0);

    Bus::assertNotDispatched(ChangeServiceStateJob::class);
});

// ── Auto-unsuspend on renewal payment ─────────────────────────────────────────

it('HandleInvoicePaid dispatches unsuspend job when renewal invoice paid for suspended service', function (): void {
    Bus::fake([ChangeServiceStateJob::class]);

    $user   = customerUser();
    $result = placeOrder($user);
    $order  = $result['order'];

    $orderItem = $order->items->first();

    $service = Service::factory()->create([
        'customer_id'      => $user->customer->id,
        'order_item_id'    => $orderItem->id,
        'status'           => ServiceStatus::Suspended,
        'suspended_at'     => now()->subDays(5),
        'suspension_reason' => 'overdue_invoice',
        'next_due_date'    => now()->subDays(5)->toDateString(),
    ]);

    $renewalInvoice = Invoice::factory()->create([
        'customer_id'        => $user->customer->id,
        'renewal_service_id' => $service->id,
        'purpose'            => 'renewal',
        'type'               => InvoiceType::Proforma,
        'status'             => InvoiceStatus::Sent,
        'order_id'           => $order->id,
    ]);

    $paymentId = \Illuminate\Support\Facades\DB::table('payments')->insertGetId([
        'uuid'        => \Illuminate\Support\Str::uuid(),
        'customer_id' => $user->customer->id,
        'invoice_id'  => $renewalInvoice->id,
        'method'      => 'credit',
        'status'      => 'completed',
        'currency'    => 'CZK',
        'amount'      => 10000,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $payment = new \App\Domains\Billing\Models\Payment();
    $payment->forceFill(['id' => $paymentId]);

    $renewalInvoice->update(['status' => InvoiceStatus::Paid, 'paid_at' => now()]);

    app(HandleInvoicePaid::class)->handle(new InvoicePaid($renewalInvoice, $payment));

    Bus::assertDispatched(ChangeServiceStateJob::class, function ($job) use ($service): bool {
        return $job->serviceId === $service->id && $job->operation === 'unsuspend';
    });
});

// ── Warning notification content ─────────────────────────────────────────────

it('ServiceSuspensionWarningNotification carries correct payload', function (): void {
    $user   = customerUser();
    $result = placeOrder($user);
    /** @var Invoice $invoice */
    $invoice = $result['invoice'];
    $order   = $result['order'];

    Service::factory()->create([
        'customer_id' => $user->customer->id,
        'label'       => 'mujweb.cz',
    ]);

    $service      = Service::first();
    $notification = new ServiceSuspensionWarningNotification($service, $invoice, 4);
    $array        = $notification->toArray($user);

    expect($array['color'])->toBe('warning')
        ->and($array['icon'])->toBe('alert-triangle')
        ->and($array['invoice_id'])->toBe($invoice->id)
        ->and($array['service_id'])->toBe($service->id);

    $mail = $notification->toMail($user);
    expect($mail->subject)->toContain('4 dny')
        ->and($mail->subject)->toContain('mujweb.cz');
});
