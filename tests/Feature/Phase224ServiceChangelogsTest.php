<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view service changelogs', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.service-changelogs.index'))
        ->assertOk()
        ->assertViewHas('changelogs');
});

it('admin can create a service changelog entry', function (): void {
    $service = \App\Domains\Provisioning\Models\Service::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('admin.service-changelogs.store'), [
            'service_id' => $service->id,
            'event_type' => 'upgrade',
            'summary'    => 'Service upgraded',
        ])
        ->assertRedirect();

    expect(\App\Models\ServiceChangelog::count())->toBe(1);
});

it('admin can filter changelogs by service_id', function (): void {
    $service1 = \App\Domains\Provisioning\Models\Service::factory()->create();
    $service2 = \App\Domains\Provisioning\Models\Service::factory()->create();

    \App\Models\ServiceChangelog::create([
        'service_id' => $service1->id,
        'event_type' => 'upgrade',
        'summary'    => 'Service 1 upgraded',
    ]);

    \App\Models\ServiceChangelog::create([
        'service_id' => $service2->id,
        'event_type' => 'downgrade',
        'summary'    => 'Service 2 downgraded',
    ]);

    $this->actingAs(adminUser())
        ->get(route('admin.service-changelogs.index').'?service_id='.$service1->id)
        ->assertOk();
});

it('guest cannot access service changelogs', function (): void {
    $this->get(route('admin.service-changelogs.index'))
        ->assertRedirect();
});

it('store validates event_type is required', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.service-changelogs.store'), [
            'service_id' => 1,
            'summary'    => 'Missing event type',
        ])
        ->assertSessionHasErrors('event_type');
});
