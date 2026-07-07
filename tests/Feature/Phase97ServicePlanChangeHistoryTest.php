<?php

declare(strict_types=1);

use App\Domains\Provisioning\Models\Service;
use App\Models\ServicePlanChange;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Migration / model ─────────────────────────────────────────────────────────

it('ServicePlanChange model can be created', function (): void {
    $user     = customerUser();
    $admin    = adminUser();
    $result   = placeOrder($user);
    $order    = $result['order'];

    $service = Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'order_item_id' => $order->items->first()->id,
    ]);

    $toPlan = $order->items->first()->pricingPlan;

    $change = ServicePlanChange::create([
        'service_id'         => $service->id,
        'from_plan_id'       => null,
        'to_plan_id'         => $toPlan->id,
        'changed_by_user_id' => $admin->id,
        'reason'             => 'admin_override',
        'changed_at'         => now(),
    ]);

    expect($change->id)->toBeInt()
        ->and($change->reason)->toBe('admin_override')
        ->and($change->reasonLabel())->toBe('Admin');
});

it('Service has planChanges relation', function (): void {
    $user   = customerUser();
    $result = placeOrder($user);
    $order  = $result['order'];
    $plan   = $order->items->first()->pricingPlan;

    $service = Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'order_item_id' => $order->items->first()->id,
    ]);

    ServicePlanChange::create([
        'service_id'  => $service->id,
        'to_plan_id'  => $plan->id,
        'reason'      => 'customer_request',
        'changed_at'  => now(),
    ]);

    expect($service->planChanges)->toHaveCount(1)
        ->and($service->planChanges->first()->reasonLabel())->toBe('Zákazník');
});

// ── applyChangePlan records history ───────────────────────────────────────────

it('applyChangePlan records ServicePlanChange entry', function (): void {
    $user      = customerUser();
    $result    = placeOrder($user);
    $order     = $result['order'];
    $orderItem = $order->items->first();
    $plan      = $orderItem->pricingPlan;

    // Service must share the same product_id as the plan for the check to pass
    $service = Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'order_item_id' => $orderItem->id,
        'product_id'    => $plan->product_id,
    ]);

    $this->actingAs($user)->post(route('panel.services.apply-change-plan', $service), [
        'plan_id' => $plan->id,
    ]);

    $change = ServicePlanChange::where('service_id', $service->id)->first();

    expect($change)->not->toBeNull()
        ->and($change->to_plan_id)->toBe($plan->id)
        ->and($change->changed_by_user_id)->toBe($user->id)
        ->and($change->reason)->toBe('customer_request');
});

// ── Admin service show contains plan history ──────────────────────────────────

it('admin service show page renders plan change history', function (): void {
    $admin  = adminUser();
    $user   = customerUser();
    $result = placeOrder($user);
    $order  = $result['order'];
    $plan   = $order->items->first()->pricingPlan;

    $service = Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'order_item_id' => $order->items->first()->id,
    ]);

    ServicePlanChange::create([
        'service_id'         => $service->id,
        'to_plan_id'         => $plan->id,
        'changed_by_user_id' => $admin->id,
        'reason'             => 'admin_override',
        'changed_at'         => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.services.show', $service))
        ->assertOk()
        ->assertSee('Historie změn plánu')
        ->assertSee($plan->name);
});

it('admin service show page shows no history section when there are no plan changes', function (): void {
    $admin  = adminUser();
    $user   = customerUser();
    $result = placeOrder($user);
    $order  = $result['order'];

    $service = Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'order_item_id' => $order->items->first()->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.services.show', $service))
        ->assertOk()
        ->assertDontSee('Historie změn plánu');
});

// ── reasonLabel ───────────────────────────────────────────────────────────────

it('ServicePlanChange reasonLabel returns correct labels', function (): void {
    $user   = customerUser();
    $result = placeOrder($user);
    $plan   = $result['order']->items->first()->pricingPlan;

    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $makeChange = fn (string $reason) => ServicePlanChange::create([
        'service_id'  => $service->id,
        'to_plan_id'  => $plan->id,
        'reason'      => $reason,
        'changed_at'  => now(),
    ]);

    expect($makeChange('customer_request')->reasonLabel())->toBe('Zákazník')
        ->and($makeChange('admin_override')->reasonLabel())->toBe('Admin')
        ->and($makeChange('auto')->reasonLabel())->toBe('Automaticky');
});
