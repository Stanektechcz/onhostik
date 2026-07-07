<?php

declare(strict_types=1);

use App\Domains\Api\Models\ApiUsageLog;

it('customer can view api usage dashboard', function (): void {
    $customer = customerUser();

    $this->actingAs($customer)
         ->get(route('panel.api.usage-dashboard'))
         ->assertOk()
         ->assertViewIs('panel.api.usage-dashboard');
});

it('api usage dashboard shows correct view data', function (): void {
    $customer = customerUser();

    $response = $this->actingAs($customer)
         ->get(route('panel.api.usage-dashboard'))
         ->assertOk();

    $response->assertViewHas('totalRequests')
             ->assertViewHas('days');
});

it('api usage dashboard shows only own user requests', function (): void {
    $customer1 = customerUser();
    $customer2 = customerUser();

    ApiUsageLog::create([
        'user_id'     => $customer1->id,
        'endpoint'    => '/api/test',
        'method'      => 'GET',
        'status_code' => 200,
    ]);

    $response = $this->actingAs($customer2)
         ->get(route('panel.api.usage-dashboard'))
         ->assertOk();

    $totalRequests = $response->viewData('totalRequests');
    expect($totalRequests)->toBe(0);
});

it('api usage dashboard counts total requests for user', function (): void {
    $customer = customerUser();

    ApiUsageLog::create(['user_id' => $customer->id, 'endpoint' => '/api/v1/a', 'method' => 'GET', 'status_code' => 200]);
    ApiUsageLog::create(['user_id' => $customer->id, 'endpoint' => '/api/v1/b', 'method' => 'POST', 'status_code' => 201]);

    $response = $this->actingAs($customer)
         ->get(route('panel.api.usage-dashboard'))
         ->assertOk();

    $totalRequests = $response->viewData('totalRequests');
    expect($totalRequests)->toBe(2);
});

it('unauthenticated user cannot view api usage dashboard', function (): void {
    $this->get(route('panel.api.usage-dashboard'))
         ->assertRedirect('/login');
});
