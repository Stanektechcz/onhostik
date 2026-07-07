<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can trigger a report schedule run', function (): void {
    $admin    = adminUser();
    $schedule = \App\Models\ReportSchedule::create([
        'name'        => 'Test Report',
        'report_type' => 'revenue',
        'frequency'   => 'daily',
        'recipients'  => ['a@test.com'],
        'format'      => 'csv',
        'is_active'   => true,
        'created_by'  => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.report-schedule-runs.store', $schedule))
        ->assertRedirect();

    $this->assertDatabaseHas('report_schedules', ['id' => $schedule->id]);
    expect(\App\Models\ReportSchedule::first()->last_run_at)->not->toBeNull();
});

it('triggering run updates last_run_at', function (): void {
    $admin    = adminUser();
    $schedule = \App\Models\ReportSchedule::create([
        'name'        => 'Test Report',
        'report_type' => 'revenue',
        'frequency'   => 'daily',
        'recipients'  => ['a@test.com'],
        'format'      => 'csv',
        'is_active'   => true,
        'created_by'  => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.report-schedule-runs.store', $schedule));

    expect(\App\Models\ReportSchedule::first()->last_run_at)->not->toBeNull();
});

it('triggering run updates next_run_at for daily schedule', function (): void {
    $admin    = adminUser();
    $schedule = \App\Models\ReportSchedule::create([
        'name'        => 'Daily Report',
        'report_type' => 'revenue',
        'frequency'   => 'daily',
        'recipients'  => ['a@test.com'],
        'format'      => 'csv',
        'is_active'   => true,
        'created_by'  => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.report-schedule-runs.store', $schedule));

    expect(\App\Models\ReportSchedule::first()->next_run_at)
        ->toBeGreaterThanOrEqual(now()->addDay()->subMinute());
});

it('guest cannot trigger report schedule run', function (): void {
    $admin    = adminUser();
    $schedule = \App\Models\ReportSchedule::create([
        'name'        => 'Test Report',
        'report_type' => 'revenue',
        'frequency'   => 'daily',
        'recipients'  => ['a@test.com'],
        'format'      => 'csv',
        'is_active'   => true,
        'created_by'  => $admin->id,
    ]);

    $this->post(route('admin.report-schedule-runs.store', $schedule))
        ->assertRedirect();
});

it('triggering run returns success message', function (): void {
    $admin    = adminUser();
    $schedule = \App\Models\ReportSchedule::create([
        'name'        => 'Test Report',
        'report_type' => 'revenue',
        'frequency'   => 'daily',
        'recipients'  => ['a@test.com'],
        'format'      => 'csv',
        'is_active'   => true,
        'created_by'  => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.report-schedule-runs.store', $schedule))
        ->assertRedirect()
        ->assertSessionHas('status');
});
