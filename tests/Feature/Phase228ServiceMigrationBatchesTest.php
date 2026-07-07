<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view service migration batches', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.service-migration-batches.index'))
        ->assertOk()
        ->assertViewHas('batches');
});

it('admin can create a service migration batch', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.service-migration-batches.store'), [
            'name'             => 'Migration 1',
            'source_server_id' => 1,
            'target_server_id' => 2,
            'service_ids_raw'  => '1,2,3',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('service_migration_batches', ['name' => 'Migration 1']);
});

it('batch is created with pending status', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.service-migration-batches.store'), [
            'name'             => 'Migration 2',
            'source_server_id' => 1,
            'target_server_id' => 2,
            'service_ids_raw'  => '4,5',
        ])
        ->assertRedirect();

    expect(\App\Models\ServiceMigrationBatch::first()->status)->toBe('pending');
});

it('guest cannot access service migration batches', function (): void {
    $this->get(route('admin.service-migration-batches.index'))
        ->assertRedirect();
});

it('store validates source and target server differ', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.service-migration-batches.store'), [
            'name'             => 'Same Server',
            'source_server_id' => 1,
            'target_server_id' => 1,
            'service_ids_raw'  => '1',
        ])
        ->assertSessionHasErrors('target_server_id');
});
