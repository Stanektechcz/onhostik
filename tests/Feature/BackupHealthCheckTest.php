<?php

declare(strict_types=1);

use App\Domains\Backups\Enums\BackupJobStatus;
use App\Domains\Backups\Models\BackupJob;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

/**
 * Backup freshness on the admin system-health page — a silent backup failure
 * should be visible, not assumed away.
 */

it('flags that no successful backup has been recorded', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.system.index'))
        ->assertOk()
        ->assertSee('backups')
        ->assertSee('žádná úspěšná záloha');
});

it('shows the last successful backup when one exists', function (): void {
    $service = Service::factory()->create(['status' => ServiceStatus::Active]);
    BackupJob::create([
        'service_id'  => $service->id,
        'type'        => 'scheduled',
        'status'      => BackupJobStatus::Success,
        'finished_at' => now()->subHours(2),
    ]);

    $this->actingAs(adminUser())
        ->get(route('admin.system.index'))
        ->assertOk()
        ->assertSee('poslední úspěch');
});

it('flags recent backup failures', function (): void {
    $service = Service::factory()->create(['status' => ServiceStatus::Active]);
    BackupJob::create(['service_id' => $service->id, 'type' => 'scheduled', 'status' => BackupJobStatus::Success, 'finished_at' => now()->subHours(2)]);
    BackupJob::create(['service_id' => $service->id, 'type' => 'scheduled', 'status' => BackupJobStatus::Failed, 'created_at' => now()->subHours(1)]);

    $this->actingAs(adminUser())
        ->get(route('admin.system.index'))
        ->assertOk()
        ->assertSee('selhání za 24 h');
});
