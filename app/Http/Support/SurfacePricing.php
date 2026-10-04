<?php

declare(strict_types=1);

namespace App\Http\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Onhost\Domain\Tax\CnbRates;
use Onhost\Domain\Tax\Models\ExchangeRate;
use Onhost\Domain\Tax\TaxEngine;
use Onhost\Platform\Errors\DomainError;

/**
 * Audit 2026-10 P1-6 (package C2): the surfaces convert and tax prices with numbers the platform holds, never with the
 * prototype's literals (`CUR` rates 1/25 …, ×1,21).
 *
 *  - `currencies()` is the prototype's `CUR` table: CZK always, every other currency only while the Czech National Bank list
 *    synced by `onhost:fx:sync` (CnbRates, `exchange_rates`) is fresh — a currency without a fresh rate is not offered at all.
 *    Nothing here asks the bank: a public page never waits on it.
 *  - `vat()` is the supplier's standard rate from the active tax rule version (TaxEngine::currentRules); null when no rule
 *    set is active (the surfaces then keep their statutory fallback, logged; no document can be issued without rules).
 */
final class SurfacePricing
{
    /** code => [symbol, decimals, symbol after the amount, Czech name, English name] */
    private const DISPLAY = [
        'CZK' => ['Kč', 0, true, 'Česká koruna', 'Czech koruna'],
        'EUR' => ['€', 2, true, 'Euro', 'Euro'],
        'USD' => ['$', 2, false, 'Americký dolar', 'US dollar'],
        'PLN' => ['zł', 2, true, 'Polský zlotý', 'Polish zloty'],
        'GBP' => ['£', 2, false, 'Britská libra', 'Pound sterling'],
    ];

    /**
     * @return array<string, array{rate:float, sym:string, dec:int, after:bool, name:string, valid_on:?string, source:string}> keyed by the lower-case code
     */
    public static function currencies(bool $cs = true): array
    {
        $out = ['czk' => self::row('CZK', 1.0, null, 'catalogue', $cs)];
        $today = Carbon::now((string) config('onhost.billing.timezone', 'Europe/Prague'));
        $oldest = $today->copy()->subDays(max(1, (int) config('onhost.billing.fx.max_age_days', 7)))->format('Y-m-d');
        foreach (CnbRates::currencies() as $code) {
            if (! isset(self::DISPLAY[$code])) {
                continue;
            }
            $rate = ExchangeRate::query()->where('source', CnbRates::SOURCE)->where('currency', $code)
                ->whereDate('valid_on', '<=', $today->format('Y-m-d'))->orderByDesc('valid_on')->first();
            if ($rate === null || $rate->valid_on->format('Y-m-d') < $oldest || $rate->rate_micro <= 0) {
                continue; // no fresh list: the currency is not offered rather than converted at a stale or invented rate
            }
            // CUR.rate multiplies a CZK amount: units of the currency per one koruna
            $out[strtolower($code)] = self::row($code, round(max(1, $rate->amount) * 1_000_000 / $rate->rate_micro, 8), $rate->valid_on->format('Y-m-d'), CnbRates::SOURCE, $cs);
        }

        return $out;
    }

    /** @return array{rate:float, percent:array{cs:string, en:string}, version:int}|null */
    public static function vat(): ?array
    {
        try {
            $version = app(TaxEngine::class)->currentRules();
        } catch (DomainError) {
            Log::warning('surface pricing: no active tax rule version, the surfaces fall back to the statutory rate');

            return null;
        }
        $rules = (array) $version->rules;
        $country = (string) data_get($rules, 'supplier.country', 'CZ');
        $percent = data_get($rules, "standard_rates.{$country}");
        if (! is_numeric($percent)) {
            return null;
        }
        $percent = (float) $percent;
        $text = rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');

        return ['rate' => round($percent / 100, 6), 'percent' => ['cs' => str_replace('.', ',', $text), 'en' => $text], 'version' => (int) $version->version];
    }

    /** @return array{rate:float, sym:string, dec:int, after:bool, name:string, valid_on:?string, source:string} */
    private static function row(string $code, float $rate, ?string $validOn, string $source, bool $cs): array
    {
        [$sym, $dec, $after, $nameCs, $nameEn] = self::DISPLAY[$code];

        return ['rate' => $rate, 'sym' => $sym, 'dec' => $dec, 'after' => $after, 'name' => $cs ? $nameCs : $nameEn, 'valid_on' => $validOn, 'source' => $source];
    }
}
