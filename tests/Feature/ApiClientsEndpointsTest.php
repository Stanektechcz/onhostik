<?php

declare(strict_types=1);

use App\Domains\Integrations\Clients\AapanelClient;
use App\Domains\Integrations\Clients\PterodactylClient;
use App\Domains\Integrations\Clients\ProxmoxClient;
use App\Domains\Integrations\Clients\WedosWapiClient;
use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Provisioning\Drivers\PterodactylProductionDriver;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\DriverResolver;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── aaPanel client ────────────────────────────────────────────────────────────

it('AapanelClient::connectionTest returns dry_run=true in mock/dry-run mode', function (): void {
    $client = AapanelClient::fromSettings();
    $result = $client->connectionTest();
    expect($result['ok'])->toBeTrue();
    expect($result['dry_run'])->toBeTrue();
});

it('AapanelClient::createSite returns dry_run payload', function (): void {
    $result = AapanelClient::fromSettings()->createSite('example.com', ['php_version' => '82']);
    expect($result['dry_run'])->toBeTrue();
    expect($result['operation'])->toBe('createSite');
});

it('AapanelClient::listSites returns mock sites in dry-run', function (): void {
    $result = AapanelClient::fromSettings()->listSites();
    expect($result['dry_run'])->toBeTrue();
    expect($result['data'])->toBeArray();
    expect(count($result['data']))->toBeGreaterThan(0);
});

it('AapanelClient::getServerHealth returns mock data in dry-run', function (): void {
    $result = AapanelClient::fromSettings()->getServerHealth();
    expect($result)->toHaveKey('dry_run');
    expect($result)->toHaveKey('cpu');
});

it('AapanelClient::configureSsl returns dry_run payload', function (): void {
    $result = AapanelClient::fromSettings()->configureSsl('123');
    expect($result['dry_run'])->toBeTrue();
    expect($result['operation'])->toBe('configureSsl');
});

it('AapanelClient::deleteSite returns dry_run payload', function (): void {
    $result = AapanelClient::fromSettings()->deleteSite('123');
    expect($result['dry_run'])->toBeTrue();
});

it('AapanelClient::setDiskQuota returns dry_run payload', function (): void {
    $result = AapanelClient::fromSettings()->setDiskQuota('123', 5120);
    expect($result['dry_run'])->toBeTrue();
    expect($result['operation'])->toBe('setDiskQuota');
});

it('AapanelClient::getSiteLogs returns dry_run response', function (): void {
    $result = AapanelClient::fromSettings()->getSiteLogs('example.com');
    expect($result)->toHaveKey('dry_run');
    expect($result)->toHaveKey('logs');
});

// ── WEDOS WAPI client ─────────────────────────────────────────────────────────

it('WedosWapiClient::connectionTest returns dry_run=true', function (): void {
    $result = WedosWapiClient::fromSettings()->connectionTest();
    expect($result['ok'])->toBeTrue();
    expect($result['dry_run'])->toBeTrue();
});

it('WedosWapiClient::checkDomain returns dry_run payload', function (): void {
    $result = WedosWapiClient::fromSettings()->checkDomain('testdomain.cz');
    expect($result['dry_run'])->toBeTrue();
    expect($result['operation'])->toBe('checkDomain');
});

it('WedosWapiClient::registerDomain returns dry_run payload', function (): void {
    $result = WedosWapiClient::fromSettings()->registerDomain('newdomain.cz');
    expect($result['dry_run'])->toBeTrue();
    expect($result['operation'])->toBe('registerDomain');
});

it('WedosWapiClient::renewDomain returns dry_run payload', function (): void {
    $result = WedosWapiClient::fromSettings()->renewDomain('renew.cz', 1);
    expect($result['dry_run'])->toBeTrue();
    expect($result['operation'])->toBe('renewDomain');
});

it('WedosWapiClient::listDomains returns dry_run payload', function (): void {
    $result = WedosWapiClient::fromSettings()->listDomains();
    expect($result['dry_run'])->toBeTrue();
    expect($result['operation'])->toBe('listDomains');
});

it('WedosWapiClient::getDnsRecords returns dry_run payload', function (): void {
    $result = WedosWapiClient::fromSettings()->getDnsRecords('example.cz');
    expect($result['dry_run'])->toBeTrue();
    expect($result['operation'])->toBe('getDnsRecords');
});

