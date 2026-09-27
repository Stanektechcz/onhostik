<?php

declare(strict_types=1);

namespace App\Console\Commands\Forensics;

use Illuminate\Support\Collection;
use Onhost\Platform\Money\Money;
use stdClass;
use Throwable;

/**
 * The arithmetic and the masking of the P1/P2 source of `onhost:forensics:lookback` (TASK-0038): Money only, and an IBAN
 * never leaves the report whole.
 */
final class PayoutChecks
{
    /**
     * P1: how far the payout exceeds the commissions allocated to it. null when covered, false when the currencies cannot
     * be compared (another currency among its commissions, or one the platform does not know).
     *
     * @param  Collection<int, stdClass>  $rows  the payout's allocated sums per currency
     * @return array{amount:Money, allocated:Money, excess:Money}|false|null
     */
    public static function excess(stdClass $payout, Collection $rows): array|false|null
    {
        try {
            $amount = Money::minor((int) $payout->amount_minor, (string) $payout->currency);
            $sum = $rows->reduce(fn (Money $carry, stdClass $r) => $carry->add(Money::minor((int) $r->total, (string) $r->currency)), Money::zero($amount->currency));
        } catch (Throwable) {
            return false;
        }

        return $amount->greaterThan($sum) ? ['amount' => $amount, 'allocated' => $sum, 'excess' => $amount->subtract($sum)] : null;
    }

    public static function normalIban(string $iban): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $iban));
    }

    /** The country and the last four characters: enough to tell accounts apart in a report, not enough to use one. */
    public static function maskIban(string $iban): string
    {
        return strlen($iban) <= 8 ? '…' : substr($iban, 0, 4).'…'.substr($iban, -4);
    }
}
