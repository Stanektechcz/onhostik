<?php

declare(strict_types=1);

use App\Models\CustomerOnboardingStep;
use App\Domains\Customer\Models\Customer;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can list onboarding steps', function () {
    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.customer-onboarding-steps.index'))
        ->assertOk()
        ->assertViewIs('admin.customer-onboarding-steps.index');
});

it('admin can add an onboarding step', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create();
    $this->actingAs($admin)->post(route('admin.customer-onboarding-steps.store'), [
        'customer_id' => $customer->id,
        'step'        => 'verify_email',
        'is_required' => true,
    ])->assertRedirect();
    $this->assertDatabaseHas('customer_onboarding_steps', ['customer_id' => $customer->id, 'step' => 'verify_email']);
});

it('admin can toggle onboarding step completion', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create();
    $step = CustomerOnboardingStep::create([
        'customer_id' => $customer->id,
        'step'        => 'setup_payment',
        'is_required' => true,
    ]);
    $this->actingAs($admin)
        ->patch(route('admin.customer-onboarding-steps.update', $step))
        ->assertRedirect();
    $this->assertNotNull($step->fresh()->completed_at);
});

it('duplicate step is upserted not duplicated', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create();
    CustomerOnboardingStep::create([
        'customer_id' => $customer->id,
        'step'        => 'verify_domain',
        'is_required' => false,
    ]);
    $this->actingAs($admin)->post(route('admin.customer-onboarding-steps.store'), [
        'customer_id' => $customer->id,
        'step'        => 'verify_domain',
        'is_required' => true,
    ])->assertRedirect();
    $this->assertDatabaseCount('customer_onboarding_steps', 1);
});

it('panel customer can view own onboarding steps', function () {
    $user = customerUser();
    CustomerOnboardingStep::create([
        'customer_id' => $user->customer->id,
        'step'        => 'profile_complete',
        'is_required' => true,
    ]);
    $this->actingAs($user)
        ->get(route('panel.customer-onboarding-steps.index'))
        ->assertOk()
        ->assertViewIs('panel.customer-onboarding-steps.index');
});
