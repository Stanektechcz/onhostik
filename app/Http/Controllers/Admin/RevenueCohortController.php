<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RevenueCohortController extends Controller
{
    public function index(): View
    {
        $isSqlite = config('database.default') === 'sqlite';

        $acquisitionExpr = $isSqlite
            ? "strftime('%Y-%m', c.created_at)"
            : "DATE_FORMAT(c.created_at, '%Y-%m')";
        $revenueExpr = $isSqlite
            ? "strftime('%Y-%m', i.paid_at)"
            : "DATE_FORMAT(i.paid_at, '%Y-%m')";

        $rows = DB::select("
            SELECT {$acquisitionExpr} as cohort_month,
                   {$revenueExpr} as revenue_month,
                   SUM(i.total) as revenue_minor,
                   COUNT(DISTINCT i.customer_id) as customer_count
            FROM invoices i
            JOIN customers c ON c.id = i.customer_id
            WHERE i.status = 'paid'
              AND i.paid_at IS NOT NULL
              AND c.created_at IS NOT NULL
            GROUP BY cohort_month, revenue_month
            ORDER BY cohort_month, revenue_month
        ");

        $cohortData = [];
        foreach ($rows as $row) {
            $cohortData[$row->cohort_month][$row->revenue_month] = [
                'revenue_minor'  => (int) $row->revenue_minor,
                'customer_count' => (int) $row->customer_count,
            ];
        }

        ksort($cohortData);
        $cohorts = array_keys($cohortData);

        $allMonths = [];
        foreach ($cohortData as $cohort => $months) {
            foreach (array_keys($months) as $m) {
                $allMonths[$m] = true;
            }
        }
        ksort($allMonths);
        $allMonths = array_keys($allMonths);

        return view('admin.revenue-cohort', compact('cohortData', 'cohorts', 'allMonths'));
    }
}
