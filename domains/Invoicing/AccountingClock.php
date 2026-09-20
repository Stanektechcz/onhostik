<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The day a document belongs to is the day at the seller's seat — not the day in UTC.
 *
 * The application runs in UTC. The date of taxable supply and the year of the number series were taken from `now()`,
 * so an order paid on 1 January at 00:30 in Prague (23:30 UTC, 31 December) got LAST year's number and a supply date in
 * last year's last VAT period, while the PDF — which already printed the issue date in Prague time — said 1 January. Every
 * document issued between midnight and one or two in the morning carried the previous day.
 *
 * Instants (issued_at, due_at, paid_at) stay what they are; a DATE on a document comes from here.
 */
final class AccountingClock
{
    public static function timezone(): string
    {
        $zone = (string) config('onhost.billing.timezone', 'Europe/Prague');

        return in_array($zone, timezone_identifiers_list(), true) ? $zone : 'Europe/Prague';
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    /** The accounting date (Y-m-d) of an instant; of this moment when none is given. */
    public static function date(?DateTimeInterface $instant = null): string
    {
        return ($instant === null ? self::now() : CarbonImmutable::instance($instant)->setTimezone(self::timezone()))->format('Y-m-d');
    }

    public static function year(?DateTimeInterface $instant = null): int
    {
        return (int) substr(self::date($instant), 0, 4);
    }
}
