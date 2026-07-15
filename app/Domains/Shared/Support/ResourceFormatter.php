<?php

declare(strict_types=1);

namespace App\Domains\Shared\Support;

/**
 * Human formatting for pricing-plan resource values shown on the web
 * (pricing cards) and in the panel. The resources JSON stores raw numbers
 * (disk_mb: 5120) — without this the cards render "DISK5120".
 */
final class ResourceFormatter
{
    public static function format(string $key, mixed $value): string
    {
        // Pre-formatted strings ("NVMe SSD", "1–8 jader") pass through.
        if (is_string($value) && !is_numeric($value)) {
            return $value;
        }

        if (is_array($value)) {
            return implode(', ', array_map(strval(...), $value));
        }

        if (!is_numeric($value)) {
            return (string) $value;
        }

        $number = (float) $value;

        return match (true) {
            str_ends_with($key, '_mb') => $number >= 1024
                ? self::trim($number / 1024) . ' GB'
                : self::trim($number) . ' MB',
            str_ends_with($key, '_gb') => self::trim($number) . ' GB',
            str_ends_with($key, '_tb') => self::trim($number) . ' TB',
            default                    => self::trim($number),
        };
    }

    private static function trim(float $number): string
    {
        return rtrim(rtrim(number_format($number, 1, ',', ' '), '0'), ',');
    }
}
