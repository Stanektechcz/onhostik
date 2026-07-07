<?php

use App\Domains\Products\Models\Product;
use App\Domains\Provisioning\Models\Service;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('customer can check service dependency', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);
    $plan    = Product::factory()->create();

    $url = route('panel.services.dependency.check', $service) . '?target_plan_id=' . $plan->id;

    $this->actingAs($user)
        ->get($url)
        ->assertOk()
        ->assertJsonStructure(['safe', 'warnings']);
});

it('dependency check returns safe for plan with no resource limits', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);
    $plan    = Product::factory()->create(['resources' => null]);

    $url = route('panel.services.dependency.check', $service) . '?target_plan_id=' . $plan->id;

    $this->actingAs($user)
        ->get($url)
        ->assertOk()
        ->assertJsonPath('safe', true);
});

it('dependency check warns when disk usage exceeds plan limit', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'disk_usage_gb' => 20.0,
    ]);
    $plan = Product::factory()->create(['resources' => ['disk_gb' => 10]]);

    $url = route('panel.services.dependency.check', $service) . '?target_plan_id=' . $plan->id;

    $this->actingAs($user)
        ->get($url)
        ->assertOk()
        ->assertJsonPath('safe', false);
});

it('customer cannot check dependency on another customers service', function (): void {
    $user    = customerUser();
    $other   = \App\Domains\Customer\Models\Customer::factory()->create();
    $service = Service::factory()->create(['customer_id' => $other->id]);
    $plan    = Product::factory()->create();

    $url = route('panel.services.dependency.check', $service) . '?target_plan_id=' . $plan->id;

    $this->actingAs($user)
        ->get($url)
        ->assertForbidden();
});

it('target_plan_id must exist', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $url = route('panel.services.dependency.check', $service) . '?target_plan_id=99999';

    $this->actingAs($user)
        ->get($url)
        ->assertSessionHasErrors('target_plan_id');
});
