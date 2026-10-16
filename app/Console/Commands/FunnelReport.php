<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * W5-6 (docs/compliance/dpia-funnel-telemetry.md, section "Report"): read-only platform funnel per day or ISO week —
 * cart quotes from funnel_daily, placed and paid orders per currency (the market proxy; CZK and EUR never mix). No amounts,
 * no identifier of an organization, user, order or request. Cells under 5 are suppressed, totals add visible cells only.
 *
 * W6-2 (difference attack): every count is first a day cell (day x currency, or day for quotes) with the <5 threshold. A
 * week is the sum of its VISIBLE day cells and gets the threshold again; it is never counted from raw rows, so neither
 * week minus days nor --days N against N+1 reveals a suppressed day.
 *
 * Platformní agregát, W5-6: it reads `orders` through DB::table on purpose, across every tenant — the output carries counts
 * only. It writes nothing, publishes no event and needs no route, migration or config key.
 */
final class FunnelReport extends Command
{
    private const MIN_CELL = 5;

    private const PAID_LABEL = 'paid (bez odečtu refundací)';

    private const SUPPRESSION = 'buňky <5 (včetně 0) potlačené, týden = součet viditelných dnů';

    protected $signature = 'onhost:funnel-report {--days=30 : window in days, 1-366} {--group=day : day|week} {--json : machine-readable output}';

    protected $description = 'Read-only platform funnel (quotes, placed, paid per market) with small-number suppression';

