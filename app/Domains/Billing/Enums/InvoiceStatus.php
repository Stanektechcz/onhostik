<?php

declare(strict_types=1);

namespace App\Domains\Billing\Enums;

enum InvoiceStatus: string
{
    case Draft     = 'draft';
    case Sent      = 'sent';
    case Paid      = 'paid';
    case Overdue   = 'overdue';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Koncept',
            self::Sent      => 'Odeslána',
            self::Paid      => 'Zaplacena',
            self::Overdue   => 'Po splatnosti',
            self::Cancelled => 'Stornována',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft     => 'gray',
            self::Sent      => 'info',
            self::Paid      => 'success',
            self::Overdue   => 'danger',
            self::Cancelled => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Sent, self::Overdue], true);
    }
}
