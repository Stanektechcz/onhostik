<?php

declare(strict_types=1);

namespace App\Domains\Billing\Enums;

enum CreditTransactionType: string
{
    case Deposit    = 'deposit';
    case Deduction  = 'deduction';
    case Refund     = 'refund';
    case Adjustment = 'adjustment';
    case Bonus      = 'bonus';

    public function label(): string
    {
        return match ($this) {
            self::Deposit    => 'Vklad',
            self::Deduction  => 'Čerpání',
            self::Refund     => 'Vrácení',
            self::Adjustment => 'Korekce (admin)',
            self::Bonus      => 'Bonus',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Deposit    => 'success',
            self::Deduction  => 'danger',
            self::Refund     => 'info',
            self::Adjustment => 'warning',
            self::Bonus      => 'primary',
        };
    }

    /** Sign convention for the ledger: credits positive, debits negative. */
    public function sign(): int
    {
        return match ($this) {
            self::Deposit, self::Refund, self::Bonus => 1,
            self::Deduction => -1,
            self::Adjustment => 0, // sign carried by amount itself
        };
    }
}
