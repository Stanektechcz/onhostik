<?php

declare(strict_types=1);

use App\Console\Commands\MarkOverdueInvoicesCommand;
use App\Console\Commands\SuspendOverdueServicesCommand;
use App\Domains\Billing\Actions\ProcessMockPaymentAction;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
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

it('suspends active services linked to overdue invoices past the grace period', function (): void {
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

    expect($service->fresh()->status)->toBe(ServiceStatus::Suspended);
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
});
