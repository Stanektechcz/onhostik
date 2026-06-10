<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum Currency: string
{
    case CZK = 'CZK';
    case EUR = 'EUR';
    case USD = 'USD';

    public function symbol(): string
    {
        return match ($this) {
            self::CZK => 'Kč',
            self::EUR => '€',
            self::USD => '$',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CZK => 'Česká koruna',
            self::EUR => 'Euro',
            self::USD => 'Americký dolar',
        };
    }

    /** Default decimal places for display. */
    public function decimals(): int
    {
        return 2;
    }

    public static function default(): self
    {
        return self::CZK;
    }
}
