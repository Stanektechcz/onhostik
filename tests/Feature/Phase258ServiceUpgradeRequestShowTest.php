<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('panel user can view their upgrade request detail', function (): void {
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

it('panel user cannot view another user\'s upgrade request', function (): void {
    $user      = customerUser();
    $otherUser = customerUser();
    $service   = \App\Domains\Provisioning\Models\Service::factory()->create(['customer_id' => $otherUser->customer->id]);
    $request   = \App\Models\ServiceUpgradeRequest::create([
        'service_id' => $service->id,
        'user_id'    => $otherUser->id,
        'status'     => 'pending',
    ]);

    $this->actingAs($user)
        ->get(route('panel.service-upgrade-requests.show', $request))
        ->assertForbidden();
});

it('upgrade request detail shows status', function (): void {
    $user    = customerUser();
    $service = \App\Domains\Provisioning\Models\Service::factory()->create(['customer_id' => $user->customer->id]);
    $request = \App\Models\ServiceUpgradeRequest::create([
        'service_id' => $service->id,
        'user_id'    => $user->id,
        'status'     => 'approved',
    ]);

    $this->actingAs($user)
        ->get(route('panel.service-upgrade-requests.show', $request))
        ->assertOk();
});

it('guest cannot view upgrade request detail', function (): void {
    $user    = customerUser();
    $service = \App\Domains\Provisioning\Models\Service::factory()->create(['customer_id' => $user->customer->id]);
    $request = \App\Models\ServiceUpgradeRequest::create([
        'service_id' => $service->id,
        'user_id'    => $user->id,
        'status'     => 'pending',
    ]);

    $this->get(route('panel.service-upgrade-requests.show', $request))
        ->assertRedirect();
});

it('upgrade request detail shows admin note when set', function (): void {
    $user    = customerUser();
    $service = \App\Domains\Provisioning\Models\Service::factory()->create(['customer_id' => $user->customer->id]);
    $request = \App\Models\ServiceUpgradeRequest::create([
        'service_id' => $service->id,
        'user_id'    => $user->id,
        'status'     => 'approved',
        'admin_note' => 'Approved for 2GB',
    ]);

    $this->actingAs($user)
        ->get(route('panel.service-upgrade-requests.show', $request))
        ->assertOk();
});
