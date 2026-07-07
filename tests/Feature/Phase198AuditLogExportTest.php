<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view audit log export page', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.audit-log-export.index'))
        ->assertOk()
        ->assertViewHas('logNames');
});

it('admin can download audit log csv', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.audit-log-export.export'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

it('export csv has correct header row', function (): void {
    $response = $this->actingAs(adminUser())
        ->get(route('admin.audit-log-export.export'));

    $response->assertOk();
    expect($response->getContent())->toContain('id,log_name,description');
});

it('export accepts date filter', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.audit-log-export.export', ['from' => '2026-01-01', 'to' => '2026-12-31']))
        ->assertOk();
});

it('unauthenticated user cannot access audit log export', function (): void {
    $this->get(route('admin.audit-log-export.index'))
        ->assertRedirect();
});
