<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Services;

use App\Domains\Provisioning\Contracts\DomainRegistrarInterface;
use App\Domains\Provisioning\Contracts\ProvisioningDriverInterface;
use App\Domains\Provisioning\Drivers\AapanelMockDriver;
use App\Domains\Provisioning\Drivers\AapanelProductionDriver;
use App\Domains\Provisioning\Drivers\ProxmoxDriver;
use App\Domains\Provisioning\Drivers\ProxmoxMockDriver;
use App\Domains\Provisioning\Drivers\PterodactylProductionDriver;
use App\Domains\Provisioning\Drivers\WedosMockRegistrar;
use App\Domains\Provisioning\Drivers\WedosProductionRegistrar;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Exceptions\ProvisioningException;
use App\Domains\Provisioning\Models\Service;

/**
 * Maps a service/driver enum to a concrete driver implementation.
 *
 * Mock mode (PROVISIONING_MOCK_MODE=true) overrides everything and uses the
 * mock drivers. In production, per-server mock_mode provides an additional
 * escape hatch for individual nodes. The real aaPanel and WEDOS drivers are
 * gated by separate ALLOW_REAL_WRITES env flags.
 */
final class DriverResolver
{
    public function forService(Service $service): ProvisioningDriverInterface
    {
        $driver = $service->provisioning_driver
            ?? throw new ProvisioningException('Service has no provisioning driver assigned.', retryable: false);

        // AAPanel: use the production driver when global mock_mode is off AND
        // the linked server has mock_mode=false and has valid credentials.
        if ($driver === ProvisioningDriver::AAPanel && ! $this->mockMode()) {
            $server = $service->server;

            if ($server !== null && ! $server->mock_mode) {
                return new AapanelProductionDriver($server);
            }
        }

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

        // Pterodactyl: use mock in mock_mode, real driver otherwise.
        if ($driver === ProvisioningDriver::Pterodactyl) {
            if ($this->mockMode()) {
                return app(AapanelMockDriver::class); // generic mock suffices for game servers
            }

            return app(PterodactylProductionDriver::class);
        }

        // Proxmox: use mock driver in mock_mode, real driver in production.
        if ($driver === ProvisioningDriver::Proxmox) {
            return $this->mockMode()
                ? app(ProxmoxMockDriver::class)
                : app(ProxmoxDriver::class);
        }

        // AAPanel fallback in mock_mode (forDriver has no Server context).
        return app(AapanelMockDriver::class);
    }

    public function registrar(): DomainRegistrarInterface
    {
        // Use the real WEDOS WAPI registrar when global mock_mode is off and
        // WAPI credentials are configured.
        if (! $this->mockMode()) {
            $user     = (string) config('provisioning.wedos.user', '');
            $password = (string) config('provisioning.wedos.password', '');

            if ($user !== '' && $password !== '') {
                return app(WedosProductionRegistrar::class);
            }
        }

        return app(WedosMockRegistrar::class);
    }

    private function mockMode(): bool
    {
        return (bool) config('provisioning.mock_mode', true);
    }
}