it('WedosWapiClient::deleteDnsRecord returns dry_run payload', function (): void {
    $result = WedosWapiClient::fromSettings()->deleteDnsRecord('example.cz', 42);
    expect($result['dry_run'])->toBeTrue();
    expect($result['operation'])->toBe('deleteDnsRecord');
});

it('WedosWapiClient::upsertDnsRecord returns dry_run payload', function (): void {
    $result = WedosWapiClient::fromSettings()->upsertDnsRecord('example.cz', ['type' => 'A', 'data' => '1.2.3.4']);
    expect($result['dry_run'])->toBeTrue();
    expect($result['operation'])->toBe('upsertDnsRecord');
});

it('WedosWapiClient::updateNameservers returns dry_run payload', function (): void {
    $result = WedosWapiClient::fromSettings()->updateNameservers('example.cz', ['ns1.onhost.cz', 'ns2.onhost.cz']);
    expect($result['dry_run'])->toBeTrue();
    expect($result['operation'])->toBe('updateNameservers');
});

it('WedosWapiClient::setAutoRenew returns dry_run payload', function (): void {
    $result = WedosWapiClient::fromSettings()->setAutoRenew('example.cz', true);
    expect($result['dry_run'])->toBeTrue();
    expect($result['operation'])->toBe('setAutoRenew');
});

it('WedosWapiClient::transferDomain returns dry_run payload', function (): void {
    $result = WedosWapiClient::fromSettings()->transferDomain('transfer.cz', 'authcode123');
    expect($result['dry_run'])->toBeTrue();
    expect($result['operation'])->toBe('transferDomain');
});

// ── Proxmox client ────────────────────────────────────────────────────────────
// Use in-memory IntegrationSetting to avoid encrypted credentials DB interaction

function mockProxmoxSetting(): IntegrationSetting
{
    $s = new IntegrationSetting();
    $s->mock_mode = true;
    $s->is_active = true;
    return $s;
}

function mockPterodactylSetting(): IntegrationSetting
{
    $s = new IntegrationSetting();
    $s->mock_mode = true;
    $s->is_active = true;
    return $s;
}

it('ProxmoxClient::connectionTest returns dry_run in mock mode', function (): void {
    $result = (new ProxmoxClient(mockProxmoxSetting()))->connectionTest();
    expect($result['ok'])->toBeTrue();
    expect($result['dry_run'])->toBeTrue();
});

it('ProxmoxClient::listVMs returns mock data', function (): void {
    $vms = (new ProxmoxClient(mockProxmoxSetting()))->listVMs();
    expect($vms)->toBeArray();
    expect(count($vms))->toBeGreaterThan(0);
    expect($vms[0])->toHaveKey('status');
});

it('ProxmoxClient::startVM returns true in mock mode', function (): void {
    expect((new ProxmoxClient(mockProxmoxSetting()))->startVM(100))->toBeTrue();
});

it('ProxmoxClient::stopVM returns true in mock mode', function (): void {
    expect((new ProxmoxClient(mockProxmoxSetting()))->stopVM(100))->toBeTrue();
});

it('ProxmoxClient::rebootVM returns true in mock mode', function (): void {
    expect((new ProxmoxClient(mockProxmoxSetting()))->rebootVM(100))->toBeTrue();
});

it('ProxmoxClient::getVMStatus returns mock data', function (): void {
    $status = (new ProxmoxClient(mockProxmoxSetting()))->getVMStatus(100);
    expect($status)->toHaveKey('status');
    expect($status['status'])->toBe('running');
});

it('ProxmoxClient::getVMConfig returns mock data', function (): void {
    $config = (new ProxmoxClient(mockProxmoxSetting()))->getVMConfig(100);
    expect($config)->toHaveKey('cores');
    expect($config)->toHaveKey('memory');
});

it('ProxmoxClient::snapshotVM returns true in mock mode', function (): void {
    expect((new ProxmoxClient(mockProxmoxSetting()))->snapshotVM(100, 'snap1', 'test'))->toBeTrue();
});

it('ProxmoxClient::listContainers returns mock LXC data', function (): void {
    $cts = (new ProxmoxClient(mockProxmoxSetting()))->listContainers();
    expect($cts)->toBeArray();
    expect($cts[0]['type'])->toBe('lxc');
});

it('ProxmoxClient::getClusterStatus returns mock node data', function (): void {
    $status = (new ProxmoxClient(mockProxmoxSetting()))->getClusterStatus();
    expect($status)->toHaveKey('nodes');
});

// ── Pterodactyl client ────────────────────────────────────────────────────────

