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
    // Audit P195: an unpaid proforma order used to sit in Pending forever.
    // Expired is a TERMINAL state the expiry command actually transitions to —
    // added because it is set, not to fill a gap in the list. (`unpaid` was
    // NOT added: that phantom state was resolved by InvoiceStatus::isOpen(),
    // and `refunded` lives on the Chargeback model, not the order.)
    case Expired    = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending    => 'Čeká na platbu',
            self::Processing => 'Zpracovává se',
            self::Active     => 'Aktivní',
            self::Cancelled  => 'Zrušena',
            self::Fraud      => 'Podvodná',
            self::Expired    => 'Vypršela',
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
            self::Expired    => 'gray',
        };
    }

    /** A terminal state accepts no further transitions. */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Active, self::Cancelled, self::Fraud, self::Expired => true,
            self::Pending, self::Processing => false,
        };
    }
}
