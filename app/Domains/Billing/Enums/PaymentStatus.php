<?php

declare(strict_types=1);

namespace App\Domains\Billing\Enums;

enum PaymentStatus: string
{
    case Pending      = 'pending';
    case Authorized   = 'authorized';
    case Completed    = 'completed';
    case Failed       = 'failed';
    case Cancelled    = 'cancelled';
    case Refunded     = 'refunded';        // reserved for a future real gateway-confirmed refund
    case ManualRefund = 'manual_refund';   // admin-recorded refund — no gateway call was made

    public function label(): string
    {
        return match ($this) {
            self::Pending      => 'Čeká',
            self::Authorized   => 'Autorizována',
            self::Completed    => 'Dokončena',
            self::Failed       => 'Selhala',
            self::Cancelled    => 'Zrušena',
            self::Refunded     => 'Vrácena',
            self::ManualRefund => 'Vráceno manuálně',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending      => 'warning',
            self::Authorized   => 'info',
            self::Completed    => 'success',
            self::Failed       => 'danger',
            self::Cancelled    => 'gray',
            self::Refunded     => 'gray',
            self::ManualRefund => 'warning',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled, self::Refunded, self::ManualRefund], true);
    }
}
