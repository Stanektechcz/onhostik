<?php

declare(strict_types=1);

namespace App\Domains\Partner\Enums;

enum PayoutStatus: string
{
    case Requested  = 'requested';
    case Processing = 'processing';
    case Paid       = 'paid';
    case Rejected   = 'rejected';
    case Cancelled  = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested  => 'Požadavek',
            self::Processing => 'Zpracovává se',
            self::Paid       => 'Vyplaceno',
            self::Rejected   => 'Zamítnuto',
            self::Cancelled  => 'Zrušeno',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Requested  => 'warning',
            self::Processing => 'info',
            self::Paid       => 'success',
            self::Rejected   => 'danger',
            self::Cancelled  => 'gray',
        };
    }
}
