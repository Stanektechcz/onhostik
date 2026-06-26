<?php

declare(strict_types=1);

namespace App\Domains\Partner\Enums;

enum ReferralStatus: string
{
    case Visitor    = 'visitor';    // only a cookie/session visit, no account
    case Registered = 'registered'; // created an account
    case Customer   = 'customer';   // placed and paid an order
    case Cancelled  = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Visitor    => 'Návštěvník',
            self::Registered => 'Registrovaný',
            self::Customer   => 'Zákazník',
            self::Cancelled  => 'Zrušeno',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Visitor    => 'secondary',
            self::Registered => 'info',
            self::Customer   => 'success',
            self::Cancelled  => 'danger',
        };
    }
}
