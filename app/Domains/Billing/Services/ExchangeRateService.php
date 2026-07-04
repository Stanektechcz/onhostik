<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\ExchangeRate;
use App\Domains\Shared\Enums\Currency;
use Brick\Money\Money;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Manages exchange rates (CZK conversion) and provides Money conversion helpers.
 *
 * Rates are cached for 1 hour. The service always works with the most recent
 * available rate for a given currency — it never interpolates.
 */
final class ExchangeRateService
{
    private const CACHE_TTL = 3600;

    /** Returns the latest CZK rate for a currency (units of CZK per 1 unit). */
    public function getLatestRate(Currency $currency): ?float
    {
        if ($currency === Currency::CZK) {
            return 1.0;
        }

        return Cache::remember(
            "exchange_rate:{$currency->value}",
            self::CACHE_TTL,
            fn (): ?float => ExchangeRate::where('currency', $currency->value)
                ->orderByDesc('valid_from')
                ->value('rate'),
        );
    }

    /**
     * Persists a new rate for today and invalidates the cache.
     * Uses updateOrCreate so calling twice on the same day is idempotent.
     */
    public function setRate(Currency $currency, float $rate, string $source = 'manual'): ExchangeRate
    {
        if ($currency === Currency::CZK) {
            throw new \InvalidArgumentException('Cannot set exchange rate for the base currency CZK.');
        }

        Cache::forget("exchange_rate:{$currency->value}");

        return ExchangeRate::updateOrCreate(
            [
                'currency'   => $currency->value,
                'valid_from' => now()->toDateString(),
            ],
            [
                'rate'   => $rate,
                'source' => $source,
            ],
        );
    }

    /**
     * Converts a Money value to CZK using the latest available rate.
     *
     * @throws RuntimeException when no rate is configured for the currency.
     */
    public function convertToCzk(Money $money): Money
    {
        $iso = $money->getCurrency()->getCurrencyCode();

        if ($iso === 'CZK') {
            return $money;
        }

        $rate = $this->getLatestRate(Currency::from($iso))
            ?? throw new RuntimeException("No exchange rate configured for {$iso}.");

        $czkMinor = (int) round($money->getMinorAmount()->toFloat() * $rate);

        return Money::ofMinor($czkMinor, 'CZK');
    }

    /** Returns all latest rates as array ['EUR' => 25.30, 'USD' => 23.10].
     * @return array<string, float>
     */
    public function allLatestRates(): array
    {
        $result = [];
        foreach ([Currency::EUR, Currency::USD] as $currency) {
            $rate = $this->getLatestRate($currency);
            if ($rate !== null) {
                $result[$currency->value] = $rate;
            }
        }

        return $result;
    }
}