it('PterodactylClient::connectionTest returns dry_run in mock mode', function (): void {
    $result = (new PterodactylClient(mockPterodactylSetting()))->connectionTest();
    expect($result['ok'])->toBeTrue();
    expect($result['dry_run'])->toBeTrue();
});

it('PterodactylClient::listServers returns mock data', function (): void {
    $servers = (new PterodactylClient(mockPterodactylSetting()))->listServers();
    expect($servers)->toBeArray();
    expect(count($servers))->toBeGreaterThan(0);
    expect($servers[0])->toHaveKey('status');
});

it('PterodactylClient::listNodes returns mock nodes', function (): void {
    $nodes = (new PterodactylClient(mockPterodactylSetting()))->listNodes();
    expect($nodes)->toBeArray();
    expect($nodes[0])->toHaveKey('fqdn');
});

it('PterodactylClient::listNests returns mock nest data', function (): void {
    $nests = (new PterodactylClient(mockPterodactylSetting()))->listNests();
    expect($nests)->toBeArray();
    expect($nests[0]['name'])->toContain('Minecraft');
});

it('PterodactylClient::listEggs returns mock egg data', function (): void {
    $eggs = (new PterodactylClient(mockPterodactylSetting()))->listEggs(1);
    expect($eggs)->toBeArray();
    expect($eggs[0])->toHaveKey('docker_image');
});

it('PterodactylClient::listAllocations returns mock allocation', function (): void {
    $allocs = (new PterodactylClient(mockPterodactylSetting()))->listAllocations(1);
    expect($allocs)->toBeArray();
    expect($allocs[0])->toHaveKey('port');
});

it('PterodactylClient::createServer returns mock id', function (): void {
    $result = (new PterodactylClient(mockPterodactylSetting()))->createServer(['name' => 'test', 'user' => 1, 'egg' => 1]);
    expect($result['ok'])->toBeTrue();
    expect((int) $result['id'])->toBeGreaterThan(0);
});

it('PterodactylClient::suspendServer returns true in mock mode', function (): void {
    expect((new PterodactylClient(mockPterodactylSetting()))->suspendServer(1))->toBeTrue();
});

it('PterodactylClient::unsuspendServer returns true in mock mode', function (): void {
    expect((new PterodactylClient(mockPterodactylSetting()))->unsuspendServer(1))->toBeTrue();
});

it('PterodactylClient::deleteServer returns true in mock mode', function (): void {
    expect((new PterodactylClient(mockPterodactylSetting()))->deleteServer(1))->toBeTrue();
});

it('PterodactylClient::createUser returns mock user', function (): void {
    $result = (new PterodactylClient(mockPterodactylSetting()))->createUser(['username' => 'testuser', 'email' => 'test@test.cz']);
    expect($result['ok'])->toBeTrue();
    expect((int) $result['id'])->toBeGreaterThan(0);
});

// ── DriverResolver + Pterodactyl ──────────────────────────────────────────────

it('DriverResolver resolves Pterodactyl to mock driver in mock mode', function (): void {
    // In PROVISIONING_MOCK_MODE=true (default in tests), Pterodactyl should resolve to a driver
    $resolver = app(DriverResolver::class);
    $driver   = $resolver->forDriver(ProvisioningDriver::Pterodactyl);
    // Should not throw — returns AapanelMockDriver as stand-in in mock mode
    expect($driver)->toBeInstanceOf(\App\Domains\Provisioning\Contracts\ProvisioningDriverInterface::class);
});

it('DriverResolver resolves WEDOS via registrar()', function (): void {
    $resolver  = app(DriverResolver::class);
    $registrar = $resolver->registrar();
    expect($registrar)->toBeInstanceOf(\App\Domains\Provisioning\Contracts\DomainRegistrarInterface::class);
});

it('DriverResolver resolves Proxmox to mock driver in mock mode', function (): void {
    $resolver = app(DriverResolver::class);
    $driver   = $resolver->forDriver(ProvisioningDriver::Proxmox);
    expect($driver)->toBeInstanceOf(\App\Domains\Provisioning\Contracts\ProvisioningDriverInterface::class);
});

it('DriverResolver forDriver(AAPanel) returns a valid driver', function (): void {
    $resolver = app(DriverResolver::class);
    $driver   = $resolver->forDriver(ProvisioningDriver::AAPanel);
    expect($driver)->toBeInstanceOf(\App\Domains\Provisioning\Contracts\ProvisioningDriverInterface::class);
});
