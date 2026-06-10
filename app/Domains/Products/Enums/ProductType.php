<?php

declare(strict_types=1);

namespace App\Domains\Products\Enums;

use App\Domains\Provisioning\Enums\ProvisioningDriver;

enum ProductType: string
{
    case Webhosting  = 'webhosting';
    case Gamehosting = 'gamehosting';
    case Vps         = 'vps';
    case Domain      = 'domain';

    public function label(): string
    {
        return match ($this) {
            self::Webhosting  => 'Webhosting',
            self::Gamehosting => 'Gamehosting',
            self::Vps         => 'Cloud / VPS',
            self::Domain      => 'Doména',
        };
    }

    public function defaultDriver(): ProvisioningDriver
    {
        return match ($this) {
            self::Webhosting  => ProvisioningDriver::AAPanel,
            self::Gamehosting => ProvisioningDriver::Pterodactyl,
            self::Vps         => ProvisioningDriver::Proxmox,
            self::Domain      => ProvisioningDriver::Wedos,
        };
    }
}
