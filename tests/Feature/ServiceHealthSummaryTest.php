<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\ServiceHealthSummary;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Phase O (O189): aggregated one-glance service health on the dashboard.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

function healthMakeService(int $customerId, ServiceStatus $status): Service
{
    return Service::factory()->create([
        'customer_id' => $customerId,
        'status'      => $status,
    ]);
}

it('reports "none" when the customer has no services', function (): void {
    $summary = app(ServiceHealthSummary::class)->forCustomer(customerUser()->customer);

    expect($summary['verdict'])->toBe('none')
        ->and($summary['total'])->toBe(0);
});

it('reports "ok" when every service is active', function (): void {
    $customer = customerUser()->customer;
    healthMakeService($customer->id, ServiceStatus::Active);
    healthMakeService($customer->id, ServiceStatus::Active);

    $summary = app(ServiceHealthSummary::class)->forCustomer($customer);

    expect($summary['verdict'])->toBe('ok')
        ->and($summary['active'])->toBe(2)
        ->and($summary['total'])->toBe(2);
});

it('reports "attention" for a suspended service', function (): void {
    $customer = customerUser()->customer;
    healthMakeService($customer->id, ServiceStatus::Active);
    healthMakeService($customer->id, ServiceStatus::Suspended);

    // Suspended usually means an unpaid invoice — worth attention, not alarm.
    expect(app(ServiceHealthSummary::class)->forCustomer($customer)['verdict'])->toBe('attention');
});

it('reports "critical" for a failed service', function (): void {
    $customer = customerUser()->customer;
    healthMakeService($customer->id, ServiceStatus::Active);
    healthMakeService($customer->id, ServiceStatus::Failed);

    // A failed provision or an outage outranks a mere suspension.
    expect(app(ServiceHealthSummary::class)->forCustomer($customer)['verdict'])->toBe('critical');
});

it('excludes terminated services from the total', function (): void {
    $customer = customerUser()->customer;
    healthMakeService($customer->id, ServiceStatus::Active);
    healthMakeService($customer->id, ServiceStatus::Terminated);

    $summary = app(ServiceHealthSummary::class)->forCustomer($customer);

    // A cancelled service is gone; it must not inflate "how many do I have".
    expect($summary['total'])->toBe(1)
        ->and($summary['active'])->toBe(1);
});

it('does not leak one customer\'s services into another\'s summary', function (): void {
    $mine   = customerUser()->customer;
    $theirs = customerUser()->customer;

    healthMakeService($theirs->id, ServiceStatus::Failed);
    healthMakeService($mine->id, ServiceStatus::Active);

    expect(app(ServiceHealthSummary::class)->forCustomer($mine)['verdict'])->toBe('ok');
});

it('surfaces the health strip on the dashboard', function (): void {
    $user = customerUser();
    healthMakeService($user->customer->id, ServiceStatus::Active);

    $this->actingAs($user)
        ->get(route('panel.dashboard'))
        ->assertOk()
        ->assertSee('Vše v pořádku');
});
