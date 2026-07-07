<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view report schedules', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.report-schedules.index'))
        ->assertOk()
        ->assertViewHas('schedules');
});

it('admin can create a report schedule', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.report-schedules.store'), [
            'name'           => 'Monthly Report',
            'report_type'    => 'revenue',
            'frequency'      => 'monthly',
            'recipients_raw' => 'admin@test.com',
            'format'         => 'csv',
            'is_active'      => 1,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('report_schedules', ['name' => 'Monthly Report']);
});

it('admin can delete a report schedule', function (): void {
    $admin    = adminUser();
    $schedule = \App\Models\ReportSchedule::create([
        'name'        => 'ToDelete',
        'report_type' => 'revenue',
        'frequency'   => 'daily',
        'recipients'  => ['test@a.com'],
        'format'      => 'csv',
        'is_active'   => true,
        'created_by'  => $admin->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.report-schedules.destroy', $schedule))
        ->assertRedirect();

    $this->assertDatabaseMissing('report_schedules', ['id' => $schedule->id]);
});

it('guest cannot access report schedules', function (): void {
    $this->get(route('admin.report-schedules.index'))
        ->assertRedirect();
});

it('store validates frequency enum', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.report-schedules.store'), [
            'name'           => 'Bad Schedule',
            'report_type'    => 'revenue',
            'frequency'      => 'quarterly',
            'recipients_raw' => 'admin@test.com',
            'format'         => 'csv',
            'is_active'      => 1,
        ])
        ->assertSessionHasErrors('frequency');
});
