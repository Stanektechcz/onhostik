<?php

use App\Domains\Products\Models\PricingPlan;
use App\Domains\Provisioning\Models\Service;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('customer can calculate prorate for a plan change', function (): void {
    $user    = customerUser();
    $plan    = PricingPlan::factory()->create(['price_czk' => 10000]);
    $service = Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'next_due_date' => now()->addDays(15),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('panel.services.prorate.calculate', $service) . '?new_plan_id=' . $plan->id)
        ->assertOk();

    expect($response->json())->toHaveKey('new_monthly');
    expect($response->json())->toHaveKey('prorated_credit');
    expect($response->json())->toHaveKey('amount_due_now');
    expect($response->json())->toHaveKey('currency');
});

it('customer cannot access prorate for another customers service', function (): void {
    $user    = customerUser();
    $plan    = PricingPlan::factory()->create();
    $service = Service::factory()->create();

    $this->actingAs($user)
        ->getJson(route('panel.services.prorate.calculate', $service) . '?new_plan_id=' . $plan->id)
        ->assertForbidden();
});

it('prorate response includes correct currency', function (): void {
    $user    = customerUser();
    $plan    = PricingPlan::factory()->create(['price_czk' => 50000]);
    $service = Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'next_due_date' => now()->addDays(20),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('panel.services.prorate.calculate', $service) . '?new_plan_id=' . $plan->id)
        ->assertOk();

    expect($response->json('currency'))->toBe('CZK');
});

it('non-existent plan_id is rejected', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($user)
        ->getJson(route('panel.services.prorate.calculate', $service) . '?new_plan_id=99999')
        ->assertUnprocessable();
});

it('prorated credit is zero when service renewal is in the past', function (): void {
    $user    = customerUser();
    $plan    = PricingPlan::factory()->create(['price_czk' => 10000]);
    $service = Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'next_due_date' => now()->subDays(5),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('panel.services.prorate.calculate', $service) . '?new_plan_id=' . $plan->id)
        ->assertOk();

    expect($response->json('prorated_credit'))->toBe(0);
});
