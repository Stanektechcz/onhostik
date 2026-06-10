<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Enums;

enum ProvisioningDriver: string
{
    case AAPanel     = 'aapanel';
    case Wedos       = 'wedos';
    case Proxmox     = 'proxmox';
    case Pterodactyl = 'pterodactyl';

    public function label(): string
    {
        return match ($this) {
            self::AAPanel     => 'AAPanel (webhosting)',
            self::Wedos       => 'WEDOS WAPI (domény)',
            self::Proxmox     => 'Proxmox VE (VPS/cloud)',
            self::Pterodactyl => 'Pterodactyl (gamehosting)',
        };
    }
}
