<?php

declare(strict_types=1);

namespace App\Domains\Billing\Enums;

/**
 * VAT scenarios per Czech & EU tax law.
 *
 * Resolution logic lives in App\Domains\Billing\Services\VatResolver.
 */
enum VatScenario: string
{
    case CzechB2C          = 'cz_b2c';            // 21 % CZ DPH
    case CzechB2B          = 'cz_b2b';            // 21 % CZ DPH (odpočet na straně zákazníka)
    case EuB2C             = 'eu_b2c';            // OSS — sazba země zákazníka
    case EuB2BReverseCharge = 'eu_b2b_rc';        // 0 % + "reverse charge" klauzule
    case NonEu             = 'non_eu';            // 0 %, mimo působnost DPH

    public function label(): string
    {
        return match ($this) {
            self::CzechB2C           => 'ČR — spotřebitel (21 %)',
            self::CzechB2B           => 'ČR — plátce DPH (21 %)',
            self::EuB2C              => 'EU — spotřebitel (OSS)',
            self::EuB2BReverseCharge => 'EU — reverse charge (0 %)',
            self::NonEu              => 'Mimo EU (0 %)',
        };
    }

    public function invoiceSeries(): InvoiceSeries
    {
        return match ($this) {
            self::CzechB2C, self::CzechB2B => InvoiceSeries::Czech,
            self::EuB2C, self::EuB2BReverseCharge => InvoiceSeries::Eu,
            self::NonEu => InvoiceSeries::International,
        };
    }

    public function requiresReverseChargeNote(): bool
    {
        return $this === self::EuB2BReverseCharge;
    }
}
