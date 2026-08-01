<?php

declare(strict_types=1);

namespace App\Domains\Customer\Enums;

/**
 * A user's role within a customer account (sub-accounts).
 *
 * Owner: the account holder — full access, including billing, payment methods
 * and account deletion. Member: an invited login user — everything except the
 * owner-only areas (see the `manage-billing` gate).
 */
enum CustomerRole: string
{
    case Owner  = 'owner';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner  => 'Vlastník',
            self::Member => 'Člen',
        };
    }
}
