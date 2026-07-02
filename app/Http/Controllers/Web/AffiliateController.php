<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Partner\Enums\PartnerStatus;
use App\Domains\Partner\Enums\ReferralStatus;
use App\Domains\Partner\Models\PartnerProfile;
use App\Domains\Partner\Models\PartnerReferral;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Handles affiliate referral links: /ref/{code}
 *
 * On visit:
 *  1. Resolve the partner by referral_code.
 *  2. Create or update a PartnerReferral (Visitor status).
 *  3. Store referral_code in the session (used at registration to link the account).
 *  4. Render the affiliate landing page.
 */
class AffiliateController extends Controller
{
    public function __invoke(Request $request, string $code): View|RedirectResponse
    {
        $profile = PartnerProfile::query()
            ->where('referral_code', strtoupper($code))
            ->where('status', PartnerStatus::Active)
            ->first();

        if ($profile === null) {
            return redirect()->route('front.home')
                ->with('notice', 'Referral kód nebyl nalezen.');
        }

        // Persist a visitor-level referral for analytics; idempotent by ip_hash.
        $ipHash = hash('sha256', $request->ip() . $code);

        PartnerReferral::firstOrCreate(
            ['partner_profile_id' => $profile->id, 'ip_hash' => $ipHash],
            [
                'referral_code'  => $profile->referral_code,
                'landing_url'    => $request->fullUrl(),
                'source_url'     => $request->headers->get('Referer', ''),
                'utm_source'     => $request->query('utm_source', ''),
                'utm_medium'     => $request->query('utm_medium', ''),
                'utm_campaign'   => $request->query('utm_campaign', ''),
                'utm_term'       => $request->query('utm_term', ''),
                'utm_content'    => $request->query('utm_content', ''),
                'user_agent_hash' => hash('sha256', $request->userAgent() ?? ''),
                'first_seen_at'  => now(),
                'status'         => ReferralStatus::Visitor,
            ],
        );

        // Keep the referral code for the duration of the session.
        // It is picked up at Fortify register to link the new account.
        $request->session()->put('partner_referral_code', $profile->referral_code);

        return view('front.affiliate', [
            'profile'      => $profile,
            'partnerName'  => $profile->user->name,
        ]);
    }
}
