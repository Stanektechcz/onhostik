<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Enums;

enum ServiceStatus: string
{
    case Pending    = 'pending';
    case Active     = 'active';
    case Suspended  = 'suspended';
    case Terminated = 'terminated';
    case Failed     = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending    => 'Čeká na zřízení',
            self::Active     => 'Aktivní',
            self::Suspended  => 'Pozastavena',
            self::Terminated => 'Ukončena',
            self::Failed     => 'Zřízení selhalo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending    => 'warning',
            self::Active     => 'success',
            self::Suspended  => 'danger',
            self::Terminated => 'gray',
            self::Failed     => 'danger',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending    => 'badge bg-warning text-dark',
            self::Active     => 'badge bg-success',
            self::Suspended  => 'badge bg-danger',
            self::Terminated => 'badge bg-secondary',
            self::Failed     => 'badge bg-danger',
        };
    }

    public function isOperational(): bool
    {
        return $this === self::Active;
    }
}
