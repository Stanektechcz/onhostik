<?php

declare(strict_types=1);

use App\Domains\Products\Models\PricingPlan;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Admin operations: create a service for a customer, edit the customer's
 * profile, and toggle their login — on top of the existing add-credit /
 * approve-payment actions.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

it('renders the admin create-service form', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.services.create'))
        ->assertOk()
        ->assertSee('Vytvořit službu pro zákazníka');
});

it('lets an admin create a service for a customer without an order', function (): void {
    $customer = customerUser()->customer;
    $plan     = PricingPlan::where('is_active', true)->firstOrFail();

    $this->actingAs(adminUser())
        ->post(route('admin.services.store'), [
            'customer_id'     => $customer->id,
            'pricing_plan_id' => $plan->id,
            'label'           => 'admin-web.cz',
            'status'          => 'active',
        ])
        ->assertRedirect();

    $service = Service::where('customer_id', $customer->id)->latest('id')->firstOrFail();

    expect($service->order_item_id)->toBeNull()
        ->and($service->label)->toBe('admin-web.cz')
        ->and($service->status)->toBe(ServiceStatus::Active);
});

it('lets an admin edit a customer profile and the contact name', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $this->actingAs(adminUser())
        ->patch(route('admin.customers.update', $customer), [
            'name'         => 'Nový Kontakt',
            'email'        => 'novy@example.com',
            'phone'        => '+420777123456',
            'company_name' => 'Firma s.r.o.',
            'country_code' => 'sk',
        ])
        ->assertRedirect();

    $customer->refresh();
    expect($customer->email)->toBe('novy@example.com')
        ->and($customer->company_name)->toBe('Firma s.r.o.')
        ->and($customer->country_code)->toBe('SK')
        ->and($customer->user->fresh()->name)->toBe('Nový Kontakt');
});

it('lets an admin toggle the customer login on and off', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    expect($user->is_active)->toBeTrue();

    $this->actingAs(adminUser())
        ->post(route('admin.customers.toggle-active', $customer))
        ->assertRedirect();

    expect($user->fresh()->is_active)->toBeFalse();
});

it('forbids a customer from creating services', function (): void {
    $this->actingAs(customerUser())
        ->get(route('admin.services.create'))
        ->assertForbidden();
});
