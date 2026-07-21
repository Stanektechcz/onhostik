<?php

declare(strict_types=1);

use App\Domains\Integrations\Clients\AapanelClient;
use App\Domains\Integrations\Clients\WedosWapiClient;
use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\WebhostingConfigActionJob;
use App\Domains\Provisioning\Models\ProvisioningTask;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\WebhostingConfigService;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * The endpoints that were previously deferred for lack of client support:
 * DNSSEC (F87), WHOIS contacts (F89), glue records (F92) and mailboxes
 * (E80/F88).
 *
 * Every one follows the house pattern — mock payload in dry-run, real call
 * only once the gate is open — so they are exercisable without touching a
 * live panel. The critical property tested here is that no credential ever
 * survives into a log, a task payload or a returned echo.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

function wedosClient(): WedosWapiClient
{
    return new WedosWapiClient(IntegrationSetting::firstOrCreate(
        ['provider' => 'wedos'],
        ['label' => 'WEDOS', 'mock_mode' => true, 'dry_run' => true],
    ));
}

function aapanelClient(): AapanelClient
{
    return new AapanelClient(IntegrationSetting::firstOrCreate(
        ['provider' => 'aapanel'],
        ['label' => 'AAPanel', 'mock_mode' => true, 'dry_run' => true],
    ));
}

// ── F87 DNSSEC ────────────────────────────────────────────────────────────────

it('supports the DNSSEC lifecycle', function (): void {
    $client = wedosClient();

    expect($client->getDnssecKeys('example.cz')['dry_run'])->toBeTrue()
        ->and($client->addDnssecKey('example.cz', [
            'key_tag' => 12345, 'algorithm' => 13, 'digest_type' => 2, 'digest' => 'ABCDEF',
        ])['would_call'])->toBe('domain-dnssec-add')
        ->and($client->deleteDnssecKey('example.cz', 12345)['would_call'])->toBe('domain-dnssec-delete');
});

// ── F89 WHOIS contacts + privacy ──────────────────────────────────────────────

it('supports domain contacts and WHOIS privacy', function (): void {
    $client = wedosClient();

    expect($client->getDomainContacts('example.cz')['would_call'])->toBe('domain-contacts-list')
        ->and($client->updateDomainContact('example.cz', 'owner', ['email' => 'a@b.cz'])['would_call'])
        ->toBe('domain-contact-update')
        ->and($client->setWhoisPrivacy('example.cz', true)['payload']['privacy'])->toBe(1);
});

// ── F92 glue records ──────────────────────────────────────────────────────────

it('supports glue records for vanity nameservers', function (): void {
    $client = wedosClient();

    expect($client->getGlueRecords('example.cz')['would_call'])->toBe('host-list')
        ->and($client->addGlueRecord('example.cz', 'ns1.example.cz', '1.2.3.4')['payload']['ip'])->toBe('1.2.3.4')
        ->and($client->deleteGlueRecord('example.cz', 'ns1.example.cz')['would_call'])->toBe('host-delete');
});

// ── Secrets must never survive the dry-run echo ───────────────────────────────

it('redacts a transfer auth code from the WEDOS dry-run echo', function (): void {
    $result = wedosClient()->transferDomain('example.cz', 'SUPER-SECRET-AUTH');

    expect(json_encode($result) ?: '')->not->toContain('SUPER-SECRET-AUTH');
});

it('redacts a mailbox password from the aaPanel dry-run echo', function (): void {
    $result = aapanelClient()->createMailbox('example.cz', 'info', 'Sup3rSecretPass!', 2048);

    expect(json_encode($result) ?: '')->not->toContain('Sup3rSecretPass!')
        ->and($result['payload']['password'])->toBe('***redacted***')
        // Non-secret fields must still come through for debugging.
        ->and($result['payload']['username'])->toBe('info');
});

// ── E80 / F88 mailboxes ───────────────────────────────────────────────────────

it('lists mailboxes in the service configuration', function (): void {
    $service = Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => ServiceStatus::Active,
        'label'               => 'posta.cz',
    ]);

    $config = app(WebhostingConfigService::class)->forService($service);

    expect($config['mailboxes'])->toHaveKeys(['items', 'error'])
        ->and($config['mailboxes']['error'])->toBeNull();
});

it('creates a mailbox without persisting the password', function (): void {
    $service = Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => ServiceStatus::Active,
        'label'               => 'posta.cz',
    ]);

    WebhostingConfigActionJob::dispatchSync($service->id, 'create_mailbox', [
        'username' => 'info',
        'password' => 'Sup3rSecretPass!',
        'quota_mb' => 2048,
    ]);

    $task = ProvisioningTask::where('service_id', $service->id)
        ->where('operation', 'create_mailbox')
        ->firstOrFail();

    $blob = json_encode([$task->payload, $task->result]) ?: '';

    // Neither the job payload nor the panel echo may carry the password.
    expect($blob)->not->toContain('Sup3rSecretPass!')
        ->and($task->status)->toBe(\App\Domains\Provisioning\Enums\TaskStatus::Success);
});

it('lets an admin create a mailbox from the service detail', function (): void {
    $service = Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => ServiceStatus::Active,
        'label'               => 'posta.cz',
    ]);

    $this->actingAs(adminUser())
        ->post(route('admin.services.config', $service), [
            'action' => 'create_mailbox', 'username' => 'sales', 'password' => 'LongEnoughPass1', 'quota_mb' => 512,
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(ProvisioningTask::where('service_id', $service->id)->where('operation', 'create_mailbox')->exists())->toBeTrue();
});

it('rejects a mailbox password that is too short', function (): void {
    $service = Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => ServiceStatus::Active,
        'label'               => 'posta.cz',
    ]);

    $this->actingAs(adminUser())
        ->from(route('admin.services.show', $service))
        ->post(route('admin.services.config', $service), [
            'action' => 'create_mailbox', 'username' => 'x', 'password' => 'short',
        ])
        ->assertSessionHasErrors('password');
});

it('shows the mailbox section on the service detail', function (): void {
    $service = Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => ServiceStatus::Active,
        'label'               => 'posta.cz',
    ]);

    $this->actingAs(adminUser())
        ->get(route('admin.services.show', $service))
        ->assertOk()
        ->assertSee('E-mailové schránky');
});
