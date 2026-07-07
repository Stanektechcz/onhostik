<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VatSummaryController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) $request->get('year', now()->year);

        $monthExpr = match (config('database.default')) {
            'sqlite' => "strftime('%m', paid_at)",
            default  => 'MONTH(paid_at)',
        };

        $rows = DB::table('invoices')
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereRaw(
                config('database.default') === 'sqlite'
                    ? "strftime('%Y', paid_at) = ?"
                    : 'YEAR(paid_at) = ?',
                [$year]
            )
            ->selectRaw("{$monthExpr} as month, vat_scenario, SUM(tax_amount) as tax_minor, SUM(total) as total_minor, COUNT(*) as invoice_count")
            ->groupBy('month', 'vat_scenario')
            ->orderBy('month')
            ->orderBy('vat_scenario')
            ->get();

        $byMonth = [];
        foreach ($rows as $row) {
            $byMonth[(int) $row->month][$row->vat_scenario] = $row;
        }

        $scenarios = $rows->pluck('vat_scenario')->unique()->sort()->values()->all();

        $totals = DB::table('invoices')
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereRaw(
                config('database.default') === 'sqlite'
                    ? "strftime('%Y', paid_at) = ?"
                    : 'YEAR(paid_at) = ?',
                [$year]
            )
            ->selectRaw('vat_scenario, SUM(tax_amount) as tax_minor, SUM(total) as total_minor, COUNT(*) as invoice_count')
            ->groupBy('vat_scenario')
            ->get()
            ->keyBy('vat_scenario');

        $availableYears = DB::table('invoices')
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->selectRaw(config('database.default') === 'sqlite' ? "strftime('%Y', paid_at) as y" : 'YEAR(paid_at) as y')
            ->groupBy('y')
            ->orderByDesc('y')
            ->pluck('y')
            ->all();

        return view('admin.vat-summary', compact('byMonth', 'scenarios', 'totals', 'year', 'availableYears'));
    }
}
