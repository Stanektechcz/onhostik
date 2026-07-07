<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RevenueByCountryController extends Controller
{
    public function index(): View
    {
        $rows = DB::table('invoices as i')
            ->join('customers as c', 'c.id', '=', 'i.customer_id')
            ->where('i.status', 'paid')
            ->whereNotNull('i.paid_at')
            ->selectRaw('c.country_code, SUM(i.total) as revenue_minor, COUNT(DISTINCT i.customer_id) as customer_count, COUNT(i.id) as invoice_count')
            ->groupBy('c.country_code')
            ->orderByDesc('revenue_minor')
            ->get();

        $totalRevenue = max(1, $rows->sum('revenue_minor'));

        return view('admin.revenue-by-country', compact('rows', 'totalRevenue'));
    }
}
