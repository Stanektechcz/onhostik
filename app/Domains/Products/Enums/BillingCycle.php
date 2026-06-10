<?php

declare(strict_types=1);

namespace App\Domains\Products\Enums;

enum BillingCycle: string
{
    case Monthly    = 'monthly';
    case Quarterly  = 'quarterly';
    case SemiAnnual = 'semi_annual';
    case Annually   = 'annually';
    case Biennially = 'biennially';

    public function label(): string
    {
        return match ($this) {
            self::Monthly    => 'Měsíčně',
            self::Quarterly  => 'Čtvrtletně',
            self::SemiAnnual => 'Pololetně',
            self::Annually   => 'Ročně',
            self::Biennially => 'Dvouletě',
        };
    }

    public function months(): int
    {
        return match ($this) {
            self::Monthly    => 1,
            self::Quarterly  => 3,
            self::SemiAnnual => 6,
            self::Annually   => 12,
            self::Biennially => 24,
        };
    }
}
