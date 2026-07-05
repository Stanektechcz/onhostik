<?php

declare(strict_types=1);

namespace App\Domains\Dns\Enums;

enum DnsRecordType: string
{
    case A     = 'A';
    case AAAA  = 'AAAA';
    case CNAME = 'CNAME';
    case MX    = 'MX';
    case TXT   = 'TXT';
    case NS    = 'NS';
    case SRV   = 'SRV';
    case CAA   = 'CAA';
    case PTR   = 'PTR';

    /** Returns true for types that support priority field. */
    public function hasPriority(): bool
    {
        return match ($this) {
            self::MX, self::SRV => true,
            default             => false,
        };
    }

    public function label(): string
    {
        return $this->value;
    }
}
