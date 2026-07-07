<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Reseller\Models\ResellerProfile;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RevenueByResellerController extends Controller
{
    public function index(Request $request): View
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to', now()->toDateString());

        $resellers = ResellerProfile::with('user')
            ->withCount('customers')
            ->get();

        $revenueByReseller = [];

        foreach ($resellers as $reseller) {
            $customerIds = $reseller->customers()->pluck('id');

            $revenue = Invoice::whereIn('customer_id', $customerIds)
                ->where('status', InvoiceStatus::Paid)
                ->whereBetween('paid_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->sum('total');

            $revenueByReseller[] = [
                'reseller' => $reseller,
                'revenue'  => (float) $revenue,
            ];
        }

        usort($revenueByReseller, fn($a, $b) => $b['revenue'] <=> $a['revenue']);

        return view('admin.revenue-by-reseller.index', compact('revenueByReseller', 'from', 'to'));
    }
}
