<?php

declare(strict_types=1);

namespace App\Http\Controllers\Partner;

use App\Domains\Partner\Enums\CommissionStatus;
use App\Domains\Partner\Enums\ReferralStatus;
use App\Domains\Partner\Models\PartnerProfile;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
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
        $profile     = $this->partnerProfile($request);
        $referralUrl = $profile ? url('/') . '?ref=' . $profile->referral_code : null;

        return view('partner.assets', [
            'referralCode' => $profile?->referral_code,
            'referralUrl'  => $referralUrl,
            // 112: real referral banners (only once the profile is active).
            'banners'      => $referralUrl
                ? app(\App\Domains\Partner\Services\PartnerBannerService::class)->generate($referralUrl)
                : [],
            // These were "MANUAL"/"PŘIPRAVUJEME" placeholders even though the data
            // already existed — show the real values.
            'commissionRate' => $profile?->commission_rate_percent,
            'referralCount'  => $profile ? $profile->referrals()->count() : 0,
            'convertedCount' => $profile
                ? $profile->referrals()->where('status', ReferralStatus::Customer->value)->count()
                : 0,
        ]);
    }

    /**
     * Save the partner's own payout details (audit 111 leftover).
     *
     * The encrypted store (payout_details_encrypted + get/setPayoutDetails)
     * already existed; the page just told partners to email their bank details
     * instead. This wires the form. Details are encrypted at rest and never
     * logged — a payout account is as sensitive as a credential.
     */
    public function updatePayout(Request $request): RedirectResponse
    {
        $profile = $this->partnerProfile($request);
        abort_if($profile === null, 403);

        $validated = $request->validate([
            'payout_method'   => ['required', 'in:bank_transfer,paypal'],
            'account_holder'  => ['required', 'string', 'max:120'],
            'account_number'  => ['required', 'string', 'max:64'],
        ], [
            'account_number.required' => 'Zadejte číslo účtu nebo IBAN.',
        ]);

        $profile->payout_method = $validated['payout_method'];
        $profile->setPayoutDetails([
            'account_holder' => $validated['account_holder'],
            'account_number' => $validated['account_number'],
        ]);
        $profile->save();

        // Log the change WITHOUT the account number — never persist the value.
        activity('partner')
            ->performedOn($profile)
            ->causedBy($request->user())
            ->withProperties(['payout_method' => $validated['payout_method']])
            ->log('partner.payout_details_updated');

        return back()->with('status', 'Výplatní údaje byly uloženy.');
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
