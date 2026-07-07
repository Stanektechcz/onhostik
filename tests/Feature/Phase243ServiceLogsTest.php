<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view service logs', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.service-logs.index'))
        ->assertOk()
        ->assertViewHas('logs');
});

it('admin can filter logs by level', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.service-logs.index').'?level=error')
        ->assertOk()
        ->assertViewHas('logs');
});

it('admin can filter logs by service_id', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.service-logs.index').'?service_id=1')
        ->assertOk();
});

it('guest cannot access service logs', function (): void {
    $this->get(route('admin.service-logs.index'))
        ->assertRedirect();
});

it('service log is visible after creation', function (): void {
    $service = \App\Domains\Provisioning\Models\Service::factory()->create();

    \App\Models\ServiceLog::create([
        'service_id' => $service->id,
        'level'      => 'error',
        'message'    => 'Test error',
        'logged_at'  => now(),
    ]);

    $response = $this->actingAs(adminUser())
        ->get(route('admin.service-logs.index'))
        ->assertOk();

    expect($response->viewData('logs')->total())->toBeGreaterThanOrEqual(1);
});
