<?php

declare(strict_types=1);

namespace App\Domains\Billing\Enums;

/**
 * Where a refunded customer's money actually goes.
 *
 * No gateway wired into this system exposes a refund API, so returning money
 * to the original method is necessarily a manual step in the gateway's own
 * dashboard. Recording the choice keeps accounting unambiguous — and makes
 * the operator's remaining task explicit instead of assumed.
 */
enum RefundDestination: string
{
    case Credit         = 'credit';           // deposited to the customer's credit balance (automatic)
    case OriginalMethod = 'original_method';  // returned via the gateway dashboard (manual)

    public function label(): string
    {
        return match ($this) {
            self::Credit         => 'Kredit na účtu zákazníka',
            self::OriginalMethod => 'Zpět na původní platební metodu',
        };
    }

    /** Whether the operator still has to move real money by hand. */
    public function requiresManualAction(): bool
    {
        return $this === self::OriginalMethod;
    }
}
