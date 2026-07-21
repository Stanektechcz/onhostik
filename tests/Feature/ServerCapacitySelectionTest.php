<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Server;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\ServerSelector;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Capacity-aware server selection (audit E69).
 *
 * The old rule was orderByDesc('is_default'), which piled every new service
 * onto the default server while others sat idle.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, IntegrationSeeder::class]);
    Server::query()->delete(); // control the fleet precisely
});

function makeServer(array $attributes = []): Server
{
    return Server::create(array_merge([
        'name'             => 'srv-' . uniqid(),
        'driver'           => ProvisioningDriver::AAPanel,
        'api_url'          => 'https://panel.test',
        'status'           => 'active',
        'max_services'     => 10,
        'current_services' => 0,
        'is_default'       => false,
        'mock_mode'        => true,
    ], $attributes));
}

function fillServer(Server $server, int $count, ServiceStatus $status = ServiceStatus::Active): void
{
    Service::factory()->count($count)->create([
        'server_id'           => $server->id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => $status,
    ]);
}

it('returns null when no server matches the driver', function (): void {
    makeServer(['driver' => ProvisioningDriver::Proxmox]);

    expect(app(ServerSelector::class)->pick(ProvisioningDriver::AAPanel))->toBeNull();
});

it('skips servers that are not active', function (): void {
    makeServer(['status' => 'maintenance']);
    $healthy = makeServer(['name' => 'healthy']);

    expect(app(ServerSelector::class)->pick(ProvisioningDriver::AAPanel)?->id)->toBe($healthy->id);
});

it('picks the server with the most free capacity, not the default one', function (): void {
    $default = makeServer(['name' => 'default', 'is_default' => true, 'max_services' => 10]);
    $spare   = makeServer(['name' => 'spare', 'max_services' => 10]);

    fillServer($default, 9); // nearly full
    fillServer($spare, 1);

    // This is the regression: the default server used to win regardless.
    expect(app(ServerSelector::class)->pick(ProvisioningDriver::AAPanel)?->id)->toBe($spare->id);
});

it('prefers the default server when capacity is equal', function (): void {
    $other   = makeServer(['name' => 'other']);
    $default = makeServer(['name' => 'default', 'is_default' => true]);

    expect(app(ServerSelector::class)->pick(ProvisioningDriver::AAPanel)?->id)->toBe($default->id);
});

it('treats a null max_services as unlimited capacity', function (): void {
    $limited   = makeServer(['name' => 'limited', 'max_services' => 10]);
    $unlimited = makeServer(['name' => 'unlimited', 'max_services' => null]);

    fillServer($limited, 1);

    expect(app(ServerSelector::class)->pick(ProvisioningDriver::AAPanel)?->id)->toBe($unlimited->id);
});

it('does not count terminated services against capacity', function (): void {
    $recycled = makeServer(['name' => 'recycled', 'max_services' => 5]);
    $fresh    = makeServer(['name' => 'fresh', 'max_services' => 5]);

    fillServer($recycled, 5, ServiceStatus::Terminated); // all freed up
    fillServer($fresh, 3);

    expect(app(ServerSelector::class)->pick(ProvisioningDriver::AAPanel)?->id)->toBe($recycled->id);
});

it('ignores the drifting current_services counter', function (): void {
    // current_services is incremented on provision but never decremented on
    // termination, so it must not be what capacity is judged on.
    $stale = makeServer(['name' => 'stale', 'max_services' => 10, 'current_services' => 999]);

    expect(app(ServerSelector::class)->pick(ProvisioningDriver::AAPanel)?->id)->toBe($stale->id);
});

it('still returns a server when every one is full rather than failing the order', function (): void {
    $full = makeServer(['name' => 'full', 'max_services' => 2]);
    fillServer($full, 5); // over capacity

    // Refusing to provision a paid order would be worse than a tight server;
    // the selector logs a warning instead.
    expect(app(ServerSelector::class)->pick(ProvisioningDriver::AAPanel)?->id)->toBe($full->id);
});
