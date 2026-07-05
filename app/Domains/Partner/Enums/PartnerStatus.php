<?php

declare(strict_types=1);

namespace App\Domains\Partner\Enums;

enum PartnerStatus: string
{
    case Pending = 'pending';
    case Active  = 'active';
    case Paused  = 'paused';
    case Banned  = 'banned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Čeká na schválení',
            self::Active  => 'Aktivní',
            self::Paused  => 'Pozastavený',
            self::Banned  => 'Zablokovaný',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Active  => 'success',
            self::Paused  => 'warning',
            self::Banned  => 'danger',
        };
    }

    public function canTrackReferrals(): bool
    {
        return $this === self::Active;
    }
}
