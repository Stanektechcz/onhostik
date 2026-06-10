<?php

declare(strict_types=1);

namespace App\Domains\Billing\Enums;

enum InvoiceType: string
{
    case Proforma   = 'proforma';     // zálohová faktura (není daňový doklad)
    case Invoice    = 'invoice';      // daňový doklad
    case CreditNote = 'credit_note';  // dobropis

    public function label(): string
    {
        return match ($this) {
            self::Proforma   => 'Zálohová faktura',
            self::Invoice    => 'Daňový doklad',
            self::CreditNote => 'Dobropis',
        };
    }

    public function isTaxDocument(): bool
    {
        return $this !== self::Proforma;
    }
}
