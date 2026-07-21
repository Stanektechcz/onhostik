<?php

declare(strict_types=1);

use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Jobs\WebhostingConfigActionJob;
use App\Domains\Provisioning\Models\ProvisioningTask;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\WebhostingConfigService;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Http;

/**
 * Full aaPanel-parity configuration on the service detail (audit
 * E77/E78/E79/E80) plus honest error reporting (E82).
 *
 * All reads are pure GETs that must work WITHOUT the
 * AAPANEL_ALLOW_REAL_WRITES gate; all writes must stay behind it.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

function hostingService(string $label = 'konfigurace.cz'): Service
{
    return Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => ServiceStatus::Active,
        'label'               => $label,
        'external_id'         => '12',
        'resources'           => ['php_version' => '82'],
    ]);
}

/** Real (non-mock) aaPanel integration, write gate left CLOSED. */
function liveAapanel(): void
{
    IntegrationSetting::query()->updateOrCreate(
        ['provider' => 'aapanel'],
        [
            'label'       => 'AAPanel (webhosting)',
            'is_active'   => true,
            'mock_mode'   => false,
            'dry_run'     => false,
            'credentials' => ['base_url' => 'https://panel.test', 'api_key' => 'k'],
        ],
    );
}

// ── Reads ─────────────────────────────────────────────────────────────────────

it('reports the service as unsupported for a non-aaPanel driver', function (): void {
    $service = Service::factory()->create(['provisioning_driver' => ProvisioningDriver::Proxmox]);

    expect(app(WebhostingConfigService::class)->forService($service)['supported'])->toBeFalse();
});

it('returns the full configuration surface in mock mode', function (): void {
    $config = app(WebhostingConfigService::class)->forService(hostingService());

    expect($config['supported'])->toBeTrue()
        ->and($config['dry_run'])->toBeTrue()
        ->and($config['php']['available'])->not->toBeEmpty()
        ->and($config)->toHaveKeys(['databases', 'ftp', 'cron', 'ssl']);
});

it('offers only PHP versions the panel actually has', function (): void {
    liveAapanel();
    // '00' is aaPanel's "Static" pseudo-build and must never be offered.
    Http::fake(['*' => Http::response([
        ['version' => '00', 'name' => 'Static'],
        ['version' => '83', 'name' => 'PHP-83'],
    ], 200)]);

    $php = app(WebhostingConfigService::class)->forService(hostingService())['php'];

    expect($php['available'])->toHaveKey('83')
        ->and($php['available'])->not->toHaveKey('00');
});

it('reads configuration without needing the real-writes gate', function (): void {
    liveAapanel();
    config()->set('provisioning.aapanel.allow_real_writes', false);
    Http::fake(['*' => Http::response([['name' => 'web_db', 'username' => 'web_user']], 200)]);

    $config = app(WebhostingConfigService::class)->forService(hostingService());

    // Viewing your own configuration must never require write access.
    expect($config['databases']['error'])->toBeNull()
        ->and($config['databases']['items'])->not->toBeEmpty();
});

it('reports why a section failed instead of showing an empty box', function (): void {
    liveAapanel();
    Http::fake(fn () => throw new RuntimeException('panel unreachable'));

    $config = app(WebhostingConfigService::class)->forService(hostingService());

    // E82: a silent empty list is indistinguishable from "nothing configured".
    expect($config['databases']['error'])->toContain('panel unreachable')
        ->and($config['ftp']['error'])->not->toBeNull()
        ->and($config['databases']['items'])->toBe([]);
});

// ── Writes ────────────────────────────────────────────────────────────────────

it('records a database creation as a provisioning task', function (): void {
    $service = hostingService();

    WebhostingConfigActionJob::dispatchSync($service->id, 'create_database', [
        'name' => 'web_db', 'username' => 'web_user',
    ]);

    $task = ProvisioningTask::where('service_id', $service->id)->where('operation', 'create_database')->firstOrFail();

    expect($task->status)->toBe(TaskStatus::Success)
        ->and($task->result['dry_run'] ?? null)->toBeTrue(); // gate closed → simulated
});

it('never persists a password in the task payload or activity log', function (): void {
    $service = hostingService();

    WebhostingConfigActionJob::dispatchSync($service->id, 'create_ftp', [
        'username' => 'web_ftp',
        'password' => 'sup3r-s3cret',
    ]);

    $task = ProvisioningTask::where('service_id', $service->id)->where('operation', 'create_ftp')->firstOrFail();
    $blob = json_encode([$task->payload, $task->result]) ?: '';

    expect($blob)->not->toContain('sup3r-s3cret');

    $activity = \Spatie\Activitylog\Models\Activity::where('subject_id', $service->id)->latest('id')->first();
    expect(json_encode($activity?->properties) ?: '')->not->toContain('sup3r-s3cret');
});

it('rejects an unknown configuration action', function (): void {
    expect(fn () => new WebhostingConfigActionJob(1, 'drop_everything'))
        ->toThrow(InvalidArgumentException::class);
});

it('ignores a config action for a non-aaPanel service', function (): void {
    $service = Service::factory()->create(['provisioning_driver' => ProvisioningDriver::Proxmox]);

    WebhostingConfigActionJob::dispatchSync($service->id, 'create_database', ['name' => 'x', 'username' => 'y']);

    expect(ProvisioningTask::where('service_id', $service->id)->count())->toBe(0);
});

// ── Admin UI ──────────────────────────────────────────────────────────────────

it('lets an admin create a database from the service detail', function (): void {
    $service = hostingService();

    $this->actingAs(adminUser())
        ->post(route('admin.services.config', $service), [
            'action' => 'create_database', 'name' => 'shop_db', 'username' => 'shop',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(ProvisioningTask::where('service_id', $service->id)->where('operation', 'create_database')->exists())->toBeTrue();
});

it('refuses a half-filled configuration form with a clear message', function (): void {
    $service = hostingService();

    $this->actingAs(adminUser())
        ->from(route('admin.services.show', $service))
        ->post(route('admin.services.config', $service), ['action' => 'create_database', 'name' => 'only_name'])
        ->assertSessionHasErrors('config');

    expect(ProvisioningTask::where('service_id', $service->id)->count())->toBe(0);
});

it('rejects a database name with shell-unsafe characters', function (): void {
    $service = hostingService();

    $this->actingAs(adminUser())
        ->from(route('admin.services.show', $service))
        ->post(route('admin.services.config', $service), [
            'action' => 'create_database', 'name' => 'db; rm -rf /', 'username' => 'u',
        ])
        ->assertSessionHasErrors('name');
});

it('refuses configuration actions on a non-aaPanel service', function (): void {
    $service = Service::factory()->create(['provisioning_driver' => ProvisioningDriver::Pterodactyl]);

    $this->actingAs(adminUser())
        ->from(route('admin.services.show', $service))
        ->post(route('admin.services.config', $service), ['action' => 'issue_ssl'])
        ->assertSessionHasErrors('config');
});

it('forbids a customer from changing service configuration', function (): void {
    $service = hostingService();

    $this->actingAs(customerUser())
        ->post(route('admin.services.config', $service), ['action' => 'issue_ssl'])
        ->assertForbidden();
});

it('shows the full configuration card on the admin service detail', function (): void {
    $service = hostingService();

    $this->actingAs(adminUser())
        ->get(route('admin.services.show', $service))
        ->assertOk()
        ->assertSee('Konfigurace webhostingu')
        ->assertSee('Databáze')
        ->assertSee('FTP účty')
        ->assertSee('Cron úlohy')
        ->assertSee('SSL certifikát');
});
