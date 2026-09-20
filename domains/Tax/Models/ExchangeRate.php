<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * One currency on one list of the Czech National Bank: `rate_micro` is CZK × 1 000 000 for `amount` units of the currency.
 *
 * @property string $source
 * @property string $currency
 * @property Carbon $valid_on
 * @property int $amount
 * @property int $rate_micro
 * @property ?Carbon $fetched_at
 */
final class ExchangeRate extends Model
{
    protected static string $idPrefix = 'fx';

    protected $table = 'exchange_rates';

    protected function casts(): array
    {
        return ['valid_on' => 'date', 'amount' => 'integer', 'rate_micro' => 'integer', 'fetched_at' => 'datetime'];
    }

    /** Minor units of the currency (both have two decimals) to CZK haléře, rounded half away from zero — integers only. */
    public static function convert(int $minor, int $rateMicro, int $amount): int
    {
        $numerator = abs($minor) * $rateMicro;
        $denominator = max(1, $amount) * 1_000_000;
        $rounded = intdiv($numerator * 2 + $denominator, $denominator * 2);

        return $minor < 0 ? -$rounded : $rounded;
    }

    public function toCzkMinor(int $minor): int
    {
        return self::convert($minor, $this->rate_micro, $this->amount);
    }

    /** `24.335` — the rate as the bank prints it, with a dot. */
    public function decimal(): string
    {
        return self::decimalOf($this->rate_micro);
    }

    public static function decimalOf(int $rateMicro): string
    {
        $text = rtrim(rtrim(sprintf('%d.%06d', intdiv($rateMicro, 1_000_000), $rateMicro % 1_000_000), '0'), '.');

        return str_contains($text, '.') ? str_pad($text, strpos($text, '.') + 4, '0') : $text.'.000'; // three decimals at least, as on the list
    }
}
