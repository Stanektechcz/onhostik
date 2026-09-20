<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Carbon;
use Onhost\Domain\Tax\Models\ExchangeRate;
use Throwable;

/**
 * The exchange rate lists of the Czech National Bank (`denni_kurz.txt`, public, no key). The bank publishes a list on working
 * days at 14:30; asked for a day, it answers with the list that is valid for it — the day's own after 14:30, the previous
 * working day's before, Friday's over a weekend. A list is stored under the date it carries, and the rate of a day is the
 * latest list that is not younger than the day. The bank that does not answer never stops a document: the rate is simply
 * not known yet (see `CzkTaxStatement`).
 */
final class CnbRates
{
    public const SOURCE = 'cnb';

    public function __construct(private readonly HttpFactory $http, private readonly CacheRepository $cache) {}

    /**
     * The list text: `18.09.2026 #182`, a header row, then `země|měna|množství|kód|kurz` rows with a decimal comma.
     *
     * @return array{valid_on:string, rates:array<string, array{amount:int, rate_micro:int}>}
     */
    public static function parse(string $body): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($body)) ?: [];
        if (! preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})\b/', (string) ($lines[0] ?? ''), $m) || ! checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            throw new \UnexpectedValueException('The exchange rate list does not start with its date.');
        }
        $rates = [];
        foreach (array_slice($lines, 1) as $line) {
            $cells = explode('|', trim($line));
            if (count($cells) !== 5 || ! preg_match('/^[A-Z]{3}$/', $cells[3]) || ! preg_match('/^\d{1,6}$/', $cells[2]) || ! preg_match('/^\d{1,6}(,\d{1,6})?$/', $cells[4])) {
                continue; // the header row, or something that is not a rate
            }
            [$whole, $fraction] = array_pad(explode(',', $cells[4]), 2, '');
            $micro = (int) $whole * 1_000_000 + (int) str_pad(substr($fraction, 0, 6), 6, '0');
            if ((int) $cells[2] > 0 && $micro > 0) {
                $rates[$cells[3]] = ['amount' => (int) $cells[2], 'rate_micro' => $micro];
            }
        }
        if ($rates === []) {
            throw new \UnexpectedValueException('The exchange rate list holds no rates.');
        }

        return ['valid_on' => sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]), 'rates' => $rates];
    }

    /**
     * Fetches the list valid for a day and stores the currencies the platform sells in. Throws when the bank cannot be read.
     *
     * @return array{valid_on:string, stored:int}
     */
    public function sync(?\DateTimeInterface $day = null): array
    {
        $day = Carbon::instance($day ?? now())->setTimezone((string) config('onhost.billing.timezone', 'Europe/Prague'));
        $response = $this->http->timeout((int) config('onhost.billing.fx.timeout_seconds', 5))->withHeaders(['Accept' => 'text/plain'])
            ->get((string) config('onhost.billing.fx.cnb_url'), ['date' => $day->format('d.m.Y')]);
        if (! $response->successful()) {
            throw new \RuntimeException("The exchange rate list answered HTTP {$response->status()}.");
        }
        $list = self::parse((string) $response->body());
        $stored = 0;
        foreach (self::currencies() as $currency) {
            $rate = $list['rates'][$currency] ?? null;
            if ($rate === null) {
                continue;
            }
            // a list is published once and never changes: what is stored under a date stays (documents were issued at it)
            if (ExchangeRate::query()->where('source', self::SOURCE)->where('currency', $currency)->whereDate('valid_on', $list['valid_on'])->exists()) {
                continue;
            }
            try {
                ExchangeRate::query()->create(['source' => self::SOURCE, 'currency' => $currency, 'valid_on' => $list['valid_on'], 'amount' => $rate['amount'], 'rate_micro' => $rate['rate_micro'], 'fetched_at' => now()]);
                $stored++;
            } catch (UniqueConstraintViolationException) {
                // another worker stored the same list a moment ago
            }
        }

        return ['valid_on' => $list['valid_on'], 'stored' => $stored];
    }

    /**
     * The rate valid for a day: the latest stored list that is not younger than the day and not older than a week before it.
     * When the day's own list is not stored yet, the bank is asked — once in a quarter of an hour, and only where that is allowed
     * (`onhost.billing.fx.fetch`); an answer that does not come is "not known yet", never an error.
     */
    public function rateFor(string $currency, \DateTimeInterface $day): ?ExchangeRate
    {
        $currency = strtoupper($currency);
        $date = Carbon::instance($day)->format('Y-m-d');
        $find = fn () => ExchangeRate::query()->where('source', self::SOURCE)->where('currency', $currency)->whereDate('valid_on', '<=', $date)->orderByDesc('valid_on')->first();
        $row = $find();
        if (($row === null || $row->valid_on->format('Y-m-d') < $date) && (bool) config('onhost.billing.fx.fetch', true) && $this->cache->add("fx:cnb:asked:{$date}", 1, 900)) {
            try {
                $this->sync($day);
            } catch (Throwable) {
                // the bank does not answer: the latest stored list decides, below
            }
            $row = $find();
        }
        $oldest = Carbon::parse($date)->subDays(max(1, (int) config('onhost.billing.fx.max_age_days', 7)))->format('Y-m-d');

        return $row !== null && $row->valid_on->format('Y-m-d') >= $oldest ? $row : null;
    }

    /** @return list<string> the currencies documents are issued in, besides CZK */
    public static function currencies(): array
    {
        return array_values(array_filter(array_map(fn ($c) => strtoupper((string) $c), (array) config('onhost.billing.currencies', [])), fn (string $c) => $c !== 'CZK'));
    }
}
