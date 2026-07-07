<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view domain transfer statistics', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.domain-transfer-statistics.index'))
        ->assertOk()
        ->assertViewHas('statusStats');
});

it('statistics shows correct total count', function (): void {
    $admin    = adminUser();
    $customer = \App\Domains\Customer\Models\Customer::factory()->create();

    \App\Models\DomainTransferRequest::create([
        'customer_id' => $customer->id,
        'domain_name' => 'a.cz',
        'status'      => 'pending',
    ]);

    \App\Models\DomainTransferRequest::create([
        'customer_id' => $customer->id,
        'domain_name' => 'b.cz',
        'status'      => 'completed',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.domain-transfer-statistics.index'))
        ->assertOk();

    expect($response->viewData('totalRequests'))->toBe(2);
});

it('statistics shows recent requests', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.domain-transfer-statistics.index'))
        ->assertOk()
        ->assertViewHas('recentRequests');
});

it('guest cannot access domain transfer statistics', function (): void {
    $this->get(route('admin.domain-transfer-statistics.index'))
        ->assertRedirect();
});

it('statistics loads with no requests', function (): void {
    $response = $this->actingAs(adminUser())
        ->get(route('admin.domain-transfer-statistics.index'))
        ->assertOk();

    expect($response->viewData('totalRequests'))->toBe(0);
});
