<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\ExchangeRateService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Financial reports: accounts-receivable aging, monthly revenue CSV.
 */
class FinancialReportController extends Controller
{
    /** Aging report — open invoices bucketed by days overdue. */
    public function aging(): View
    {
        $today = now()->toDateString();

        $buckets = [
            'current'  => [],  // not yet overdue
            'd1_30'    => [],  // 1–30 days
            'd31_60'   => [],  // 31–60 days
            'd61_90'   => [],  // 61–90 days
            'd90plus'  => [],  // 90+ days
        ];

        $totals = array_fill_keys(array_keys($buckets), 0);

        Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->with('customer')
            ->orderBy('due_date')
            ->each(function (Invoice $invoice) use (&$buckets, &$totals): void {
                $daysOverdue = $invoice->due_date
                    ? (int) now()->startOfDay()->diffInDays($invoice->due_date, false) * -1
                    : 0;

                $bucket = match (true) {
                    $daysOverdue <= 0 => 'current',
                    $daysOverdue <= 30 => 'd1_30',
                    $daysOverdue <= 60 => 'd31_60',
                    $daysOverdue <= 90 => 'd61_90',
                    default            => 'd90plus',
                };

                $minor = $invoice->total->getMinorAmount()->toInt();
                $buckets[$bucket][] = $invoice;
                $totals[$bucket]    += $minor;
            });

        return view('admin.financial-report.aging', [
            'buckets' => $buckets,
            'totals'  => $totals,
        ]);
    }

    /** Monthly revenue CSV (paid invoices, grouped by year-month + currency). */
    public function exportRevenueCsv(Request $request, ExchangeRateService $fx): StreamedResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        return response()->streamDownload(function () use ($validated, $fx): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'rok',
                'mesic',
                'mena',
                'faktur_celkem',
                'faktur_zaplaceno',
                'celkem_minor',
                'zaplaceno_minor',
                'zaplaceno_czk_minor',
            ]);

            $isSqlite  = DB::getDriverName() === 'sqlite';
            $monthExpr = $isSqlite
                ? "strftime('%Y-%m', paid_at)"
                : "DATE_FORMAT(paid_at, '%Y-%m')";

            $rows = DB::table('invoices')
                ->where('status', InvoiceStatus::Paid->value)
                ->where('purpose', '!=', 'credit_topup')
                ->whereBetween('paid_at', [$validated['from'] . ' 00:00:00', $validated['to'] . ' 23:59:59'])
                ->selectRaw("
                    {$monthExpr} AS ym,
                    currency,
                    COUNT(*) AS cnt,
                    SUM(total) AS total_minor
                ")
                ->groupBy('ym', 'currency')
                ->orderBy('ym')
                ->orderBy('currency')
                ->get();

            foreach ($rows as $row) {
                [$year, $month] = explode('-', (string) $row->ym);
                $rate      = $fx->getLatestRate(\App\Domains\Shared\Enums\Currency::tryFrom($row->currency));
                $czkMinor  = $rate !== null
                    ? (int) round((int) $row->total_minor * $rate)
                    : ($row->currency === 'CZK' ? (int) $row->total_minor : null);

                fputcsv($handle, [
                    $year,
                    $month,
                    $row->currency,
                    $row->cnt,
                    $row->cnt, // all rows in this query are paid
                    (int) $row->total_minor,
                    (int) $row->total_minor,
                    $czkMinor ?? '',
                ]);
            }

            fclose($handle);
        }, "revenue-{$validated['from']}-{$validated['to']}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
