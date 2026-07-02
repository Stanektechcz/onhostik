<?php

declare(strict_types=1);

namespace App\Domains\Billing\Enums;

enum PaymentMethod: string
{
    case Comgate      = 'comgate';
    case Stripe       = 'stripe';
    case GoPay        = 'gopay';
    case BankTransfer = 'bank_transfer';
    case Credit       = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Comgate      => 'Platební brána (Comgate)',
            self::Stripe       => 'Platební karta (Stripe)',
            self::GoPay        => 'Platební brána (GoPay)',
            self::BankTransfer => 'Bankovní převod',
            self::Credit       => 'Kredit (zálohový účet)',
        };
    }

    public function isInstant(): bool
    {
        return $this === self::Credit;
    }
}
