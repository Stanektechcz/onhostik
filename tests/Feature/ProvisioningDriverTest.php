<?php

declare(strict_types=1);

use App\Domains\Provisioning\Drivers\AapanelMockDriver;
use App\Domains\Provisioning\Drivers\AapanelProductionDriver;
use App\Domains\Provisioning\Drivers\WedosMockRegistrar;
use App\Domains\Provisioning\Drivers\WedosProductionRegistrar;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Exceptions\ProvisioningException;
use App\Domains\Provisioning\Models\Server;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\DriverResolver;
use App\Domains\Customer\Models\Customer;
use App\Domains\Products\Models\Product;
use App\Models\User;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Http;

// ──────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────

/** Create a non-mock aaPanel server with fake credentials. */
function realAapanelServer(): Server
{
    return Server::create([
        'name'            => 'TEST-AAP-REAL',
        'driver'          => ProvisioningDriver::AAPanel,
        'api_url'         => 'https://panel.example.com:7800',
        'api_credentials' => ['api_key' => 'test-api-key-abc123'],
        'status'          => 'active',
        'mock_mode'       => false,
    ]);
}

/**
 * Build an in-memory Service for driver tests that do not need DB persistence.
 * The driver only reads label, external_id, resources — no FK needed.
 *
 * @param array<string, mixed> $attributes
 */
function inMemoryService(array $attributes = []): Service
{
    return new Service(array_merge([
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => ServiceStatus::Pending,
        'label'               => 'test.onhost.cz',
        'external_id'         => null,
        'resources'           => [],
    ], $attributes));
}

// ──────────────────────────────────────────────────────────────────
// DriverResolver — mode selection
// ──────────────────────────────────────────────────────────────────

it('resolver returns mock driver when PROVISIONING_MOCK_MODE is true', function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
    config(['provisioning.mock_mode' => true]);

    $resolver = app(DriverResolver::class);
    $server   = Server::where('mock_mode', true)->firstOrFail();
    $user     = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $product  = Product::first();

    $service = Service::create([
        'customer_id'         => $customer->id,
        'product_id'          => $product->id,
        'server_id'           => $server->id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => ServiceStatus::Pending,
        'label'               => 'test-mock.onhost.cz',
    ]);

    expect($resolver->forService($service))->toBeInstanceOf(AapanelMockDriver::class);
});

it('resolver returns production driver when mock_mode is off and server is real', function (): void {
    $this->seed([ProductCatalogSeeder::class]);
    config(['provisioning.mock_mode' => false]);

    $server   = realAapanelServer();
    $user     = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $product  = Product::first();

    $service = Service::create([
        'customer_id'         => $customer->id,
        'product_id'          => $product->id,
        'server_id'           => $server->id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => ServiceStatus::Pending,
        'label'               => 'test-real.onhost.cz',
    ]);

    expect(app(DriverResolver::class)->forService($service))
        ->toBeInstanceOf(AapanelProductionDriver::class);
});

it('resolver returns mock registrar when PROVISIONING_MOCK_MODE is true', function (): void {
    config(['provisioning.mock_mode' => true]);

    expect(app(DriverResolver::class)->registrar())->toBeInstanceOf(WedosMockRegistrar::class);
});

it('resolver returns production registrar when mock_mode is off and credentials are set', function (): void {
    config([
        'provisioning.mock_mode'      => false,
        'provisioning.wedos.user'     => 'admin@example.cz',
        'provisioning.wedos.password' => 'secret-password',
    ]);

    expect(app(DriverResolver::class)->registrar())->toBeInstanceOf(WedosProductionRegistrar::class);
});

it('resolver returns mock registrar when credentials are missing even if mock_mode is off', function (): void {
    config([
        'provisioning.mock_mode'      => false,
        'provisioning.wedos.user'     => '',
        'provisioning.wedos.password' => '',
    ]);

    expect(app(DriverResolver::class)->registrar())->toBeInstanceOf(WedosMockRegistrar::class);
});

