<?php

declare(strict_types=1);

it('admin can view email suppression list', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.email-suppression.index'))
         ->assertOk()
         ->assertViewIs('admin.email-suppression.index');
});

it('admin can suppress customer email', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    $this->actingAs($admin)
         ->post(route('admin.email-suppression.suppress', $customer->customer))
         ->assertRedirect();

    $customer->refresh();
    expect($customer->email_suppressed_at)->not->toBeNull();
});

it('admin can unsuppress customer email', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    $customer->update(['email_suppressed_at' => now()]);

    $this->actingAs($admin)
         ->delete(route('admin.email-suppression.unsuppress', $customer->customer))
         ->assertRedirect();

    $customer->refresh();
    expect($customer->email_suppressed_at)->toBeNull();
});

it('suppression list shows only suppressed users', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    $customer->update(['email_suppressed_at' => now()]);

    $response = $this->actingAs($admin)
         ->get(route('admin.email-suppression.index'))
         ->assertOk();

    $suppressed = $response->viewData('suppressed');
    expect($suppressed->total())->toBeGreaterThanOrEqual(1);
});

it('customer cannot access email suppression list', function (): void {
    $customer = customerUser();

    $this->actingAs($customer)
         ->get(route('admin.email-suppression.index'))
         ->assertForbidden();
});
