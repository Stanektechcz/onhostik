<?php

declare(strict_types=1);

namespace App\Domains\Customer\Enums;

/**
 * A user's role within a customer account (sub-accounts).
 *
 * - Owner: the account holder — full access, including account deletion and
 *   member management (owner-only, always).
 * - Member: an invited login user — services, domains and tickets, but NOT
 *   billing/payments (a "technician").
 * - Accountant: like a member, but additionally may access billing, payments,
 *   invoices and payment methods — for a bookkeeper who should not touch
 *   services or manage members.
 */
enum CustomerRole: string
{
    case Owner      = 'owner';
    case Member     = 'member';
    case Accountant = 'accountant';

    public function label(): string
    {
        return match ($this) {
            self::Owner      => 'Vlastník',
            self::Member     => 'Člen',
            self::Accountant => 'Účetní',
        };
    }

    /** May this role reach billing / payments / invoices? */
    public function canAccessBilling(): bool
    {
        return $this === self::Owner || $this === self::Accountant;
    }

    /**
     * Roles an owner may assign when inviting (owner is not assignable).
     *
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [self::Member, self::Accountant];
    }
}
