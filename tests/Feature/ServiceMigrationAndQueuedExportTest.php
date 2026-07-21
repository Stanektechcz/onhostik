<?php

declare(strict_types=1);

use App\Domains\Provisioning\Actions\MigrateServiceToServerAction;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Server;
use App\Domains\Provisioning\Models\Service;
use App\Jobs\GenerateInvoiceBatchExportJob;
use App\Models\ExportJob;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * E53 service migration between servers + L120 queued heavy exports.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

function migratableService(Server $server): Service
{
    return Service::factory()->create([
        'customer_id'         => customerUser()->customer->id,
        'server_id'           => $server->id,
        'status'              => ServiceStatus::Active,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'external_id'         => 'site-on-old-panel',
    ]);
}

function serverFor(ProvisioningDriver $driver, ?int $max = null): Server
{
    return Server::factory()->create([
        'driver'       => $driver->value,
        'status'       => 'active',
        'max_services' => $max,
    ]);
}

// ── E53: migration ────────────────────────────────────────────────────────────

it('rebinds a service to the target server', function (): void {
    $from = serverFor(ProvisioningDriver::AAPanel);
    $to   = serverFor(ProvisioningDriver::AAPanel);
    $service = migratableService($from);

    $moved = app(MigrateServiceToServerAction::class)->execute($service, $to, 'draining old box');

    expect($moved->server_id)->toBe($to->id);
});

it('clears the external id so the new panel is re-synced', function (): void {
    $from = serverFor(ProvisioningDriver::AAPanel);
    $to   = serverFor(ProvisioningDriver::AAPanel);
    $service = migratableService($from);

    $moved = app(MigrateServiceToServerAction::class)->execute($service, $to);

    // The old id addresses a site on the OLD panel — keeping it would silently
    // point every later operation at the wrong box.
    expect($moved->external_id)->toBeNull();
});

it('records that data was NOT migrated', function (): void {
    $from = serverFor(ProvisioningDriver::AAPanel);
    $to   = serverFor(ProvisioningDriver::AAPanel);
    $service = migratableService($from);

    app(MigrateServiceToServerAction::class)->execute($service, $to);

    $activity = \Spatie\Activitylog\Models\Activity::where('description', 'service.migrated_server')->firstOrFail();

    // An admin who believes the files followed would cut over a live site.
    expect($activity->properties['data_migrated'])->toBeFalse()
        ->and($activity->properties['to_server_id'])->toBe($to->id);
});

it('refuses to migrate onto a server with a different driver', function (): void {
    $from = serverFor(ProvisioningDriver::AAPanel);
    $to   = serverFor(ProvisioningDriver::Proxmox);
    $service = migratableService($from);

    // An aaPanel site cannot become a Proxmox VM by changing a foreign key.
    expect(fn () => app(MigrateServiceToServerAction::class)->execute($service, $to))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses to migrate onto a full server', function (): void {
    $from = serverFor(ProvisioningDriver::AAPanel);
    $to   = serverFor(ProvisioningDriver::AAPanel, max: 1);

    Service::factory()->create([
        'customer_id' => customerUser()->customer->id,
        'server_id'   => $to->id,
        'status'      => ServiceStatus::Active,
    ]);

    $service = migratableService($from);

    expect(fn () => app(MigrateServiceToServerAction::class)->execute($service, $to))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses to migrate a terminated service', function (): void {
    $from = serverFor(ProvisioningDriver::AAPanel);
    $to   = serverFor(ProvisioningDriver::AAPanel);

    $service = migratableService($from);
    $service->update(['status' => ServiceStatus::Terminated]);

    expect(fn () => app(MigrateServiceToServerAction::class)->execute($service->fresh(), $to))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses a no-op migration to the same server', function (): void {
    $server  = serverFor(ProvisioningDriver::AAPanel);
    $service = migratableService($server);

    expect(fn () => app(MigrateServiceToServerAction::class)->execute($service, $server))
        ->toThrow(InvalidArgumentException::class);
});

it('lets an admin migrate a service over HTTP', function (): void {
    $from = serverFor(ProvisioningDriver::AAPanel);
    $to   = serverFor(ProvisioningDriver::AAPanel);
    $service = migratableService($from);

    $this->actingAs(adminUser())
        ->post(route('admin.services.migrate', $service), ['server_id' => $to->id])
        ->assertRedirect();

    expect($service->fresh()->server_id)->toBe($to->id);
});

// ── L120: queued exports ──────────────────────────────────────────────────────

it('queues the invoice batch export instead of rendering inline', function (): void {
    Queue::fake();

    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    $this->actingAs(adminUser())
        ->post(route('admin.invoice-batch.export'), ['invoice_ids' => [$invoice->id]])
        ->assertRedirect();

    // 50 dompdf renders used to run inside the request, against a timeout.
    Queue::assertPushed(GenerateInvoiceBatchExportJob::class);
    expect(ExportJob::where('type', 'invoice_batch')->count())->toBe(1);
});

it('produces a downloadable archive when the job runs', function (): void {
    Storage::fake('local');

    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user);
    $admin = adminUser();

    $export = ExportJob::create([
        'user_id' => $admin->id, 'type' => 'invoice_batch', 'format' => 'zip',
        'status' => ExportJob::STATUS_PENDING,
        'parameters' => ['invoice_ids' => [$invoice->id]],
    ]);

    (new GenerateInvoiceBatchExportJob($export->id))->handle();

    $export->refresh();

    expect($export->status)->toBe(ExportJob::STATUS_READY)
        ->and($export->row_count)->toBe(1)
        ->and($export->isDownloadable())->toBeTrue();

    Storage::disk('local')->assertExists((string) $export->file_path);
});

it('only lets the requester download their own export', function (): void {
    Storage::fake('local');

    $mine = adminUser();
    $export = ExportJob::create([
        'user_id' => $mine->id, 'type' => 'invoice_batch', 'status' => ExportJob::STATUS_READY,
        'file_path' => 'exports/x.zip', 'expires_at' => now()->addDay(),
    ]);

    // These archives hold invoice data for many customers — another admin must
    // not be able to pull one by guessing an id.
    $this->actingAs(adminUser())
        ->get(route('admin.invoice-batch.download', $export))
        ->assertForbidden();
});

it('refuses to serve an expired export', function (): void {
    $admin = adminUser();
    $export = ExportJob::create([
        'user_id' => $admin->id, 'type' => 'invoice_batch', 'status' => ExportJob::STATUS_READY,
        'file_path' => 'exports/old.zip', 'expires_at' => now()->subDay(),
    ]);

    expect($export->isDownloadable())->toBeFalse();

    $this->actingAs($admin)
        ->get(route('admin.invoice-batch.download', $export))
        ->assertNotFound();
});

it('records the reason when an export fails', function (): void {
    $admin = adminUser();

    // No such invoice ids → the job still marks the row, so the admin sees
    // "failed and why" instead of a silent blank download.
    $export = ExportJob::create([
        'user_id' => $admin->id, 'type' => 'invoice_batch', 'status' => ExportJob::STATUS_PENDING,
        'parameters' => ['invoice_ids' => []],
    ]);

    (new GenerateInvoiceBatchExportJob($export->id))->handle();

    // An empty set is a valid (if pointless) export, not a failure.
    expect($export->fresh()->status)->toBe(ExportJob::STATUS_READY)
        ->and($export->fresh()->row_count)->toBe(0);
});
