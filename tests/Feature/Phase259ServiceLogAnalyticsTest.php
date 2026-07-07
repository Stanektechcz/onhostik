<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view service log analytics', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.service-log-analytics.index'))
        ->assertOk()
        ->assertViewHas('levelStats');
});

it('analytics shows top error services', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.service-log-analytics.index'))
        ->assertOk()
        ->assertViewHas('topErrorServices');
});

it('analytics shows recent errors', function (): void {
    $service = \App\Domains\Provisioning\Models\Service::factory()->create();

    \App\Models\ServiceLog::create([
        'service_id' => $service->id,
        'level'      => 'error',
        'message'    => 'Test',
        'logged_at'  => now(),
    ]);

    $this->actingAs(adminUser())
        ->get(route('admin.service-log-analytics.index'))
        ->assertOk()
        ->assertViewHas('recentErrors');
});

it('guest cannot access service log analytics', function (): void {
    $this->get(route('admin.service-log-analytics.index'))
        ->assertRedirect();
});

it('analytics loads cleanly with no logs', function (): void {
    $response = $this->actingAs(adminUser())
        ->get(route('admin.service-log-analytics.index'))
        ->assertOk();

    expect($response->viewData('levelStats')->isEmpty())->toBeTrue();
});
