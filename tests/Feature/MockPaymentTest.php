<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Billing\Enums\PaymentMethod;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\Payment;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\ProvisionHostingServiceJob;
use App\Domains\Provisioning\Jobs\RegisterDomainJob;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

it('marks invoice and order paid and queues provisioning on mock payment', function (): void {
    Queue::fake();

    $user = customerUser();
    ['order' => $order, 'invoice' => $invoice] = placeOrder($user, [
        'domain'          => 'platici-zakaznik.cz',
        'register_domain' => true,
    ]);

    $this->actingAs($user)
        ->post(route('panel.billing.invoices.pay-mock', $invoice))
        ->assertRedirect()
        ->assertSessionHas('status');

    $invoice->refresh();
    $order->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->paid_at)->not->toBeNull()
        ->and($order->status)->toBe(OrderStatus::Processing)
        ->and($order->paid_at)->not->toBeNull();

    $payment = Payment::firstOrFail();
    expect($payment->status)->toBe(PaymentStatus::Completed)
        ->and($payment->method)->toBe(PaymentMethod::Comgate)
        ->and($payment->gateway_transaction_id)->toBe('MOCK-' . $invoice->uuid)
        ->and($payment->amount->isEqualTo($invoice->total))->toBeTrue();

    // Service shell exists; remote work is queued, not executed inline.
    $service = Service::firstOrFail();
    expect($service->status)->toBe(ServiceStatus::Pending);

    Queue::assertPushed(ProvisionHostingServiceJob::class, 1);
    Queue::assertPushed(RegisterDomainJob::class, 1);

    expect(Activity::where('log_name', 'payment')->where('description', 'payment.completed')->exists())->toBeTrue()
        ->and(Activity::where('log_name', 'order')->where('description', 'order.paid')->exists())->toBeTrue();
});

it('does not double-process a duplicate mock payment', function (): void {
    Queue::fake();

    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user, ['domain' => 'dvojklik.cz', 'register_domain' => true]);

    $this->actingAs($user)->post(route('panel.billing.invoices.pay-mock', $invoice));
    $this->actingAs($user)->post(route('panel.billing.invoices.pay-mock', $invoice));

    expect(Payment::count())->toBe(1)
        ->and(Payment::firstOrFail()->status)->toBe(PaymentStatus::Completed)
        ->and(Activity::where('log_name', 'order')->where('description', 'order.paid')->count())->toBe(1);

    // Provisioning was queued exactly once — the duplicate was a no-op.
    Queue::assertPushed(ProvisionHostingServiceJob::class, 1);
    Queue::assertPushed(RegisterDomainJob::class, 1);
});

it('records a simulated failed payment without any state transition', function (): void {
    Queue::fake();

    $user = customerUser();
    ['order' => $order, 'invoice' => $invoice] = placeOrder($user);

    $this->actingAs($user)
        ->post(route('panel.billing.invoices.pay-mock', $invoice), ['outcome' => 'fail'])
        ->assertRedirect()
        ->assertSessionHas('payment_failed');

    $invoice->refresh();
    $order->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Sent)
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and(Payment::firstOrFail()->status)->toBe(PaymentStatus::Failed);

    Queue::assertNothingPushed();

    expect(Activity::where('log_name', 'payment')->where('description', 'payment.failed')->exists())->toBeTrue();
});

it('lets the customer retry successfully after a failed attempt', function (): void {
    Queue::fake();

    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    $this->actingAs($user)->post(route('panel.billing.invoices.pay-mock', $invoice), ['outcome' => 'fail']);
    $this->actingAs($user)->post(route('panel.billing.invoices.pay-mock', $invoice));

    // The retry reuses the same deterministic payment attempt row.
    expect(Payment::count())->toBe(1)
        ->and(Payment::firstOrFail()->status)->toBe(PaymentStatus::Completed)
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Paid);
});
