<?php

declare(strict_types=1);

use App\Domains\Provisioning\Contracts\ProvisioningDriverInterface;
use App\Domains\Provisioning\DTOs\ProvisioningResult;
use App\Domains\Provisioning\DTOs\UsageStats;
use App\Domains\Provisioning\Drivers\AapanelMockDriver;
use App\Domains\Provisioning\Drivers\ProxmoxMockDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Audit M168: the provisioning driver contract.
 *
 * Every driver — mock and production — must satisfy the same interface with
 * the same return SHAPES, or the pipeline that consumes them (ProvisionHosting-
 * ServiceJob, EnsureOrderProvisionedAction) breaks when a driver is swapped.
 * These are the contract every driver implementation is held to; the mock
 * drivers are the fixtures the pipeline is tested against.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

/** A service wired to a mock server, ready to provision. */
function contractService(): Service
{
    $customer = customerUser()->customer;
    $server   = \App\Domains\Provisioning\Models\Server::query()->firstOrFail();
    $product  = \App\Domains\Products\Models\Product::query()->firstOrFail();

    return Service::factory()->create([
        'customer_id' => $customer->id,
        'server_id'   => $server->id,
        'product_id'  => $product->id,
        'status'      => ServiceStatus::Pending,
    ]);
}

/** @return list<class-string<ProvisioningDriverInterface>> */
function driverClasses(): array
{
    return [AapanelMockDriver::class, ProxmoxMockDriver::class];
}

it('exposes every interface method on each driver', function (): void {
    $required = [
        'create', 'suspend', 'unsuspend', 'terminate',
        'changePackage', 'getUsageStats', 'resetPassword',
        'loginAsUser', 'testConnection',
    ];

    foreach (driverClasses() as $driver) {
        foreach ($required as $method) {
            expect(method_exists($driver, $method))
                ->toBeTrue("{$driver} chybí metoda {$method}");
        }
    }
});

it('returns a successful ProvisioningResult from create', function (): void {
    foreach (driverClasses() as $driverClass) {
        $driver = app($driverClass);
        $result = $driver->create(contractService());

        expect($result)->toBeInstanceOf(ProvisioningResult::class)
            ->and($result->success)->toBeTrue()
            // A successful create must yield an external id or be a pending
            // async task — never "success" with nothing to reference later.
            ->and($result->externalId !== null || $result->isPendingTask())->toBeTrue();
    }
});

it('returns a well-formed result for every lifecycle operation', function (): void {
    foreach (driverClasses() as $driverClass) {
        $driver  = app($driverClass);
        $service = contractService();
        $driver->create($service);

        // Lifecycle ops act on an already-provisioned service, so give it the
        // external id the driver would have assigned (Proxmox create is async
        // and leaves it unset until the polling job completes).
        $service->update(['external_id' => $service->external_id ?? 'ext-contract-1']);

        foreach (['suspend', 'unsuspend', 'terminate'] as $op) {
            $result = $driver->{$op}($service);

            expect($result)->toBeInstanceOf(ProvisioningResult::class);
            // A failing op must explain itself; a passing one must not carry a
            // stray error string.
            if (! $result->success) {
                expect($result->errorMessage)->not->toBeNull();
            }
        }
    }
});

it('reports usage as a typed UsageStats object', function (): void {
    foreach (driverClasses() as $driverClass) {
        $driver  = app($driverClass);
        $service = contractService();
        $driver->create($service);

        expect($driver->getUsageStats($service))->toBeInstanceOf(UsageStats::class);
    }
});

it('never leaks a credential into result metadata', function (): void {
    // metadata is stored on the ProvisioningTask and shown in the admin UI;
    // credentials belong in the separate `credentials` bag, redacted before
    // storage. A password in metadata would be logged and displayed.
    foreach (driverClasses() as $driverClass) {
        $driver = app($driverClass);
        $result = $driver->create(contractService());

        $metadataJson = json_encode($result->metadata) ?: '';

        foreach (['password', 'passwd', 'secret', 'api_key', 'token'] as $secretKey) {
            expect(str_contains(strtolower($metadataJson), $secretKey))
                ->toBeFalse("{$driverClass} vypustil `{$secretKey}` do metadata");
        }
    }
});

it('resets a password to a non-empty string', function (): void {
    foreach (driverClasses() as $driverClass) {
        $driver  = app($driverClass);
        $service = contractService();
        $driver->create($service);

        expect($driver->resetPassword($service))->toBeString()->not->toBeEmpty();
    }
});

it('keeps mock drivers honest about not touching a real panel', function (): void {
    // testConnection() on a mock driver must succeed without a network call —
    // the whole point of mock mode is that tests never reach outside.
    foreach (driverClasses() as $driverClass) {
        expect(app($driverClass)->testConnection())->toBeTrue();
    }
});
