<?php

declare(strict_types=1);

namespace App\Domains\Security\Enums;

enum WafRuleType: string
{
    case IpBlock      = 'ip_block';
    case IpAllow      = 'ip_allow';
    case CountryBlock = 'country_block';
    case RateLimit    = 'rate_limit';

    public function label(): string
    {
        return match ($this) {
            self::IpBlock      => 'Blokovat IP',
            self::IpAllow      => 'Povolit IP',
            self::CountryBlock => 'Blokovat zemi',
            self::RateLimit    => 'Omezit rychlost',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::IpBlock      => 'danger',
            self::IpAllow      => 'success',
            self::CountryBlock => 'warning',
            self::RateLimit    => 'info',
        };
    }

    public function valuePlaceholder(): string
    {
        return match ($this) {
            self::IpBlock, self::IpAllow => '192.168.1.0/24 nebo 1.2.3.4',
            self::CountryBlock           => 'CZ, DE, RU …',
            self::RateLimit              => '100 (požadavků/min)',
        };
    }
}
