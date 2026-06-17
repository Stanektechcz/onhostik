<?php

declare(strict_types=1);

use App\Console\Commands\CreateRenewalInvoicesCommand;
use App\Domains\Billing\Actions\IssueRenewalInvoiceAction;
use App\Domains\Billing\Actions\PayInvoiceWithCreditAction;
use App\Domains\Billing\Actions\ProcessMockPaymentAction;
use App\Domains\Billing\Events\InvoicePaid;
use App\Domains\Billing\Listeners\HandleInvoicePaid;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\ChangeServiceStateJob;
use App\Domains\Provisioning\Models\Service;
use Brick\Money\Money;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

/** Creates a paid order + Active service due in $daysUntilDue days. */
function activeServiceDueIn(int $daysUntilDue, ?\App\Models\User $user = null): Service
{
    $user = $user ?? customerUser();
    ['order' => $order] = placeOrder($user);

    $orderItem = $order->items()->with('pricingPlan.product')->first();

    return Service::create([
        'customer_id'         => $user->customer->id,
        'order_item_id'       => $orderItem->id,
        'product_id'          => $orderItem->pricingPlan->product_id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => ServiceStatus::Active,
        'label'               => 'renewal-test-' . $orderItem->id . '.onhost.cz',
        'next_due_date'       => now()->addDays($daysUntilDue)->toDateString(),
    ]);
}

it('issues a renewal proforma for a service due within the renewal window', function (): void {
    $days    = (int) config('billing.lifecycle.renewal_days_before', 7);
    $service = activeServiceDueIn($days);

    $this->artisan(CreateRenewalInvoicesCommand::class)->assertSuccessful();

    $invoice = Invoice::where('renewal_service_id', $service->id)->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->purpose)->toBe('renewal')
        ->and($invoice->due_date->toDateString())->toBe($service->next_due_date->toDateString())
        ->and($invoice->status->value)->toBe('sent');
});

it('does not create a duplicate renewal invoice when run twice', function (): void {
    $days    = (int) config('billing.lifecycle.renewal_days_before', 7);
    $service = activeServiceDueIn($days);

    $this->artisan(CreateRenewalInvoicesCommand::class)->assertSuccessful();
    $this->artisan(CreateRenewalInvoicesCommand::class)->assertSuccessful();

    expect(Invoice::where('renewal_service_id', $service->id)->count())->toBe(1);
});

it('does not issue a second renewal invoice via the action directly either', function (): void {
    $days    = (int) config('billing.lifecycle.renewal_days_before', 7);
    $service = activeServiceDueIn($days);

    $action = app(IssueRenewalInvoiceAction::class);
    $first  = $action->execute($service);
    $second = $action->execute($service->refresh());

    expect(Invoice::where('renewal_service_id', $service->id)->count())->toBe(1)
        ->and($second->id)->toBe($first->id);
});

it('ignores services whose due date is outside the renewal window', function (): void {
    activeServiceDueIn(20); // far in the future
    activeServiceDueIn(1);  // already inside the grace/overdue zone, not the renewal window

    $this->artisan(CreateRenewalInvoicesCommand::class)->assertSuccessful();

    expect(Invoice::where('purpose', 'renewal')->count())->toBe(0);
});

it('ignores suspended, terminated and pending services', function (): void {
    $days = (int) config('billing.lifecycle.renewal_days_before', 7);

    $user = customerUser();
    ['order' => $order] = placeOrder($user);
    $orderItem = $order->items()->with('pricingPlan.product')->first();

    foreach ([ServiceStatus::Suspended, ServiceStatus::Terminated, ServiceStatus::Pending, ServiceStatus::Failed] as $status) {
        Service::create([
            'customer_id'         => $user->customer->id,
            'order_item_id'       => $orderItem->id,
            'product_id'          => $orderItem->pricingPlan->product_id,
            'provisioning_driver' => ProvisioningDriver::AAPanel,
            'status'              => $status,
            'label'               => "renewal-skip-{$status->value}.onhost.cz",
            'next_due_date'       => now()->addDays($days)->toDateString(),
        ]);
    }

    $this->artisan(CreateRenewalInvoicesCommand::class)->assertSuccessful();

    expect(Invoice::where('purpose', 'renewal')->count())->toBe(0);
});

it('renewal invoice copies the original plan price, not a re-derived one', function (): void {
    $days    = (int) config('billing.lifecycle.renewal_days_before', 7);
    $service = activeServiceDueIn($days);
    $orderItem = $service->orderItem;

    $invoice = app(IssueRenewalInvoiceAction::class)->execute($service);

    expect($invoice->total->getMinorAmount()->toInt())
        ->toBe($orderItem->total->plus($orderItem->total->multipliedBy((float) $orderItem->vat_rate / 100, \Brick\Math\RoundingMode::HALF_UP))->getMinorAmount()->toInt());
});

// ──────────────────────────────────────────────────────────────────
// Renewal PAID → next_due_date extension (Phase 9)
// ──────────────────────────────────────────────────────────────────

