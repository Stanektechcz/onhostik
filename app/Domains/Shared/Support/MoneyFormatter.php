<?php

declare(strict_types=1);

namespace App\Domains\Shared\Support;

use Brick\Money\Money;
use NumberFormatter;

/**
 * Locale-aware money formatting with a graceful fallback when ext-intl
 * is unavailable (e.g. local Windows dev). With intl present the output
 * is identical to Money::formatTo() — production MUST have ext-intl.
 */
final class MoneyFormatter
{
    public static function formatMinor(int $minorAmount, string $currency, ?string $locale = null): string
    {
        return self::format(Money::ofMinor($minorAmount, $currency), $locale);
    }

    public static function format(?Money $money, ?string $locale = null): string
    {
        if ($money === null) {
            return '—';
        }

        $locale ??= app()->getLocale();

        if (class_exists(NumberFormatter::class)) {
            return $money->formatTo($locale);
        }

        $amount = $money->getAmount()->toFloat();

        return match ($money->getCurrency()->getCurrencyCode()) {
            'CZK'   => number_format($amount, 2, ',', ' ') . ' Kč',
            'EUR'   => '€' . number_format($amount, 2, '.', ','),
            'USD'   => '$' . number_format($amount, 2, '.', ','),
            default => number_format($amount, 2, '.', ',') . ' ' . $money->getCurrency()->getCurrencyCode(),
        };
    }
}
