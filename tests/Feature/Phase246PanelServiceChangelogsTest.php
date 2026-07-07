<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('panel user can view service changelog page', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.service-changelogs.index'))
        ->assertOk()
        ->assertViewHas('services')
        ->assertViewHas('changelogs');
});

it('panel user can filter changelogs by their service', function (): void {
    $user    = customerUser();
    $service = \App\Domains\Provisioning\Models\Service::factory()->create([
        'customer_id' => $user->customer->id,
    ]);

    \App\Models\ServiceChangelog::create([
        'service_id' => $service->id,
        'event_type' => 'upgrade',
        'summary'    => 'Upgraded',
    ]);

    $this->actingAs($user)
        ->get(route('panel.service-changelogs.index') . '?service_id=' . $service->id)
        ->assertOk();
});

it('panel user cannot see changelogs for another customer service', function (): void {
    $user      = customerUser();
    $otherUser = customerUser();

    $otherService = \App\Domains\Provisioning\Models\Service::factory()->create([
        'customer_id' => $otherUser->customer->id,
    ]);

    $this->actingAs($user)
        ->get(route('panel.service-changelogs.index') . '?service_id=' . $otherService->id)
        ->assertForbidden();
});

it('changelogs list is empty when no service selected', function (): void {
    $user = customerUser();

    $response = $this->actingAs($user)
        ->get(route('panel.service-changelogs.index'))
        ->assertOk();

    expect(
        $response->viewData('changelogs') instanceof \Illuminate\Support\Collection
        && $response->viewData('changelogs')->isEmpty()
    )->toBeTrue();
});

it('guest cannot access service changelogs', function (): void {
    $this->get(route('panel.service-changelogs.index'))
        ->assertRedirect();
});