it('extends next_due_date by the plan billing cycle when an on-time renewal is paid', function (): void {
    $days    = (int) config('billing.lifecycle.renewal_days_before', 7);
    $service = activeServiceDueIn($days);
    $oldDueDate  = $service->next_due_date;
    $cycleMonths = $service->orderItem->pricingPlan->billing_cycle->months();

    $invoice = app(IssueRenewalInvoiceAction::class)->execute($service);
    app(ProcessMockPaymentAction::class)->execute($invoice);

    expect($service->fresh()->next_due_date->toDateString())
        ->toBe($oldDueDate->copy()->addMonths($cycleMonths)->toDateString())
        ->and($invoice->fresh()->renewal_applied_at)->not->toBeNull();
});

it('extends from the payment date, not the stale due date, when a renewal is paid late', function (): void {
    $service     = activeServiceDueIn(-5); // 5 days overdue already
    $cycleMonths = $service->orderItem->pricingPlan->billing_cycle->months();

    $invoice = app(IssueRenewalInvoiceAction::class)->execute($service);
    app(ProcessMockPaymentAction::class)->execute($invoice);

    $expected = now()->startOfDay()->addMonths($cycleMonths);

    expect($service->fresh()->next_due_date->toDateString())->toBe($expected->toDateString());
});

it('does not extend next_due_date twice if InvoicePaid is replayed for the same renewal invoice', function (): void {
    $days    = (int) config('billing.lifecycle.renewal_days_before', 7);
    $service = activeServiceDueIn($days);

    $invoice = app(IssueRenewalInvoiceAction::class)->execute($service);
    $payment = app(ProcessMockPaymentAction::class)->execute($invoice);

    $afterFirstPayment = $service->fresh()->next_due_date;

    // Simulate a duplicate webhook / replayed event for the same invoice.
    app(HandleInvoicePaid::class)->handle(new InvoicePaid($invoice->fresh(), $payment));

    expect($service->fresh()->next_due_date->toDateString())->toBe($afterFirstPayment->toDateString());
});

it('extends next_due_date the same way when a renewal invoice is paid by credit', function (): void {
    $days    = (int) config('billing.lifecycle.renewal_days_before', 7);
    $service = activeServiceDueIn($days);
    $oldDueDate  = $service->next_due_date;
    $cycleMonths = $service->orderItem->pricingPlan->billing_cycle->months();

    app(CreditLedger::class)->deposit($service->customer, Money::ofMinor(50_000, 'CZK'), 'Test deposit');

    $invoice = app(IssueRenewalInvoiceAction::class)->execute($service);
    app(PayInvoiceWithCreditAction::class)->execute($invoice);

    expect($service->fresh()->next_due_date->toDateString())
        ->toBe($oldDueDate->copy()->addMonths($cycleMonths)->toDateString());
});

it('dispatches an unsuspend job when a suspended service\'s overdue renewal is paid', function (): void {
    Queue::fake();

    $user = customerUser();
    ['order' => $order] = placeOrder($user);
    $orderItem = $order->items()->with('pricingPlan.product')->first();

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'order_item_id'       => $orderItem->id,
        'product_id'          => $orderItem->pricingPlan->product_id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => ServiceStatus::Suspended,
        'label'               => 'renewal-unsuspend.onhost.cz',
        'next_due_date'       => now()->subDays(3)->toDateString(),
        'suspended_at'        => now()->subDay(),
        'suspension_reason'   => 'overdue_invoice',
    ]);

    $invoice = app(IssueRenewalInvoiceAction::class)->execute($service);
    app(ProcessMockPaymentAction::class)->execute($invoice);

    Queue::assertPushed(
        ChangeServiceStateJob::class,
        fn (ChangeServiceStateJob $job): bool => $job->serviceId === $service->id
            && $job->operation === 'unsuspend'
            && $job->reason === 'renewal_paid',
    );

    // next_due_date still extends even though the queued unsuspend hasn't run yet.
    expect($service->fresh()->next_due_date->isFuture())->toBeTrue();
});

it('actually unsuspends the service once the dispatched unsuspend job runs', function (): void {
    $user = customerUser();
    ['order' => $order] = placeOrder($user);
    $orderItem = $order->items()->with('pricingPlan.product')->first();

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'order_item_id'       => $orderItem->id,
        'product_id'          => $orderItem->pricingPlan->product_id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => ServiceStatus::Suspended,
        'label'               => 'renewal-unsuspend-real.onhost.cz',
        'next_due_date'       => now()->subDays(3)->toDateString(),
        'suspended_at'        => now()->subDay(),
        'suspension_reason'   => 'overdue_invoice',
    ]);

    $invoice = app(IssueRenewalInvoiceAction::class)->execute($service);
    app(ProcessMockPaymentAction::class)->execute($invoice); // queue runs sync in tests by default

    expect($service->fresh()->status)->toBe(ServiceStatus::Active)
        ->and($service->fresh()->suspended_at)->toBeNull()
        ->and($service->fresh()->suspension_reason)->toBeNull();
});
