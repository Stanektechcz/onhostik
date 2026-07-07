<?php

declare(strict_types=1);

use App\Domains\Products\Enums\BillingCycle;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Products\Models\Product;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Models\ServicePlanChange;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeServiceWithTwoPlans(): array
{
    $user    = customerUser();
    $product = Product::factory()->create(['name' => 'Hosting', 'is_active' => true]);

    $planA = PricingPlan::factory()->create([
        'product_id'    => $product->id,
        'name'          => 'Basic',
        'billing_cycle' => BillingCycle::Monthly,
        'price_czk'     => 50000,    // 500 CZK in minor units
        'is_active'     => true,
    ]);

    $planB = PricingPlan::factory()->create([
        'product_id'    => $product->id,
        'name'          => 'Pro',
        'billing_cycle' => BillingCycle::Monthly,
        'price_czk'     => 100000,   // 1000 CZK
        'is_active'     => true,
    ]);

    $service = \App\Domains\Provisioning\Models\Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'product_id'    => $product->id,
        'status'        => ServiceStatus::Active,
        'label'         => 'Test Service',
        'next_due_date' => now()->addDays(15),
    ]);

    return compact('user', 'service', 'planA', 'planB', 'product');
}

// ── Preview endpoint ──────────────────────────────────────────────────────────

it('changePlanPreview returns prorated breakdown', function (): void {
    ['user' => $user, 'service' => $service, 'planB' => $planB] = makeServiceWithTwoPlans();

    $this->actingAs($user)
         ->getJson(route('panel.services.change-plan-preview', $service) . '?plan_id=' . $planB->id)
         ->assertOk()
         ->assertJsonStructure([
             'current_plan_price',
             'new_plan_price',
             'prorated_days',
             'prorated_amount',
             'currency',
             'is_upgrade',
         ]);
});

it('preview marks upgrade correctly when new price is higher', function (): void {
    ['user' => $user, 'service' => $service, 'planB' => $planB] = makeServiceWithTwoPlans();

    $data = $this->actingAs($user)
         ->getJson(route('panel.services.change-plan-preview', $service) . '?plan_id=' . $planB->id)
         ->assertOk()
         ->json();

    expect($data['is_upgrade'])->toBeTrue()
        ->and($data['new_plan_price'])->toBeGreaterThan($data['current_plan_price']);
});

it('preview returns is_upgrade as boolean', function (): void {
    ['user' => $user, 'service' => $service, 'planA' => $planA] = makeServiceWithTwoPlans();

    $data = $this->actingAs($user)
         ->getJson(route('panel.services.change-plan-preview', $service) . '?plan_id=' . $planA->id)
         ->assertOk()
         ->json();

    // is_upgrade must be a boolean
    expect($data['is_upgrade'])->toBeBool();
});

it('preview includes prorated_days matching remaining days to next_due_date', function (): void {
    ['user' => $user, 'service' => $service, 'planB' => $planB] = makeServiceWithTwoPlans();

    $data = $this->actingAs($user)
         ->getJson(route('panel.services.change-plan-preview', $service) . '?plan_id=' . $planB->id)
         ->assertOk()
         ->json();

    // Service has next_due_date = now + 15 days
    expect($data['prorated_days'])->toBeGreaterThanOrEqual(14)
        ->and($data['prorated_days'])->toBeLessThanOrEqual(16);
});

it('preview rejects plan_id from a different product', function (): void {
    ['user' => $user, 'service' => $service] = makeServiceWithTwoPlans();

    $otherProduct = Product::factory()->create(['is_active' => true]);
    $otherPlan    = PricingPlan::factory()->create([
        'product_id' => $otherProduct->id,
        'is_active'  => true,
        'price_czk'  => 200000,
    ]);

    $this->actingAs($user)
         ->getJson(route('panel.services.change-plan-preview', $service) . '?plan_id=' . $otherPlan->id)
         ->assertStatus(422);
});

// ── Change-plan page renders modal scaffold ───────────────────────────────────

it('change-plan page renders plan-change-btn buttons (not direct submit)', function (): void {
    ['user' => $user, 'service' => $service] = makeServiceWithTwoPlans();

    $this->actingAs($user)
         ->get(route('panel.services.change-plan', $service))
         ->assertOk()
         ->assertSee('plan-change-btn')
         ->assertSee('planChangeModal');
});

it('change-plan page renders confirmation modal', function (): void {
    ['user' => $user, 'service' => $service] = makeServiceWithTwoPlans();

    $this->actingAs($user)
         ->get(route('panel.services.change-plan', $service))
         ->assertOk()
         ->assertSee('Potvrdit změnu');
});

// ── Apply (existing tested, just verify it records ServicePlanChange) ─────────

it('applyChangePlan records a ServicePlanChange entry', function (): void {
    ['user' => $user, 'service' => $service, 'planB' => $planB] = makeServiceWithTwoPlans();

    $this->actingAs($user)
         ->post(route('panel.services.apply-change-plan', $service), [
             'plan_id' => $planB->id,
         ])
         ->assertRedirect();

    $change = ServicePlanChange::query()
        ->where('service_id', $service->id)
        ->where('to_plan_id', $planB->id)
        ->first();

    expect($change)->not->toBeNull()
        ->and($change->reason)->toBe('customer_request');
});
