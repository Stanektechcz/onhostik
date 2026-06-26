<?php

declare(strict_types=1);

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PartnerController extends Controller
{
    /**
     * Referrals / lead list.
     * Real affiliate tracking is a future phase — page shows referral link and empty state.
     */
    public function referrals(Request $request): View
    {
        $user         = $request->user();
        $referralCode = strtoupper(Str::substr(md5((string) $user?->id . 'onhost'), 0, 8));
        $referralUrl  = url('/') . '?ref=' . $referralCode;

        return view('partner.referrals', [
            'referralCode' => $referralCode,
            'referralUrl'  => $referralUrl,
            'referrals'    => collect(), // stub — real referral tracking not yet implemented
        ]);
    }

    /**
     * Commissions list.
     * Commission model not yet implemented — shows coming-soon empty state.
     */
    public function commissions(Request $request): View
    {
        return view('partner.commissions', [
            'commissions' => collect(), // stub
        ]);
    }

    /**
     * Payouts list.
     * Manual payouts only — no automatic payout model yet.
     */
    public function payouts(Request $request): View
    {
        return view('partner.payouts', [
            'payouts' => collect(), // stub
        ]);
    }

    /**
     * Partner assets — referral link, promotional materials.
     */
    public function assets(Request $request): View
    {
        $user         = $request->user();
        $referralCode = strtoupper(Str::substr(md5((string) $user?->id . 'onhost'), 0, 8));
        $referralUrl  = url('/') . '?ref=' . $referralCode;

        return view('partner.assets', [
            'referralCode' => $referralCode,
            'referralUrl'  => $referralUrl,
        ]);
    }

    /**
     * Partner profile & settings.
     */
    public function profile(Request $request): View
    {
        $user     = $request->user();
        $customer = $user?->customer;

        return view('partner.profile', [
            'user'     => $user,
            'customer' => $customer,
        ]);
    }
}
