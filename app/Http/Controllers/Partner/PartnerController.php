<?php

declare(strict_types=1);

namespace App\Http\Controllers\Partner;

use App\Domains\Partner\Enums\CommissionStatus;
use App\Domains\Partner\Models\PartnerProfile;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    private function partnerProfile(Request $request): ?PartnerProfile
    {
        $user = $request->user();

        return $user ? PartnerProfile::where('user_id', $user->id)->first() : null;
    }

    public function referrals(Request $request): View
    {
        $profile = $this->partnerProfile($request);

        if ($profile === null) {
            return view('partner.referrals', [
                'referralCode' => null,
                'referralUrl'  => null,
                'referrals'    => collect(),
            ]);
        }

        $referrals = $profile->referrals()
            ->with('referredUser', 'referredCustomer')
            ->latest()
            ->paginate(20);

        return view('partner.referrals', [
            'referralCode' => $profile->referral_code,
            'referralUrl'  => url('/') . '?ref=' . $profile->referral_code,
            'referrals'    => $referrals,
        ]);
    }

    public function commissions(Request $request): View
    {
        $profile = $this->partnerProfile($request);

        if ($profile === null) {
            return view('partner.commissions', [
                'commissions' => collect(),
                'counts'      => ['pending' => 0, 'approved' => 0, 'paid' => 0, 'rejected' => 0],
            ]);
        }

        $counts = [
            'pending'  => (int) $profile->commissions()->where('status', CommissionStatus::Pending->value)->sum('amount'),
            'approved' => (int) $profile->commissions()->where('status', CommissionStatus::Approved->value)->sum('amount'),
            'paid'     => (int) $profile->commissions()->where('status', CommissionStatus::Paid->value)->sum('amount'),
            'rejected' => (int) $profile->commissions()->where('status', CommissionStatus::Rejected->value)->sum('amount'),
        ];

        $commissions = $profile->commissions()
            ->with('invoice', 'order')
            ->latest()
            ->paginate(20);

        return view('partner.commissions', [
            'commissions' => $commissions,
            'counts'      => $counts,
        ]);
    }

    public function payouts(Request $request): View
    {
        $profile = $this->partnerProfile($request);

        $payouts = $profile
            ? $profile->payouts()->latest()->paginate(15)
            : collect();

        return view('partner.payouts', ['payouts' => $payouts]);
    }

    public function assets(Request $request): View
    {
        $profile = $this->partnerProfile($request);

        return view('partner.assets', [
            'referralCode' => $profile?->referral_code,
            'referralUrl'  => $profile ? url('/') . '?ref=' . $profile->referral_code : null,
        ]);
    }

    public function profile(Request $request): View
    {
        $user    = $request->user();
        $profile = $this->partnerProfile($request);

        return view('partner.profile', [
            'user'           => $user,
            'customer'       => $user?->customer,
            'partnerProfile' => $profile,
        ]);
    }
}
