<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Enums;

/**
 * Verdict of reconciling a local Service against its backend panel.
 *
 * The important one is MissingRemote: the customer paid and the service
 * looks Active locally, but the site/VM does not exist in the panel.
 */
enum ServiceSyncState: string
{
    case InSync         = 'in_sync';
    case MissingRemote  = 'missing_remote';
    case StatusMismatch = 'status_mismatch';
    case Adopted        = 'adopted';
    case Unsupported    = 'unsupported';
    case Error          = 'error';

    public function label(): string
    {
        return match ($this) {
            self::InSync         => 'Synchronizováno',
            self::MissingRemote  => 'Chybí v panelu',
            self::StatusMismatch => 'Odlišný stav',
            self::Adopted        => 'Dohledáno a spárováno',
            self::Unsupported    => 'Nelze ověřit',
            self::Error          => 'Chyba ověření',
        };
    }

    /** Cuba badge class for the service detail. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::InSync, self::Adopted => 'badge-light-success',
            self::MissingRemote         => 'badge-light-danger',
            self::StatusMismatch        => 'badge-light-warning',
            self::Unsupported           => 'badge-light-secondary',
            self::Error                 => 'badge-light-danger',
        };
    }

    /** Whether this verdict needs a human to look at it. */
    public function needsAttention(): bool
    {
        return in_array($this, [self::MissingRemote, self::StatusMismatch, self::Error], true);
    }
}
