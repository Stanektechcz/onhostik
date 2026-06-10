<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum Locale: string
{
    case Czech   = 'cs';
    case English = 'en';

    public function label(): string
    {
        return match ($this) {
            self::Czech   => 'Čeština',
            self::English => 'English',
        };
    }

    public function flag(): string
    {
        return match ($this) {
            self::Czech   => 'cz',
            self::English => 'gb',
        };
    }

    public static function default(): self
    {
        return self::Czech;
    }
}
