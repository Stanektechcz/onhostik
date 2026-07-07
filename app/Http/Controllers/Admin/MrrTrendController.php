<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MrrTrendController extends Controller
{
    public function index(): View
    {
        $months = collect();
        $now    = Carbon::now();

        // Build 12-month labels (oldest → newest)
        for ($i = 11; $i >= 0; $i--) {
            $months->push($now->copy()->subMonths($i)->format('Y-m'));
        }

        // Aggregate paid invoices per year-month using DB::table() to avoid MoneyCast issues
        $rawData = DB::table('invoices')
            ->where('status', InvoiceStatus::Paid->value)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $now->copy()->subMonths(11)->startOfMonth())
            ->selectRaw("strftime('%Y-%m', paid_at) as month, SUM(total) as mrr_minor, COUNT(*) as invoice_count")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $mrrData = $months->map(function (string $month) use ($rawData): array {
            $row = $rawData->get($month);
            return [
                'month'          => $month,
                'mrr_minor'      => $row ? (int) $row->mrr_minor : 0,
                'invoice_count'  => $row ? (int) $row->invoice_count : 0,
            ];
        });

        $totalLtm  = $mrrData->sum('mrr_minor');
        $avgMonthly = (int) round($totalLtm / 12);
        $lastMonth  = $mrrData->last()['mrr_minor'] ?? 0;
        $prevMonth  = $mrrData->count() >= 2 ? $mrrData->slice(-2, 1)->first()['mrr_minor'] : 0;
        $growth     = $prevMonth > 0 ? round(($lastMonth - $prevMonth) / $prevMonth * 100, 1) : 0.0;

        return view('admin.mrr-trend', [
            'mrrData'    => $mrrData,
            'totalLtm'   => $totalLtm,
            'avgMonthly' => $avgMonthly,
            'lastMonth'  => $lastMonth,
            'growth'     => $growth,
        ]);
    }
}
