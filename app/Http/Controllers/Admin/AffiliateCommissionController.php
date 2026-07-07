<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateCommission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AffiliateCommissionController extends Controller
{
    public function index(): View
    {
        $commissions = AffiliateCommission::with('customer')
            ->orderByDesc('created_at')
            ->paginate(30);

        $summary = AffiliateCommission::selectRaw(
            'status, COUNT(*) as count, SUM(commission_haler) as total_haler'
        )->groupBy('status')->pluck('total_haler', 'status');

        return view('admin.affiliate.index', compact('commissions', 'summary'));
    }

    public function approve(AffiliateCommission $commission): RedirectResponse
    {
        abort_if($commission->status !== 'pending', 422);
        $commission->update(['status' => 'approved']);

        return back()->with('status', 'Komise schválena.');
    }

    public function markPaid(AffiliateCommission $commission): RedirectResponse
    {
        abort_if($commission->status !== 'approved', 422);
        $commission->update(['status' => 'paid', 'paid_at' => now()]);

        return back()->with('status', 'Komise označena jako vyplacená.');
    }
}
