<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class RevenueForecastController extends Controller
{
    public function index(): View
    {
        // ── Historical: last 12 months of completed payments (in CZK) ─────────
        $historicalRaw = Payment::query()
            ->selectRaw("SUBSTR(created_at, 1, 7) as month, SUM(amount) as total_minor")
            ->where('status', PaymentStatus::Completed->value)
            ->where('created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->groupByRaw("SUBSTR(created_at, 1, 7)")
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        // ── Forecast: open/overdue invoices with future due dates (next 6 months)
        $forecastRaw = Invoice::query()
            ->selectRaw("SUBSTR(due_date, 1, 7) as month, SUM(total) as total_minor")
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value])
            ->where('due_date', '>=', now()->startOfMonth())
            ->where('due_date', '<', now()->addMonths(6)->startOfMonth())
            ->groupByRaw("SUBSTR(due_date, 1, 7)")
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        // Build 12-month historical series
        $historical = $this->buildMonthSeries(12, 0, $historicalRaw);

        // Build 6-month forecast series
        $forecast = $this->buildMonthSeries(6, 0, $forecastRaw, future: true);

        // KPIs
        $last3Avg  = $historical->slice(-3)->avg('czk') ?? 0.0;
        $totalYtd  = $historical
            ->filter(fn ($m) => str_starts_with($m['month'], now()->format('Y')))
            ->sum('czk');
        $forecastSum3 = $forecast->take(3)->sum('czk');

        return view('admin.revenue-forecast', compact(
            'historical',
            'forecast',
            'last3Avg',
            'totalYtd',
            'forecastSum3'
        ));
    }

    /**
     * Build an ordered collection of { month, label, czk } for N months.
     *
     * @param  Collection<string, mixed>  $data  keyed by 'YYYY-MM'
     * @return Collection<int, array{month: string, label: string, czk: float}>
     */
    private function buildMonthSeries(
        int $count,
        int $offsetMonths,
        Collection $data,
        bool $future = false
    ): Collection {
        return collect(range(0, $count - 1))->map(function (int $i) use ($count, $offsetMonths, $data, $future) {
            $date  = $future
                ? now()->addMonths($i + $offsetMonths)->startOfMonth()
                : now()->subMonths($count - 1 - $i)->startOfMonth();
            $key   = $date->format('Y-m');
            $minor = (int) ($data[$key]->total_minor ?? 0);

            return [
                'month' => $key,
                'label' => $date->locale('cs')->isoFormat('MMM YY'),
                'czk'   => round($minor / 100, 2),
            ];
        });
    }
}
