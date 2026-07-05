<?php

declare(strict_types=1);

namespace App\Domains\Compliance\Enums;

enum GdprRequestType: string
{
    case Export   = 'export';
    case Deletion = 'deletion';

    public function label(): string
    {
        return match ($this) {
            self::Export   => 'Export dat',
            self::Deletion => 'Smazání účtu',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Export   => 'info',
            self::Deletion => 'danger',
        };
    }
}
