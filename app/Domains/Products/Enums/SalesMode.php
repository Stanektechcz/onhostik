<?php

declare(strict_types=1);

namespace App\Domains\Products\Enums;

enum SalesMode: string
{
    case SelfService  = 'self_service';  // fully orderable via checkout
    case ContactOnly  = 'contact_only';  // quote/contact form only
    case ComingSoon   = 'coming_soon';   // not yet available
    case Inactive     = 'inactive';      // disabled, hide from catalog

    public function label(): string
    {
        return match ($this) {
            self::SelfService => 'Objednatelné',
            self::ContactOnly => 'Na poptávku',
            self::ComingSoon  => 'Připravujeme',
            self::Inactive    => 'Neaktivní',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SelfService => 'success',
            self::ContactOnly => 'info',
            self::ComingSoon  => 'warning',
            self::Inactive    => 'gray',
        };
    }

    public function isOrderable(): bool
    {
        return $this === self::SelfService;
    }
}
