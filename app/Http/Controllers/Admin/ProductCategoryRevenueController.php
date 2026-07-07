<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductCategoryRevenueController extends Controller
{
    public function index(): View
    {
        $since = now()->subMonths(11)->startOfMonth();

        // Renewal invoices: invoices.renewal_service_id → services.product_id → products.type
        $renewalRows = DB::table('invoices as i')
            ->join('services as s', 's.id', '=', 'i.renewal_service_id')
            ->join('products as p', 'p.id', '=', 's.product_id')
            ->where('i.status', 'paid')
            ->whereNotNull('i.paid_at')
            ->where('i.paid_at', '>=', $since)
            ->selectRaw('p.type as product_type, SUM(i.total) as revenue_minor, COUNT(DISTINCT i.id) as invoice_count')
            ->groupBy('p.type')
            ->orderByDesc('revenue_minor')
            ->get();

        // Order invoices: invoices.order_id → orders → order_items → pricing_plans → products
        $orderRows = DB::table('invoices as i')
            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->join('pricing_plans as pp', 'pp.id', '=', 'oi.pricing_plan_id')
            ->join('products as p', 'p.id', '=', 'pp.product_id')
            ->where('i.status', 'paid')
            ->whereNotNull('i.paid_at')
            ->where('i.paid_at', '>=', $since)
            ->selectRaw('p.type as product_type, SUM(i.total) as revenue_minor, COUNT(DISTINCT i.id) as invoice_count')
            ->groupBy('p.type')
            ->orderByDesc('revenue_minor')
            ->get();

        $merged = [];
        foreach ([$renewalRows, $orderRows] as $set) {
            foreach ($set as $row) {
                if (! isset($merged[$row->product_type])) {
                    $merged[$row->product_type] = ['revenue_minor' => 0, 'invoice_count' => 0];
                }
                $merged[$row->product_type]['revenue_minor']  += $row->revenue_minor;
                $merged[$row->product_type]['invoice_count']  += $row->invoice_count;
            }
        }
        arsort($merged);

        $rows = collect($merged)->map(fn ($v, $k) => (object) ['product_type' => $k, ...$v]);

        $totalRevenue = max(1, $rows->sum('revenue_minor'));

        return view('admin.product-category-revenue', compact('rows', 'totalRevenue'));
    }
}
