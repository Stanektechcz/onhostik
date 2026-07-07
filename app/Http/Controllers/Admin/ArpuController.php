<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ArpuController extends Controller
{
    public function index(): View
    {
        $now    = Carbon::now();
        $months = collect();

        for ($i = 11; $i >= 0; $i--) {
            $months->push($now->copy()->subMonths($i)->format('Y-m'));
        }

        $isSqlite  = DB::getDriverName() === 'sqlite';
        $monthExpr = $isSqlite
            ? "strftime('%Y-%m', paid_at)"
            : "DATE_FORMAT(paid_at, '%Y-%m')";

        $revenueRaw = DB::table('invoices')
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $now->copy()->subMonths(11)->startOfMonth())
            ->selectRaw("{$monthExpr} as month, SUM(total) as revenue_minor, COUNT(DISTINCT customer_id) as cust_count")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $arpuData = $months->map(function (string $month) use ($revenueRaw): array {
            $row   = $revenueRaw->get($month);
            $rev   = $row ? (int) $row->revenue_minor : 0;
            $custs = $row ? (int) $row->cust_count : 0;
            return [
                'month'       => $month,
                'revenue'     => $rev,
                'customers'   => $custs,
                'arpu'        => $custs > 0 ? (int) round($rev / $custs) : 0,
            ];
        });

        $latestArpu = $arpuData->last()['arpu'] ?? 0;
        $prevArpu   = $arpuData->count() >= 2 ? $arpuData->slice(-2, 1)->first()['arpu'] : 0;
        $growth     = $prevArpu > 0 ? round(($latestArpu - $prevArpu) / $prevArpu * 100, 1) : 0.0;
        $avgArpu    = (int) round($arpuData->avg('arpu'));
        $maxArpu    = $arpuData->max('arpu') ?: 1;

        return view('admin.arpu', compact('arpuData', 'latestArpu', 'prevArpu', 'growth', 'avgArpu', 'maxArpu'));
    }
}
