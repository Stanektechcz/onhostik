<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('panel user can view service migration status', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.service-migration-status.index'))
        ->assertOk()
        ->assertViewHas('activeMigrations');
});

it('panel user sees migrations for their services', function (): void {
    $user    = customerUser();
    $service = \App\Domains\Provisioning\Models\Service::factory()->create(['customer_id' => $user->customer->id]);

    \App\Models\ServiceMigrationBatch::create([
        'name'             => 'Test Migration',
        'source_server_id' => 1,
        'target_server_id' => 2,
        'service_ids'      => [$service->id],
        'status'           => 'pending',
        'created_by'       => adminUser()->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('panel.service-migration-status.index'))
        ->assertOk();

    expect($response->viewData('activeMigrations')->count())->toBeGreaterThanOrEqual(1);
});

it('panel user does not see migrations for other services', function (): void {
    $user      = customerUser();
    $otherUser = customerUser();

    $otherService = \App\Domains\Provisioning\Models\Service::factory()->create(['customer_id' => $otherUser->customer->id]);

    \App\Models\ServiceMigrationBatch::create([
        'name'             => 'Other Migration',
        'source_server_id' => 1,
        'target_server_id' => 2,
        'service_ids'      => [$otherService->id],
        'status'           => 'pending',
        'created_by'       => adminUser()->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('panel.service-migration-status.index'))
        ->assertOk();

    expect($response->viewData('activeMigrations')->count())->toBe(0);
});

it('guest cannot access service migration status', function (): void {
    $this->get(route('panel.service-migration-status.index'))
        ->assertRedirect();
});

it('completed migrations are shown separately', function (): void {
    $user    = customerUser();
    $service = \App\Domains\Provisioning\Models\Service::factory()->create(['customer_id' => $user->customer->id]);

    \App\Models\ServiceMigrationBatch::create([
        'name'             => 'Done Migration',
        'source_server_id' => 1,
        'target_server_id' => 2,
        'service_ids'      => [$service->id],
        'status'           => 'completed',
        'created_by'       => adminUser()->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('panel.service-migration-status.index'))
        ->assertOk();

    expect($response->viewData('completedMigrations')->count())->toBeGreaterThanOrEqual(1);
});
