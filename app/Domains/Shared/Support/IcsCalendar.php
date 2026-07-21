<?php

declare(strict_types=1);

namespace App\Domains\Shared\Support;

use DateTimeInterface;

/**
 * Minimal RFC 5545 VEVENT builder (audit I129).
 *
 * Hand-rolled rather than pulled from a package: one event with a fixed set of
 * fields is a small, stable slice of the spec, and it keeps a dependency out of
 * the tree for something this size.
 *
 * The fiddly parts of RFC 5545 that actually break calendar clients are all
 * handled: CRLF line endings, 75-octet line folding, and escaping of the
 * characters that otherwise terminate a property value.
 */
final class IcsCalendar
{
    public static function event(
        string $uid,
        string $summary,
        string $description,
        DateTimeInterface $start,
        DateTimeInterface $end,
        ?string $url = null,
    ): string {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//OnHost//Maintenance//CS',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . self::stamp(now()),
            'DTSTART:' . self::stamp($start),
            'DTEND:' . self::stamp($end),
            'SUMMARY:' . self::escape($summary),
            'DESCRIPTION:' . self::escape($description),
            // Maintenance is not a meeting — showing the customer as busy in
            // their own calendar would be wrong.
            'TRANSP:TRANSPARENT',
            'STATUS:CONFIRMED',
        ];

        if ($url !== null) {
            $lines[] = 'URL:' . self::escape($url);
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines)) . "\r\n";
    }

    /** UTC, per spec — local times without a VTIMEZONE block are ambiguous. */
    private static function stamp(DateTimeInterface $moment): string
    {
        return (new \DateTimeImmutable('@' . $moment->getTimestamp()))
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Ymd\THis\Z');
    }

    /** Order matters: the backslash must be escaped before anything else. */
    private static function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $value,
        );
    }

    /**
     * Fold to 75 octets per line, continuation lines starting with a space.
     *
     * Counted in octets, not characters: a Czech maintenance title is full of
     * multi-byte characters, and splitting one down the middle produces a file
     * that some clients refuse outright.
     */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $out       = '';
        $current   = '';
        $maxFirst  = 75;
        $maxOthers = 74; // one octet is spent on the leading space

        foreach (mb_str_split($line) as $char) {
            $limit = $out === '' ? $maxFirst : $maxOthers;

            if (strlen($current) + strlen($char) > $limit) {
                $out .= ($out === '' ? '' : "\r\n ") . $current;
                $current = '';
            }

            $current .= $char;
        }

        return $out . ($out === '' ? '' : "\r\n ") . $current;
    }
}