// ──────────────────────────────────────────────────────────────────
// AapanelProductionDriver — constructor guard
// ──────────────────────────────────────────────────────────────────

it('aaPanel production driver throws when server has no api_key', function (): void {
    $server = Server::create([
        'name'            => 'BAD-AAP',
        'driver'          => ProvisioningDriver::AAPanel,
        'api_url'         => 'https://panel.example.com:7800',
        'api_credentials' => [],
        'status'          => 'active',
        'mock_mode'       => false,
    ]);

    expect(fn () => new AapanelProductionDriver($server))
        ->toThrow(ProvisioningException::class);
});

// ──────────────────────────────────────────────────────────────────
// AapanelProductionDriver — testConnection
// ──────────────────────────────────────────────────────────────────

it('aaPanel production driver testConnection returns true on valid panel response', function (): void {
    Http::fake([
        '*' => Http::response(['func_request' => 1], 200),
    ]);

    expect((new AapanelProductionDriver(realAapanelServer()))->testConnection())->toBeTrue();
});

it('aaPanel production driver testConnection returns false on HTTP error', function (): void {
    Http::fake([
        '*' => Http::response([], 500),
    ]);

    expect((new AapanelProductionDriver(realAapanelServer()))->testConnection())->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────
// AapanelProductionDriver — create
// ──────────────────────────────────────────────────────────────────

it('aaPanel create returns failure when allow_real_writes gate is closed', function (): void {
    config(['provisioning.aapanel.allow_real_writes' => false]);

    $result = (new AapanelProductionDriver(realAapanelServer()))
        ->create(inMemoryService());

    expect($result->success)->toBeFalse()
        ->and($result->errorMessage)->toContain('AAPANEL_ALLOW_REAL_WRITES');
});

it('aaPanel create is idempotent when external_id is already set', function (): void {
    Http::fake(); // must NOT be called

    $result = (new AapanelProductionDriver(realAapanelServer()))
        ->create(inMemoryService(['external_id' => '999']));

    expect($result->success)->toBeTrue()
        ->and($result->externalId)->toBe('999')
        ->and($result->metadata['idempotent'])->toBeTrue();

    Http::assertNothingSent();
});

it('aaPanel create provisions a new site when gate is open', function (): void {
    config(['provisioning.aapanel.allow_real_writes' => true]);

    Http::fake([
        '*' => Http::response(['status' => 1, 'siteId' => 77, 'msg' => 'OK'], 200),
    ]);

    $result = (new AapanelProductionDriver(realAapanelServer()))
        ->create(inMemoryService());

    expect($result->success)->toBeTrue()
        ->and($result->externalId)->toBe('77')
        ->and($result->credentials)->toHaveKey('ftp_password');
});

it('aaPanel create recovers orphaned site when aaPanel reports already exists', function (): void {
    config(['provisioning.aapanel.allow_real_writes' => true]);

    // First call: AddSite → already exists; second call: getData → returns site list
    Http::fakeSequence()
        ->push(['status' => 0, 'msg' => 'Domain already exists', 'siteId' => null])
        ->push(['status' => 1, 'data' => [['name' => 'test.onhost.cz', 'id' => 55]]]);

    $result = (new AapanelProductionDriver(realAapanelServer()))
        ->create(inMemoryService(['label' => 'test.onhost.cz']));

    expect($result->success)->toBeTrue()
        ->and($result->externalId)->toBe('55');
});

// ──────────────────────────────────────────────────────────────────
// WedosProductionRegistrar — constructor guard
// ──────────────────────────────────────────────────────────────────

it('WEDOS production registrar throws when credentials are missing', function (): void {
    config(['provisioning.wedos.user' => '', 'provisioning.wedos.password' => '']);

    expect(fn () => new WedosProductionRegistrar())
        ->toThrow(ProvisioningException::class);
});

// ──────────────────────────────────────────────────────────────────
// WedosProductionRegistrar — checkDomain
// ──────────────────────────────────────────────────────────────────

it('WEDOS registrar checkDomain returns available when avail=1', function (): void {
    config([
        'provisioning.wedos.user'      => 'admin@example.cz',
        'provisioning.wedos.password'  => 'secret',
        'provisioning.wedos.test_mode' => true,
    ]);

    Http::fake([
        '*' => Http::response([
            'response' => [
                'code'   => 1000,
                'result' => 'OK',
                'data'   => [
                    'commands' => [
                        'domain-check' => [
                            'code'   => 1000,
                            'result' => 'OK',
                            'data'   => ['avail' => 1, 'name' => 'example.cz'],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $result = (new WedosProductionRegistrar())->checkDomain('example.cz');

    expect($result->available)->toBeTrue()
        ->and($result->fqdn)->toBe('example.cz');
});

it('WEDOS registrar checkDomain returns unavailable when avail=0', function (): void {
    config([
        'provisioning.wedos.user'     => 'admin@example.cz',
        'provisioning.wedos.password' => 'secret',
    ]);

    Http::fake([
        '*' => Http::response([
            'response' => [
                'code'   => 1000,
                'result' => 'OK',
                'data'   => [
                    'commands' => [
                        'domain-check' => [
                            'code'   => 1000,
                            'result' => 'OK',
                            'data'   => ['avail' => 0, 'name' => 'taken.cz'],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $result = (new WedosProductionRegistrar())->checkDomain('taken.cz');

    expect($result->available)->toBeFalse()
        ->and($result->reason)->toBe('taken');
});

it('WEDOS registrar checkDomain returns unavailable on connection failure', function (): void {
    config([
        'provisioning.wedos.user'     => 'admin@example.cz',
        'provisioning.wedos.password' => 'secret',
    ]);

    Http::fake([
        '*' => Http::response([], 503),
    ]);

    $result = (new WedosProductionRegistrar())->checkDomain('example.cz');

    expect($result->available)->toBeFalse()
        ->and($result->reason)->toBe('check_failed');
});

// ──────────────────────────────────────────────────────────────────
// WedosProductionRegistrar — registerDomain
// ──────────────────────────────────────────────────────────────────

it('WEDOS registrar registerDomain returns failure when gate is closed', function (): void {
    config([
        'provisioning.wedos.user'              => 'admin@example.cz',
        'provisioning.wedos.password'          => 'secret',
        'provisioning.wedos.allow_real_writes' => false,
    ]);

    $result = (new WedosProductionRegistrar())->registerDomain('newdomain.cz');

    expect($result->success)->toBeFalse()
        ->and($result->errorMessage)->toContain('WAPI_ALLOW_REAL_WRITES');
});

it('WEDOS registrar registerDomain succeeds when gate is open and WAPI returns ok', function (): void {
    config([
        'provisioning.wedos.user'              => 'admin@example.cz',
        'provisioning.wedos.password'          => 'secret',
        'provisioning.wedos.test_mode'         => true,
        'provisioning.wedos.allow_real_writes' => true,
    ]);

    Http::fake([
        '*' => Http::response([
            'response' => [
                'code'   => 1000,
                'result' => 'OK',
                'data'   => [
                    'commands' => [
                        'domain-create' => [
                            'code'   => 1000,
                            'result' => 'OK',
                            'data'   => ['id' => 12345],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $result = (new WedosProductionRegistrar())->registerDomain('newdomain.cz', [
        'nameservers' => ['ns1.onhost.cz', 'ns2.onhost.cz'],
    ]);

    expect($result->success)->toBeTrue()
        ->and($result->externalId)->toBe('12345')
        ->and($result->metadata['registrar'])->toBe('wedos')
        ->and($result->metadata['test_mode'])->toBeTrue();
});
