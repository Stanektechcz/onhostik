<?php

declare(strict_types=1);

use App\Domains\Billing\Models\Invoice;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Support\Models\SupportTicket;

it('admin can view customer onboarding checklist', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    $this->actingAs($admin)
         ->get(route('admin.customers.onboarding', $customer->customer))
         ->assertOk()
         ->assertViewIs('admin.customer-onboarding');
});

it('onboarding view has checklist and progress data', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    $response = $this->actingAs($admin)
         ->get(route('admin.customers.onboarding', $customer->customer))
         ->assertOk();

    $response->assertViewHas('checklist')
             ->assertViewHas('progress');
});

it('progress is 0 when no onboarding steps are completed', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    $customer->customer->update(['company_name' => null]);

    $response = $this->actingAs($admin)
         ->get(route('admin.customers.onboarding', $customer->customer))
         ->assertOk();

    $progress = $response->viewData('progress');
    expect($progress)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
});

it('active service increases onboarding progress', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    Service::factory()->create([
        'customer_id' => $customer->customer->id,
        'status'      => 'active',
    ]);

    $response = $this->actingAs($admin)
         ->get(route('admin.customers.onboarding', $customer->customer))
         ->assertOk();

    $checklist = $response->viewData('checklist');
    $serviceItem = collect($checklist)->firstWhere('label', 'Aktivní služba');
    expect($serviceItem['done'])->toBeTrue();
});

it('customer cannot view onboarding checklist', function (): void {
    $customer = customerUser();

    $this->actingAs($customer)
         ->get(route('admin.customers.onboarding', $customer->customer))
         ->assertForbidden();
});
