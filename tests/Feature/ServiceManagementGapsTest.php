<?php

declare(strict_types=1);

use App\Domains\Backups\Models\BackupJob;
use App\Domains\Backups\Models\BackupPolicy;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Models\SnapshotRestoreRequest;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Queue;

/**
 * Remaining service-management gaps: suspend reason (E73), snapshot restore
 * approval (E76), reinstall mode (E81) and honest live-status errors (E82).
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── E73: suspend must carry a reason ──────────────────────────────────────────

it('requires a reason to suspend a service', function (): void {
    $service = Service::factory()->create(['status' => ServiceStatus::Active]);

    $this->actingAs(adminUser())
        ->from(route('admin.services.show', $service))
        ->post(route('admin.services.suspend', $service), ['reason' => ''])
        ->assertSessionHasErrors('reason');
});

it('records the reason when suspending a service', function (): void {
    $service = Service::factory()->create(['status' => ServiceStatus::Active]);

    $this->actingAs(adminUser())
        ->post(route('admin.services.suspend', $service), ['reason' => 'Neuhrazená faktura'])
        ->assertRedirect();

    expect(\Spatie\Activitylog\Models\Activity::where('description', 'service.suspend_requested')
        ->get()
        ->contains(fn ($a): bool => ($a->properties['reason'] ?? null) === 'Neuhrazená faktura'))->toBeTrue();
});

// ── E81: reinstall mode ───────────────────────────────────────────────────────

function gameService(): Service
{
    return Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::Pterodactyl,
        'status'              => ServiceStatus::Active,
    ]);
}

it('takes a backup before reinstalling when asked to preserve data', function (): void {
    Queue::fake();
    $service = gameService();
    BackupPolicy::create([
        'service_id' => $service->id, 'frequency' => 'daily',
        'retention_days' => 14, 'provider' => 'local_mock', 'is_active' => true,
    ]);

    $this->actingAs(adminUser())
        ->post(route('admin.services.game-action', $service), ['action' => 'reinstall', 'mode' => 'backup_first'])
        ->assertRedirect();

    expect(BackupJob::where('service_id', $service->id)->where('type', 'pre_reinstall')->exists())->toBeTrue();
});

it('refuses a preserving reinstall when there is no backup policy', function (): void {
    Queue::fake();
    $service = gameService(); // no BackupPolicy

    $this->actingAs(adminUser())
        ->from(route('admin.services.show', $service))
        ->post(route('admin.services.game-action', $service), ['action' => 'reinstall', 'mode' => 'backup_first'])
        ->assertSessionHasErrors('service');

    // Must not silently wipe when the operator asked to keep the data.
    expect(BackupJob::where('service_id', $service->id)->exists())->toBeFalse();
});

it('reinstalls without a backup when the wipe mode is chosen', function (): void {
    Queue::fake();
    $service = gameService();

    $this->actingAs(adminUser())
        ->post(route('admin.services.game-action', $service), ['action' => 'reinstall', 'mode' => 'wipe'])
        ->assertRedirect();

    expect(BackupJob::where('service_id', $service->id)->exists())->toBeFalse();
});

it('rejects an unknown reinstall mode', function (): void {
    $service = gameService();

    $this->actingAs(adminUser())
        ->from(route('admin.services.show', $service))
        ->post(route('admin.services.game-action', $service), ['action' => 'reinstall', 'mode' => 'nuke'])
        ->assertSessionHasErrors('mode');
});

// ── E76: snapshot restore approval ────────────────────────────────────────────

function restoreRequest(string $status = 'pending'): SnapshotRestoreRequest
{
    return SnapshotRestoreRequest::create([
        'service_id'    => Service::factory()->create()->id,
        'user_id'       => customerUser()->id,
        'restore_point' => '2026-07-18 02:00',
        'status'        => $status,
        'customer_note' => 'Prosím obnovit',
    ]);
}

it('renders the admin restore-request queue', function (): void {
    restoreRequest();

    $this->actingAs(adminUser())
        ->get(route('admin.snapshot-restore-requests.index'))
        ->assertOk()
        ->assertSee('Požadavky na obnovu');
});

it('lets an admin approve a restore request', function (): void {
    $req = restoreRequest();

    $this->actingAs(adminUser())
        ->post(route('admin.snapshot-restore-requests.approve', $req), ['admin_note' => 'OK'])
        ->assertRedirect();

    expect($req->fresh()->status)->toBe('approved')
        ->and($req->fresh()->handled_by)->not->toBeNull();
});

it('requires a reason to reject a restore request', function (): void {
    $req = restoreRequest();

    $this->actingAs(adminUser())
        ->from(route('admin.snapshot-restore-requests.index'))
        ->post(route('admin.snapshot-restore-requests.reject', $req), ['admin_note' => ''])
        ->assertSessionHasErrors('admin_note');

    expect($req->fresh()->status)->toBe('pending');
});

it('lets an admin reject with a reason the customer can read', function (): void {
    $req = restoreRequest();

    $this->actingAs(adminUser())
        ->post(route('admin.snapshot-restore-requests.reject', $req), ['admin_note' => 'Záloha je poškozená'])
        ->assertRedirect();

    expect($req->fresh()->status)->toBe('rejected')
        ->and($req->fresh()->admin_note)->toBe('Záloha je poškozená');
});

it('refuses to decide an already-handled request twice', function (): void {
    $req = restoreRequest('approved');

    $this->actingAs(adminUser())
        ->from(route('admin.snapshot-restore-requests.index'))
        ->post(route('admin.snapshot-restore-requests.approve', $req), ['admin_note' => 'again'])
        ->assertSessionHasErrors('request');
});

it('forbids a customer from approving restore requests', function (): void {
    $req = restoreRequest();

    $this->actingAs(customerUser())
        ->post(route('admin.snapshot-restore-requests.approve', $req))
        ->assertForbidden();

    expect($req->fresh()->status)->toBe('pending');
});

// ── E82: live status must explain itself ──────────────────────────────────────

it('tells the customer why the live status is unavailable', function (): void {
    $service = Service::factory()->create([
        'customer_id'         => ($user = customerUser())->customer->id,
        'provisioning_driver' => ProvisioningDriver::Proxmox,
        'status'              => ServiceStatus::Active,
        'external_id'         => null, // cannot be looked up
    ]);

    $response = $this->actingAs($user)->get(route('panel.services.live-status', $service));

    $response->assertOk();
    // The endpoint must carry a reason so the UI can show it instead of a
    // blank box (which reads as "nothing here").
    expect($response->json('ok'))->toBeFalse()
        ->and($response->json('error'))->not->toBeEmpty();
});
