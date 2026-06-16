<?php

declare(strict_types=1);

use App\Console\Commands\MarkOverdueInvoicesCommand;
use App\Console\Commands\SuspendOverdueServicesCommand;
use App\Domains\Billing\Actions\ProcessMockPaymentAction;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\ChangeServiceStateJob;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\DriverResolver;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
    Queue::fake();
});

it('marks sent invoices as overdue when past their due date', function (): void {
    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    $invoice->update(['due_date' => now()->subDay()->toDateString()]);

    $this->artisan(MarkOverdueInvoicesCommand::class)->assertSuccessful();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Overdue);
});

it('does not mark sent invoices as overdue when still within due date', function (): void {
    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    $invoice->update(['due_date' => now()->addDay()->toDateString()]);

    $this->artisan(MarkOverdueInvoicesCommand::class)->assertSuccessful();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

it('does not mark paid invoices as overdue', function (): void {
    $user = customerUser([
        'company_name' => 'Paid Co s.r.o.',
        'country_code' => 'CZ',
    ]);

    $user->customer->addresses()->create([
        'type'         => 'billing',
        'street'       => 'Uhrazená 1',
        'city'         => 'Praha',
        'zip'          => '11000',
        'country_code' => 'CZ',
        'is_primary'   => true,
    ]);

    ['invoice' => $invoice] = placeOrder($user);
    app(ProcessMockPaymentAction::class)->execute($invoice);

    $invoice->update(['due_date' => now()->subDays(10)->toDateString()]);

    $this->artisan(MarkOverdueInvoicesCommand::class)->assertSuccessful();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('dispatches a real suspend job for active services past the grace period', function (): void {
    $user = customerUser();
    ['invoice' => $invoice, 'order' => $order] = placeOrder($user);

    $graceDays = (int) config('billing.lifecycle.suspend_after', 7);
    $invoice->update([
        'status'   => InvoiceStatus::Overdue,
        'due_date' => now()->subDays($graceDays + 1)->toDateString(),
    ]);

    $orderItem  = $order->items()->with('pricingPlan')->first();
    $productId  = $orderItem->pricingPlan->product_id;

    $service = Service::create([
        'customer_id'        => $user->customer->id,
        'order_item_id'      => $orderItem->id,
        'product_id'         => $productId,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'             => ServiceStatus::Active,
        'label'              => 'test-service.onhost.cz',
    ]);

    $this->artisan(SuspendOverdueServicesCommand::class)->assertSuccessful();

    // The command itself never flips the DB status — it only dispatches.
    expect($service->fresh()->status)->toBe(ServiceStatus::Active);

    Queue::assertPushed(
        ChangeServiceStateJob::class,
        fn (ChangeServiceStateJob $job): bool => $job->serviceId === $service->id
            && $job->operation === 'suspend'
            && $job->reason === 'overdue_invoice',
    );
});

it('actually suspends the service once the dispatched job runs', function (): void {
    $user = customerUser();
    ['invoice' => $invoice, 'order' => $order] = placeOrder($user);

    $graceDays = (int) config('billing.lifecycle.suspend_after', 7);
    $invoice->update([
        'status'   => InvoiceStatus::Overdue,
        'due_date' => now()->subDays($graceDays + 1)->toDateString(),
    ]);

    $orderItem = $order->items()->with('pricingPlan')->first();
    $service   = Service::create([
        'customer_id'         => $user->customer->id,
        'order_item_id'       => $orderItem->id,
        'product_id'          => $orderItem->pricingPlan->product_id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => ServiceStatus::Active,
        'label'               => 'test-service-runjob.onhost.cz',
    ]);

    $this->artisan(SuspendOverdueServicesCommand::class)->assertSuccessful();

    // Simulate the queue worker actually processing the dispatched job —
    // this is what makes the suspension real (calls driver->suspend()).
    (new ChangeServiceStateJob($service->id, 'suspend', 'overdue_invoice'))
        ->handle(app(DriverResolver::class));

    expect($service->fresh()->status)->toBe(ServiceStatus::Suspended)
        ->and($service->fresh()->suspension_reason)->toBe('overdue_invoice');
});

it('does not suspend services within the grace period', function (): void {
    $user = customerUser();
    ['invoice' => $invoice, 'order' => $order] = placeOrder($user);

    $invoice->update([
        'status'   => InvoiceStatus::Overdue,
        'due_date' => now()->subDays(2)->toDateString(),
    ]);

    $orderItem  = $order->items()->with('pricingPlan')->first();
    $productId  = $orderItem->pricingPlan->product_id;

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'order_item_id'       => $orderItem->id,
        'product_id'          => $productId,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => ServiceStatus::Active,
        'label'               => 'test-service-grace.onhost.cz',
    ]);

    $this->artisan(SuspendOverdueServicesCommand::class)->assertSuccessful();

    expect($service->fresh()->status)->toBe(ServiceStatus::Active);
    Queue::assertNotPushed(ChangeServiceStateJob::class);
});

it('does not suspend services on a fully paid invoice', function (): void {
    $user = customerUser([
        'company_name' => 'Paid Co s.r.o.',
        'country_code' => 'CZ',
    ]);

    $user->customer->addresses()->create([
        'type'         => 'billing',
        'street'       => 'Uhrazená 1',
        'city'         => 'Praha',
        'zip'          => '11000',
        'country_code' => 'CZ',
        'is_primary'   => true,
    ]);

    ['invoice' => $invoice, 'order' => $order] = placeOrder($user);
    app(ProcessMockPaymentAction::class)->execute($invoice);

    // Invoice is Paid, not Overdue — the command's query should never match it.
    $orderItem = $order->items()->with('pricingPlan')->first();
    $service   = Service::create([
        'customer_id'         => $user->customer->id,
        'order_item_id'       => $orderItem->id,
        'product_id'          => $orderItem->pricingPlan->product_id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => ServiceStatus::Active,
        'label'               => 'test-service-paid.onhost.cz',
    ]);

    $this->artisan(SuspendOverdueServicesCommand::class)->assertSuccessful();

    expect($service->fresh()->status)->toBe(ServiceStatus::Active);
    Queue::assertNotPushed(ChangeServiceStateJob::class);
});
