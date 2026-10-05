<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax;

/**
 * How VAT is rounded on ONhost's documents (§ 37 of the Czech VAT act; the document's choice, G2).
 *
 *  · VAT is computed from the base ("zdola", § 37 (1)): base × rate, line by line, rounded to the haléř half away from zero; the
 *    recap per rate is the sum of its lines, so the lines, the recap and the total always add up;
 *  · VAT that is extracted from a received amount (a tax document for a received payment, "shora", § 37 (2)) is
 *    amount × rate / (100 + rate), rounded the same way — the base is what is left. Truncating the base (as before) gave a haléř
 *    of VAT too much whenever the fraction of the base was above one half;
 *  · the total is never rounded to whole crowns: every payment is cashless (no haléřové vyrovnání).
 *
 * The method is recorded on every tax document (`meta.rounding`).
 */
final class VatRounding
{
    public const METHOD = 'haler_half_away_from_zero';

    /** VAT of a base in minor units at a rate in percent ("21", "12", "25.5"). */
    public static function taxFromNet(int $netMinor, string $rate): int
    {
        return self::roundHalfAwayFromZero(bcdiv(bcmul((string) $netMinor, self::rate($rate), 8), '100', 8));
    }

    /** VAT contained in an amount that includes it. */
    public static function taxFromGross(int $grossMinor, string $rate): int
    {
        $rate = self::rate($rate);
        if (bccomp($rate, '0', 8) === 0) {
            return 0;
        }

        return self::roundHalfAwayFromZero(bcdiv(bcmul((string) $grossMinor, $rate, 8), bcadd('100', $rate, 8), 8));
    }

    private static function rate(string $rate): string
    {
        $rate = trim($rate);
        if (! preg_match('/^\d{1,3}(\.\d{1,4})?$/', $rate)) {
            throw new \InvalidArgumentException("Not a VAT rate: {$rate}");
        }

        return $rate;
    }

    private static function roundHalfAwayFromZero(string $value): int
    {
        $negative = str_starts_with($value, '-');
        $rounded = bcadd(ltrim($value, '-'), '0.5', 0); // bcadd truncates: |x| + 0.5 truncated is |x| rounded half up

        return (int) ($negative ? '-'.$rounded : $rounded);
    }
}