    public function handle(): int
    {
        $daysOption = (string) $this->option('days');
        if (! ctype_digit($daysOption) || (int) $daysOption < 1 || (int) $daysOption > 366) {
            $this->error('--days musí být celé číslo v rozsahu 1-366.');

            return self::FAILURE;
        }
        $group = (string) $this->option('group');
        if (! in_array($group, ['day', 'week'], true)) {
            $this->error('--group musí být day nebo week.');

            return self::FAILURE;
        }
        $days = (int) $daysOption;
        $from = Carbon::now('UTC')->startOfDay()->subDays($days - 1);
        $metrics = (bool) config('onhost.funnel_metrics');

        /** @var array<string, array<string, array{placed: int, paid: int}>> $markets */
        $markets = [];
        foreach (DB::table('orders')->whereNotNull('placed_at')->where('placed_at', '>=', $from)->get(['currency', 'placed_at']) as $row) {
            $key = Carbon::parse((string) $row->placed_at, 'UTC')->toDateString();
            $markets[$key][(string) $row->currency]['placed'] = ($markets[$key][(string) $row->currency]['placed'] ?? 0) + 1;
            $markets[$key][(string) $row->currency]['paid'] ??= 0;
        }
        foreach (DB::table('orders')->whereNotNull('paid_at')->whereNull('cancelled_at')->where('paid_at', '>=', $from)->get(['currency', 'paid_at']) as $row) {
            $key = Carbon::parse((string) $row->paid_at, 'UTC')->toDateString();
            $markets[$key][(string) $row->currency]['paid'] = ($markets[$key][(string) $row->currency]['paid'] ?? 0) + 1;
            $markets[$key][(string) $row->currency]['placed'] ??= 0;
        }

        /** @var array<string, int> $quotes */
        $quotes = [];
        if ($metrics) {
            $rows = DB::table('funnel_daily')->where('date', '>=', $from->toDateString())
                ->groupBy('date')->get(['date', DB::raw('SUM(count) as total')]);
            foreach ($rows as $row) {
                $key = Carbon::parse((string) $row->date, 'UTC')->toDateString();
                $quotes[$key] = ($quotes[$key] ?? 0) + (int) $row->total;
            }
        }

        // W6-2: suppress per day first; a period (day or week) adds only visible day cells and is thresholded again
        /** @var array<string, array<string, array{placed: int, paid: int}>> $visibleMarkets */
        $visibleMarkets = [];
        foreach ($markets as $day => $byCurrency) {
            $key = $this->periodKey(Carbon::parse($day, 'UTC'), $group);
            foreach ($byCurrency as $currency => $counts) {
                $visibleMarkets[$key][$currency]['placed'] = ($visibleMarkets[$key][$currency]['placed'] ?? 0) + (int) $this->cell($counts['placed'])['value'];
                $visibleMarkets[$key][$currency]['paid'] = ($visibleMarkets[$key][$currency]['paid'] ?? 0) + (int) $this->cell($counts['paid'])['value'];
            }
        }
        $markets = $visibleMarkets;
        /** @var array<string, int> $visibleQuotes */
        $visibleQuotes = [];
        foreach ($quotes as $day => $count) {
            $key = $this->periodKey(Carbon::parse($day, 'UTC'), $group);
            $visibleQuotes[$key] = ($visibleQuotes[$key] ?? 0) + (int) $this->cell($count)['value'];
        }
        $quotes = $visibleQuotes;

        $periods = array_unique(array_merge(array_keys($markets), array_keys($quotes)));
        sort($periods);

        $rows = [];
        $quoteRows = [];
        $totals = ['placed' => 0, 'paid' => 0, 'quotes' => 0];
        foreach ($periods as $period) {
            $placedVisible = 0;
            $byCurrency = $markets[$period] ?? [];
            ksort($byCurrency);
            foreach ($byCurrency as $currency => $counts) {
                $placed = $this->cell($counts['placed']);
                $paid = $this->cell($counts['paid']);
                $placedVisible += (int) $placed['value'];
                $totals['placed'] += (int) $placed['value'];
                $totals['paid'] += (int) $paid['value'];
                $rows[] = ['period' => $period, 'market' => (string) $currency, 'placed' => $placed, 'paid' => $paid,
                    'conversion' => $this->ratio($paid, $placed)];
            }
            if ($metrics) {
                $quote = $this->cell($quotes[$period] ?? 0);
                $totals['quotes'] += (int) $quote['value'];
                $quoteRows[] = ['period' => $period, 'quotes' => $quote,
                    'conversion' => $this->ratio($this->cell($placedVisible), $quote)];
            }
        }

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'days' => $days, 'group' => $group, 'timezone' => 'UTC', 'funnel_metrics' => $metrics,
                'note' => 'součty jsou dolní mez', 'suppression' => self::SUPPRESSION, 'paid_label' => self::PAID_LABEL, 'min_cell' => self::MIN_CELL,
                'rows' => $rows, 'quotes' => $metrics ? $quoteRows : null,
                'totals' => ['placed' => $totals['placed'], 'paid' => $totals['paid'], 'quotes' => $metrics ? $totals['quotes'] : null],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line("Funnel platformy za {$days} dní po ".($group === 'week' ? 'týdnech (ISO, pondělí)' : 'dnech').' v UTC — jen čtení; součty jsou dolní mez ('.self::SUPPRESSION.').');
        if (! $metrics) {
            $this->warn('funnel_metrics vypnuto, konverze se nepočítá');
        }
        $this->table(['Období', 'Trh (měna)', 'placed', self::PAID_LABEL, 'paid/placed'], array_map(fn (array $r): array => [
            $r['period'], $r['market'], $this->show($r['placed']), $this->show($r['paid']), $r['conversion'] ?? '-',
        ], $rows));
        if ($metrics) {
            $this->table(['Období', 'poptávky (všechny trhy)', 'placed/poptávky'], array_map(fn (array $r): array => [
                $r['period'], $this->show($r['quotes']), $r['conversion'] ?? '-',
            ], $quoteRows));
        }
        $this->line('Součty (dolní mez): placed '.$totals['placed'].', '.self::PAID_LABEL.' '.$totals['paid'].($metrics ? ', poptávky '.$totals['quotes'] : '').'.');

        return self::SUCCESS;
    }

    private function periodKey(Carbon $at, string $group): string
    {
        return $group === 'week' ? $at->copy()->startOfWeek(Carbon::MONDAY)->toDateString() : $at->toDateString();
    }

    /** @return array{value: int|null, suppressed: bool} */
    private function cell(int $count): array
    {
        return $count < self::MIN_CELL ? ['value' => null, 'suppressed' => true] : ['value' => $count, 'suppressed' => false];
    }

    /**
     * @param  array{value: int|null, suppressed: bool}  $numerator
     * @param  array{value: int|null, suppressed: bool}  $denominator
     */
    private function ratio(array $numerator, array $denominator): ?string
    {
        if ($numerator['value'] === null || $denominator['value'] === null) {
            return null;
        }

        return number_format($numerator['value'] / $denominator['value'] * 100, 1, '.', '').' %';
    }

    /** @param  array{value: int|null, suppressed: bool}  $cell */
    private function show(array $cell): string
    {
        return $cell['value'] === null ? '<5' : (string) $cell['value'];
    }
}
