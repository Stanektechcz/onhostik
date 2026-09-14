<?php

declare(strict_types=1);

namespace Onhost\Platform\Money;

enum Currency: string
{
    case CZK = 'CZK';
    case EUR = 'EUR';

    public function minorUnits(): int
    {
        return 2;
    }

    public static function fromString(string $code): self
    {
        return self::from(strtoupper($code));
    }
}
