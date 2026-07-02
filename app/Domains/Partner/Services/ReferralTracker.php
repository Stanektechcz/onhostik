<?php

declare(strict_types=1);

namespace App\Domains\Partner\Services;

use App\Domains\Customer\Models\Customer;
use App\Domains\Partner\Enums\PartnerStatus;
use App\Domains\Partner\Enums\ReferralStatus;
use App\Domains\Partner\Models\PartnerProfile;
use App\Domains\Partner\Models\PartnerReferral;
use App\Models\User;
use Illuminate\Http\Request;

class ReferralTracker
{
    /**
     * Called from HandleReferralCookie middleware on every web request
     * carrying ?ref=CODE. Creates a visitor-level referral record if
     * none exists for this IP hash yet, and stores the code in session.
     *
     * Never throws — referral tracking must never break page loads.
     */
    public function handleVisit(Request $request, string $code): void
    {
        try {
            $profile = PartnerProfile::where('referral_code', $code)
                ->where('status', PartnerStatus::Active->value)
                ->first();

            if ($profile === null) {
                return;
            }

            $ipHash = hash('sha256', $request->ip() ?? '');
            $uaHash = hash('sha256', $request->userAgent() ?? '');

            // Deduplicate: one referral record per partner × IP hash
            $existing = PartnerReferral::where('partner_profile_id', $profile->id)
                ->where('ip_hash', $ipHash)
                ->first();

            if ($existing === null) {
                PartnerReferral::create([
                    'partner_profile_id' => $profile->id,
                    'referral_code'      => $code,
                    'ip_hash'            => $ipHash,
                    'user_agent_hash'    => $uaHash,
                    'source_url'         => $this->safeUrl($request->headers->get('referer')),
                    'landing_url'        => $this->safeUrl($request->fullUrl()),
                    'utm_source'         => $this->utmParam($request, 'utm_source'),
                    'utm_medium'         => $this->utmParam($request, 'utm_medium'),
                    'utm_campaign'       => $this->utmParam($request, 'utm_campaign'),
                    'utm_term'           => $this->utmParam($request, 'utm_term'),
                    'utm_content'        => $this->utmParam($request, 'utm_content'),
                    'first_seen_at'      => now(),
                    'status'             => ReferralStatus::Visitor,
                ]);
            }

            // Always refresh session key so cookie and session stay in sync
            session([config('partner.session_key') => $code]);

        } catch (\Throwable) {
            // Never break page loads over referral tracking errors
        }
    }

    /**
     * Called after Fortify creates a new User (inside or right after the
     * registration transaction). Links the in-session referral code to the
     * newly created user/customer.
     */
    public function linkRegistration(User $user, string $code): void
    {
        $profile = PartnerProfile::where('referral_code', $code)
            ->where('status', PartnerStatus::Active->value)
            ->first();

        if ($profile === null) {
            return;
        }

        // Self-referral guard
        if (config('partner.self_referral_blocked', true) && $profile->user_id === $user->id) {
            return;
        }

        // Only one referral per user (unique constraint on referred_user_id)
        $alreadyReferred = PartnerReferral::where('referred_user_id', $user->id)->exists();
        if ($alreadyReferred) {
            return;
        }

        $ipHash = hash('sha256', request()->ip() ?? '');

        // Find an existing visitor record for this partner + IP, or create one
        $referral = PartnerReferral::where('partner_profile_id', $profile->id)
            ->where('ip_hash', $ipHash)
            ->whereNull('referred_user_id')
            ->first();

        $customer = $user->customer;

        if ($referral !== null) {
            $referral->update([
                'referred_user_id'     => $user->id,
                'referred_customer_id' => $customer?->id,
                'registered_at'        => now(),
                'status'               => ReferralStatus::Registered,
            ]);
        } else {
            // No prior visitor record — create one now (direct registration)
            PartnerReferral::create([
                'partner_profile_id'   => $profile->id,
                'referred_user_id'     => $user->id,
                'referred_customer_id' => $customer?->id,
                'referral_code'        => $code,
                'ip_hash'              => $ipHash,
                'first_seen_at'        => now(),
                'registered_at'        => now(),
                'status'               => ReferralStatus::Registered,
            ]);
        }
    }

    /**
     * Called when a referred customer pays their first order invoice.
     * Marks the referral as converted and returns the referral record
     * so the commission listener can use it.
     */
    public function markConverted(int $customerId): ?PartnerReferral
    {
        $referral = PartnerReferral::where('referred_customer_id', $customerId)
            ->whereIn('status', [
                ReferralStatus::Registered->value,
                ReferralStatus::Visitor->value,
            ])
            ->first();

        if ($referral === null) {
            return null;
        }

        $referral->update([
            'converted_at' => now(),
            'status'       => ReferralStatus::Customer,
        ]);

        return $referral->fresh();
    }

    private function safeUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        // Truncate to 2048 chars — no sensitive params
        return mb_substr(strtok($url, '?'), 0, 2048) ?: null;
    }

    private function utmParam(Request $request, string $param): ?string
    {
        $value = $request->query($param);
        if (!is_string($value) || $value === '') {
            return null;
        }
        return mb_substr($value, 0, 200);
    }
}
