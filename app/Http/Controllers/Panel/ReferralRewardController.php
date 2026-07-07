<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Customer\Models\CustomerReferral;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralRewardController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user()->customer;

        $rewards = CustomerReferral::where('referrer_id', $customer->id)
            ->whereNotNull('rewarded_at')
            ->with('referee.user')
            ->orderByDesc('rewarded_at')
            ->get();

        $totalReferrerReward = $rewards->sum('referrer_reward_haler');
        $pendingCount        = CustomerReferral::where('referrer_id', $customer->id)
            ->whereNull('rewarded_at')
            ->count();

        return view('panel.referrals.rewards', compact('rewards', 'totalReferrerReward', 'pendingCount'));
    }
}
