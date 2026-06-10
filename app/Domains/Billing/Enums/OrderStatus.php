<?php

declare(strict_types=1);

namespace App\Domains\Billing\Enums;

enum OrderStatus: string
{
    case Pending    = 'pending';
    case Processing = 'processing';
    case Active     = 'active';
    case Cancelled  = 'cancelled';
    case Fraud      = 'fraud';

    public function label(): string
    {
        return match ($this) {
            self::Pending    => 'Čeká na platbu',
            self::Processing => 'Zpracovává se',
            self::Active     => 'Aktivní',
            self::Cancelled  => 'Zrušena',
            self::Fraud      => 'Podvodná',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending    => 'warning',
            self::Processing => 'info',
            self::Active     => 'success',
            self::Cancelled  => 'gray',
            self::Fraud      => 'danger',
        };
    }
}
