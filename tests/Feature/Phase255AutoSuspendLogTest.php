<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view auto-suspend log', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.auto-suspend-log.index'))
        ->assertOk()
        ->assertViewHas('rules');
});

it('log shows correct active count', function (): void {
    $admin = adminUser();

    \App\Models\AutoSuspendRule::create(['name' => 'R1', 'trigger' => 'overdue_days',    'threshold_value' => 30, 'is_active' => true,  'description' => '']);
    \App\Models\AutoSuspendRule::create(['name' => 'R2', 'trigger' => 'usage_percent',   'threshold_value' => 90, 'is_active' => true,  'description' => '']);
    \App\Models\AutoSuspendRule::create(['name' => 'R3', 'trigger' => 'failed_payments', 'threshold_value' => 3,  'is_active' => false, 'description' => '']);

    $response = $this->actingAs($admin)
        ->get(route('admin.auto-suspend-log.index'))
        ->assertOk();

    expect($response->viewData('activeCount'))->toBe(2);
});

it('log shows correct inactive count', function (): void {
    $admin = adminUser();

    \App\Models\AutoSuspendRule::create(['name' => 'R1', 'trigger' => 'overdue_days',    'threshold_value' => 30, 'is_active' => true,  'description' => '']);
    \App\Models\AutoSuspendRule::create(['name' => 'R2', 'trigger' => 'usage_percent',   'threshold_value' => 90, 'is_active' => true,  'description' => '']);
    \App\Models\AutoSuspendRule::create(['name' => 'R3', 'trigger' => 'failed_payments', 'threshold_value' => 3,  'is_active' => false, 'description' => '']);

    $response = $this->actingAs($admin)
        ->get(route('admin.auto-suspend-log.index'))
        ->assertOk();

    expect($response->viewData('inactiveCount'))->toBe(1);
});

it('guest cannot access auto-suspend log', function (): void {
    $this->get(route('admin.auto-suspend-log.index'))
        ->assertRedirect();
});

it('auto-suspend log page loads with no rules', function (): void {
    $response = $this->actingAs(adminUser())
        ->get(route('admin.auto-suspend-log.index'))
        ->assertOk();

    expect($response->viewData('activeCount'))->toBe(0);
});
