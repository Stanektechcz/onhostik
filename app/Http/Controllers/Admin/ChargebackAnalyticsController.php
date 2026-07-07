<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chargeback;
use Illuminate\View\View;

class ChargebackAnalyticsController extends Controller
{
    public function index(): View
    {
        $stats = Chargeback::selectRaw('status, count(*) as count, sum(amount) as total_amount')
            ->groupBy('status')
            ->get();

        $recentChargebacks = Chargeback::with(['customer'])
            ->orderByDesc('received_at')
            ->limit(10)
            ->get();

        $totalCount = Chargeback::count();
        $totalAmount = Chargeback::sum('amount');

        return view('admin.chargeback-analytics.index', compact('stats', 'recentChargebacks', 'totalCount', 'totalAmount'));
    }
}
