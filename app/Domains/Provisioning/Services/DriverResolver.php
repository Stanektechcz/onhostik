<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Services;

use App\Domains\Provisioning\Contracts\DomainRegistrarInterface;
use App\Domains\Provisioning\Contracts\ProvisioningDriverInterface;
use App\Domains\Provisioning\Drivers\AapanelMockDriver;
use App\Domains\Provisioning\Drivers\ProxmoxDriver;
use App\Domains\Provisioning\Drivers\WedosMockRegistrar;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Exceptions\ProvisioningException;
use App\Domains\Provisioning\Models\Service;

/**
 * Maps a service/driver enum to a concrete driver implementation.
 *
 * Phase 2: only MOCK implementations exist. With provisioning.mock_mode
 * disabled this resolver throws — the real aaPanel/WEDOS clients arrive in
 * a later phase, and the Proxmox/Pterodactyl slots stay reserved untouched.
 */
final class DriverResolver
{
    public function forService(Service $service): ProvisioningDriverInterface
    {
        $driver = $service->provisioning_driver
            ?? throw new ProvisioningException('Service has no provisioning driver assigned.', retryable: false);

        return $this->forDriver($driver);
    }

    public function forDriver(ProvisioningDriver $driver): ProvisioningDriverInterface
    {
        // Domains are handled by the registrar contract, not this interface.
        if ($driver === ProvisioningDriver::Wedos) {
            throw new ProvisioningException(
                'WEDOS is a domain registrar — resolve it via registrar().',
                driver: 'wedos',
                retryable: false,
            );
        }

        // Pterodactyl is still a reserved slot.
        if ($driver === ProvisioningDriver::Pterodactyl) {
            throw new ProvisioningException(
                'Pterodactyl driver is not implemented yet.',
                driver: 'pterodactyl',
                retryable: false,
            );
        }

        // Proxmox has a real driver — use it regardless of mock_mode.
        if ($driver === ProvisioningDriver::Proxmox) {
            return app(ProxmoxDriver::class);
        }

        // All other drivers fall back to mock in mock_mode.
        if (!$this->mockMode()) {
            throw new ProvisioningException(
                "Real {$driver->value} driver is not implemented yet — enable PROVISIONING_MOCK_MODE.",
                driver: $driver->value,
                retryable: false,
            );
        }

        return match ($driver) {
            ProvisioningDriver::AAPanel => app(AapanelMockDriver::class),
            default => throw new ProvisioningException(
                "No driver for {$driver->value}.",
                driver: $driver->value,
                retryable: false,
            ),
        };
    }

    public function registrar(): DomainRegistrarInterface
    {
        if (!$this->mockMode() && config('provisioning.wedos.test_mode') !== true) {
            throw new ProvisioningException(
                'Real WEDOS WAPI client is not implemented yet — enable PROVISIONING_MOCK_MODE or WAPI_TEST_MODE.',
                driver: 'wedos',
                retryable: false,
            );
        }

        return app(WedosMockRegistrar::class);
    }

    private function mockMode(): bool
    {
        return (bool) config('provisioning.mock_mode', true);
    }
}
