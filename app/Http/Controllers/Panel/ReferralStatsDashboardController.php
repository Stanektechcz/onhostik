<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Partner\Models\PartnerCommission;
use App\Domains\Partner\Models\PartnerProfile;
use App\Domains\Partner\Models\PartnerReferral;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralStatsDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user    = $request->user();
        $profile = PartnerProfile::where('user_id', $user->id)->first();

        $referrals  = collect();
        $commissions = collect();
        $totalEarned = 0;
        $pendingPayout = 0;

        if ($profile !== null) {
            $referrals = PartnerReferral::where('partner_profile_id', $profile->id)
                ->orderByDesc('created_at')
                ->paginate(20);

            $commissions = PartnerCommission::where('partner_profile_id', $profile->id)
                ->orderByDesc('created_at')
                ->get();

            $totalEarned   = $commissions->where('status', 'paid')->sum('amount');
            $pendingPayout = $commissions->where('status', 'pending')->sum('amount');
        }

        return view('panel.referral-stats.index', compact(
            'profile',
            'referrals',
            'commissions',
            'totalEarned',
            'pendingPayout'
        ));
    }
}
