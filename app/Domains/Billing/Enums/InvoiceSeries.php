<?php

declare(strict_types=1);

namespace App\Domains\Billing\Enums;

/**
 * Invoice numbering series.
 *
 * Format: {PREFIX}-{YYYY}-{NNNNNN}, e.g. CZ-2026-000001
 */
enum InvoiceSeries: string
{
    case Czech         = 'CZ';
    case Eu            = 'EU';
    case International = 'INT';
    case CreditNote    = 'CN';

    public function label(): string
    {
        return match ($this) {
            self::Czech         => 'Tuzemsko (CZK)',
            self::Eu            => 'EU (EUR)',
            self::International => 'Mimo EU (EUR/USD)',
            self::CreditNote    => 'Dobropis',
        };
    }

    public function format(int $year, int $number): string
    {
        return sprintf('%s-%d-%06d', $this->value, $year, $number);
    }
}
