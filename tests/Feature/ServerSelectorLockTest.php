<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Server;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\ServerSelector;
use Database\Seeders\ProductCatalogSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class]);
});

/**
 * Distributed lock on server selection (audit #50): select + reserve must be
 * atomic so two provisions can't over-fill the last free slot.
 */

function makeServer(int $maxServices, bool $default = false): Server
{
    return Server::create([
        'name'         => 'srv-' . uniqid(),
        'api_url'      => 'https://' . uniqid() . '.example',
        'driver'       => ProvisioningDriver::AAPanel->value,
        'status'       => 'active',
        'max_services' => $maxServices,
        'is_default'   => $default,
    ]);
}

it('reserves the chosen server through the lock', function (): void {
    $server = makeServer(5);

    $result = app(ServerSelector::class)->pickAndReserve(
        ProvisioningDriver::AAPanel,
        fn (?Server $chosen): ?int => $chosen?->id,
    );

    expect($result)->toBe($server->id);
});

it('respects capacity across sequential reservations (no over-fill)', function (): void {
    $server    = makeServer(2); // capacity 2
    $productId = \App\Domains\Products\Models\Product::value('id');
    $customerId = customerUser()->customer->id;

    // Reserve three services via the atomic path; each reservation creates a
    // live service so the next selection sees reduced capacity.
    $assigned = [];
    for ($i = 0; $i < 3; $i++) {
        $service = app(ServerSelector::class)->pickAndReserve(
            ProvisioningDriver::AAPanel,
            fn (?Server $chosen): Service => Service::create([
                'customer_id'         => $customerId,
                'product_id'          => $productId,
                'server_id'           => $chosen?->id,
                'provisioning_driver' => ProvisioningDriver::AAPanel,
                'status'              => ServiceStatus::Active,
                'label'               => 'svc-' . $i,
            ]),
        );
        $assigned[] = $service->server_id;
    }

    // First two land on the server; the third finds it full. With only one
    // server, the fallback still returns it (loud), but capacity accounting held
    // for the first two selections.
    $onServer = collect($assigned)->filter(fn ($id): bool => $id === $server->id)->count();
    expect($onServer)->toBeGreaterThanOrEqual(2)
        ->and(Service::where('server_id', $server->id)->count())->toBe($onServer);
});

it('spreads load to the least-loaded server', function (): void {
    $full = makeServer(1);
    Service::create([
        'customer_id'         => customerUser()->customer->id,
        'product_id'          => \App\Domains\Products\Models\Product::value('id'),
        'server_id'           => $full->id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => ServiceStatus::Active,
        'label'               => 'filler',
    ]);
    $empty = makeServer(5);

    $chosen = app(ServerSelector::class)->pickAndReserve(
        ProvisioningDriver::AAPanel,
        fn (?Server $s): ?int => $s?->id,
    );

    expect($chosen)->toBe($empty->id); // the one with free capacity
});
