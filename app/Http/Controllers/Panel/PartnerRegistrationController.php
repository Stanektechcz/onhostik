<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Partner\Enums\PartnerStatus;
use App\Domains\Partner\Models\PartnerProfile;
use App\Domains\Partner\Services\PartnerTierService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PartnerRegistrationController extends Controller
{
    public function __construct(private readonly PartnerTierService $tierService) {}

    public function apply(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $existing = PartnerProfile::where('user_id', $user->id)->first();

        if ($existing !== null) {
            return redirect()
                ->route('partner.dashboard')
                ->with('status', 'Váš partner profil je již aktivní.');
        }

        return view('partner.apply', [
            'user' => $user,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (PartnerProfile::where('user_id', $user->id)->exists()) {
            return redirect()->route('partner.dashboard');
        }

        $validated = $request->validate([
            'payout_method'  => ['required', 'string', 'max:100'],
            'payout_info'    => ['required', 'string', 'max:500'],
            'agree_terms'    => ['accepted'],
        ]);

        // Generate unique referral code based on user name
        $base = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $user->name) ?? '');
        $base = substr($base, 0, 6) ?: 'USER';

        do {
            $code = $base . strtoupper(Str::random(4));
        } while (PartnerProfile::where('referral_code', $code)->exists());

        $profile = PartnerProfile::create([
            'user_id'                  => $user->id,
            'referral_code'            => $code,
            'status'                   => PartnerStatus::Pending,
            'commission_rate_percent'  => 5.0,
            'payout_method'            => $validated['payout_method'],
            'payout_details_encrypted' => \Illuminate\Support\Facades\Crypt::encryptString(
                $validated['payout_info']
            ),
        ]);

        activity('partner')
            ->performedOn($profile)
            ->causedBy($user)
            ->withProperties(['referral_code' => $code])
            ->log('partner.applied');

        return redirect()
            ->route('panel.dashboard')
            ->with('status', 'Vaše přihláška do partnerského programu byla odeslána. Vyrozumíme vás e-mailem.');
    }

    public function requestPayout(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $profile = PartnerProfile::where('user_id', $user->id)
            ->where('status', PartnerStatus::Active->value)
            ->first();

        if ($profile === null) {
            return back()->withErrors(['partner' => 'Nemáte aktivní partner profil.']);
        }

        $approvedMinor = (int) $profile->commissions()
            ->where('status', \App\Domains\Partner\Enums\CommissionStatus::Approved->value)
            ->whereNull('partner_payout_id')
            ->sum('amount');

        $minPayout = (int) config('partner.min_payout_czk', 500) * 100;

        if ($approvedMinor < $minPayout) {
            return back()->withErrors([
                'payout' => 'Minimální výše výplaty je ' . number_format($minPayout / 100, 0, ',', ' ') . ' Kč. Aktuálně máte ' . number_format($approvedMinor / 100, 0, ',', ' ') . ' Kč.',
            ]);
        }

        $payout = \App\Domains\Partner\Models\PartnerPayout::create([
            'partner_profile_id' => $profile->id,
            'amount'             => $approvedMinor,
            'currency'           => 'CZK',
            'status'             => \App\Domains\Partner\Enums\PayoutStatus::Requested,
            'method'             => $profile->payout_method,
            'requested_at'       => now(),
        ]);

        \App\Domains\Partner\Models\PartnerCommission::where('partner_profile_id', $profile->id)
            ->where('status', \App\Domains\Partner\Enums\CommissionStatus::Approved->value)
            ->whereNull('partner_payout_id')
            ->update(['partner_payout_id' => $payout->id]);

        $this->tierService->maybeUpgrade($profile);

        activity('partner')
            ->performedOn($payout)
            ->causedBy($user)
            ->withProperties(['amount' => $approvedMinor, 'currency' => 'CZK'])
            ->log('payout.requested');

        return back()->with('status', 'Žádost o výplatu ' . number_format($approvedMinor / 100, 0, ',', ' ') . ' Kč byla odeslána.');
    }
}
