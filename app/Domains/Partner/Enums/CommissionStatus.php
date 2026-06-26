<?php

declare(strict_types=1);

namespace App\Domains\Partner\Enums;

enum CommissionStatus: string
{
    case Pending   = 'pending';    // hold period, not yet eligible for approval
    case Approved  = 'approved';   // admin approved, eligible for payout
    case Rejected  = 'rejected';   // admin rejected (refund, fraud, etc.)
    case Paid      = 'paid';       // included in a paid payout
    case Cancelled = 'cancelled';  // order refunded or self-referral detected

    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'Čeká',
            self::Approved  => 'Schváleno',
            self::Rejected  => 'Zamítnuto',
            self::Paid      => 'Vyplaceno',
            self::Cancelled => 'Zrušeno',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending   => 'warning',
            self::Approved  => 'success',
            self::Rejected  => 'danger',
            self::Paid      => 'primary',
            self::Cancelled => 'gray',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Paid, self::Rejected, self::Cancelled], true);
    }
}
