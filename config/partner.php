<?php

declare(strict_types=1);

return [
    /*
     | Name of the HTTP cookie that carries the referral code for
     | up to cookie_days days.
     */
    'cookie_name' => env('PARTNER_COOKIE_NAME', 'onhost_ref'),

    /*
     | How long the referral cookie lives (days).
     */
    'cookie_days' => (int) env('PARTNER_COOKIE_DAYS', 30),

    /*
     | Session key where the current referral code is also stored.
     */
    'session_key' => 'partner_referral_code',

    /*
     | Default commission rate (%) assigned to new partner profiles.
     | Partners can have individual rates overriding this.
     */
    'default_commission_rate_percent' => (float) env('PARTNER_DEFAULT_COMMISSION_RATE', 10.0),

    /*
     | Days after which a pending commission becomes eligible for approval.
     | Acts as a refund-protection hold period.
     */
    'commission_hold_days' => (int) env('PARTNER_COMMISSION_HOLD_DAYS', 14),

    /*
     | Minimum payout amount in the default currency (minor units / haléře).
     | 50000 = 500 CZK.
     */
    'minimum_payout_minor' => (int) env('PARTNER_MIN_PAYOUT_MINOR', 50000),

    /*
     | Block a partner from earning commission on their own account.
     */
    'self_referral_blocked' => (bool) env('PARTNER_SELF_REFERRAL_BLOCKED', true),

    /*
     | Only generate commissions for invoices with these purposes.
     | 'credit_topup' and 'renewal' are excluded by default.
     */
    'commissionable_purposes' => ['order'],

    /*
     | Monthly revenue target for the partner dashboard progress bar.
     | Stored in minor units (haléře) — 1 000 000 = 10 000 CZK.
     */
    'monthly_target_minor' => (int) env('PARTNER_MONTHLY_TARGET_MINOR', 1_000_000),
];
