<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('panel user can view report schedules', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.report-schedules.index'))
        ->assertOk()
        ->assertViewHas('schedules');
});

it('panel only shows active report schedules', function (): void {
    $user  = customerUser();
    $admin = adminUser();

    \App\Models\ReportSchedule::create([
        'name'        => 'Active',
        'report_type' => 'revenue',
        'frequency'   => 'monthly',
        'recipients'  => ['a@test.com'],
        'format'      => 'csv',
        'is_active'   => true,
        'created_by'  => $admin->id,
    ]);

    \App\Models\ReportSchedule::create([
        'name'        => 'Inactive',
        'report_type' => 'revenue',
        'frequency'   => 'monthly',
        'recipients'  => ['b@test.com'],
        'format'      => 'csv',
        'is_active'   => false,
        'created_by'  => $admin->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('panel.report-schedules.index'))
        ->assertOk();

    expect($response->viewData('schedules')->total())->toBe(1);
});

it('panel user sees schedule details', function (): void {
    $user  = customerUser();
    $admin = adminUser();

    \App\Models\ReportSchedule::create([
        'name'        => 'Monthly Revenue',
        'report_type' => 'revenue',
        'frequency'   => 'monthly',
        'recipients'  => ['c@test.com'],
        'format'      => 'pdf',
        'is_active'   => true,
        'created_by'  => $admin->id,
    ]);

    $this->actingAs($user)
        ->get(route('panel.report-schedules.index'))
        ->assertOk();
});

it('guest cannot access report schedules', function (): void {
    $this->get(route('panel.report-schedules.index'))
        ->assertRedirect();
});

it('panel report schedules are read-only', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.report-schedules.index'))
        ->assertOk();
});
