<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class RevenueComparisonController extends Controller
{
    public function index(): View
    {
        $currentMonth  = Invoice::where('status', InvoiceStatus::Paid)
            ->whereYear('paid_at', now()->year)
            ->whereMonth('paid_at', now()->month)
            ->sum('total');

        $previousMonth = Invoice::where('status', InvoiceStatus::Paid)
            ->whereYear('paid_at', now()->subMonth()->year)
            ->whereMonth('paid_at', now()->subMonth()->month)
            ->sum('total');

        $currentYear = Invoice::where('status', InvoiceStatus::Paid)
            ->whereYear('paid_at', now()->year)
            ->sum('total');

        $previousYear = Invoice::where('status', InvoiceStatus::Paid)
            ->whereYear('paid_at', now()->subYear()->year)
            ->sum('total');

        $momChange = $previousMonth > 0
            ? round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1)
            : null;

        $yoyChange = $previousYear > 0
            ? round((($currentYear - $previousYear) / $previousYear) * 100, 1)
            : null;

        return view('admin.revenue-comparison.index', compact(
            'currentMonth', 'previousMonth', 'currentYear', 'previousYear', 'momChange', 'yoyChange'
        ));
    }
}
