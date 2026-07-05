<?php

declare(strict_types=1);

namespace App\Domains\Dns\Enums;

enum DnsZoneStatus: string
{
    case Pending   = 'pending';
    case Active    = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'Čeká na aktivaci',
            self::Active    => 'Aktivní',
            self::Suspended => 'Pozastavená',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending   => 'warning',
            self::Active    => 'success',
            self::Suspended => 'danger',
        };
    }
}
