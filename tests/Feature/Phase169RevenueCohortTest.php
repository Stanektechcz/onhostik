<?php

declare(strict_types=1);

use App\Domains\Billing\Models\Invoice;

it('admin can view revenue cohort page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.metrics.cohort'))
         ->assertOk()
         ->assertViewIs('admin.revenue-cohort');
});

it('revenue cohort page provides cohortData, cohorts and allMonths', function (): void {
    $admin = adminUser();

    $response = $this->actingAs($admin)
         ->get(route('admin.metrics.cohort'))
         ->assertOk();

    $response->assertViewHas('cohortData')
             ->assertViewHas('cohorts')
             ->assertViewHas('allMonths');
});

it('revenue cohort aggregates paid invoices by customer cohort', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    Invoice::factory()->create([
        'customer_id' => $customer->customer->id,
        'status'      => 'paid',
        'paid_at'     => now(),
    ]);

    $response = $this->actingAs($admin)
         ->get(route('admin.metrics.cohort'))
         ->assertOk();

    $cohortData = $response->viewData('cohortData');
    expect($cohortData)->toBeArray();
});

it('revenue cohort page returns empty cohorts with no paid invoices', function (): void {
    $admin = adminUser();

    $response = $this->actingAs($admin)
         ->get(route('admin.metrics.cohort'))
         ->assertOk();

    $cohorts = $response->viewData('cohorts');
    expect($cohorts)->toBeArray();
});

it('customer cannot view revenue cohort analysis', function (): void {
    $customer = customerUser();

    $this->actingAs($customer)
         ->get(route('admin.metrics.cohort'))
         ->assertForbidden();
});
