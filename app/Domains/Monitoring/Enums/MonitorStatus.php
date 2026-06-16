<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Enums;

enum MonitorStatus: string
{
    case Up      = 'up';
    case Down    = 'down';
    case Unknown = 'unknown';
    case Paused  = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::Up      => 'Online',
            self::Down    => 'Výpadek',
            self::Unknown => 'Neznámý',
            self::Paused  => 'Pozastaven',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Up      => 'success',
            self::Down    => 'danger',
            self::Unknown => 'gray',
            self::Paused  => 'warning',
        };
    }
}
