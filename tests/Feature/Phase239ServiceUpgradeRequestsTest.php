<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('panel user can view service upgrade requests', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.service-upgrade-requests.index'))
        ->assertOk()
        ->assertViewHas('requests');
});

it('panel user can submit a service upgrade request', function (): void {
    $user    = customerUser();
    $service = \App\Domains\Provisioning\Models\Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($user)
        ->post(route('panel.service-upgrade-requests.store'), [
            'service_id'    => $service->id,
            'customer_note' => 'Need more RAM',
        ])
        ->assertRedirect();

    expect(\App\Models\ServiceUpgradeRequest::count())->toBe(1);
});

it('upgrade request is created with pending status', function (): void {
    $user    = customerUser();
    $service = \App\Domains\Provisioning\Models\Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($user)
        ->post(route('panel.service-upgrade-requests.store'), [
            'service_id'    => $service->id,
            'customer_note' => 'Need more RAM',
        ]);

    expect(\App\Models\ServiceUpgradeRequest::first()->status)->toBe('pending');
});

it('panel user can view upgrade request detail', function (): void {
    $user    = customerUser();
    $service = \App\Domains\Provisioning\Models\Service::factory()->create(['customer_id' => $user->customer->id]);
    $request = \App\Models\ServiceUpgradeRequest::create([
        'service_id' => $service->id,
        'user_id'    => $user->id,
        'status'     => 'pending',
    ]);

    $this->actingAs($user)
        ->get(route('panel.service-upgrade-requests.show', $request))
        ->assertOk()
        ->assertViewHas('serviceUpgradeRequest');
});

it('guest cannot access service upgrade requests', function (): void {
    $this->get(route('panel.service-upgrade-requests.index'))
        ->assertRedirect();
});
