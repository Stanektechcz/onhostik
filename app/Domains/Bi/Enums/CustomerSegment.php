<?php

declare(strict_types=1);

namespace App\Domains\Bi\Enums;

enum CustomerSegment: string
{
    case VIP     = 'vip';
    case Healthy = 'healthy';
    case AtRisk  = 'at_risk';
    case Churned = 'churned';

    public function label(): string
    {
        return match ($this) {
            self::VIP     => 'VIP',
            self::Healthy => 'Zdravý',
            self::AtRisk  => 'Ohrožený',
            self::Churned => 'Odchod',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::VIP     => 'primary',
            self::Healthy => 'success',
            self::AtRisk  => 'warning',
            self::Churned => 'danger',
        };
    }
}
