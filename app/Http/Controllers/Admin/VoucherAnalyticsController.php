<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VoucherAnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $totalVouchers  = Voucher::count();
        $activeVouchers = Voucher::where('is_active', true)->count();
        $totalUsage     = Voucher::sum('used_count');

        $typeStats = Voucher::selectRaw('type, count(*) as count, sum(used_count) as total_used')
            ->groupBy('type')
            ->get();

        $topVouchers = Voucher::orderByDesc('used_count')
            ->limit(10)
            ->get();

        return view('admin.voucher-analytics.index', compact(
            'totalVouchers',
            'activeVouchers',
            'totalUsage',
            'typeStats',
            'topVouchers',
        ));
    }
}
