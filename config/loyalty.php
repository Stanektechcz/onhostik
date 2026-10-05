<?php

declare(strict_types=1);

/*
 * G3 — redeeming loyalty points for a discount (owner decision G-R2, docs/audit/2026-10-full-readiness/ROZHODNUTI.md).
 * The earning side of the programme (points per action, levels, missions) stays in `onhost.loyalty`.
 *
 * These are the owner's rules, not tuning knobs: changing one is an owner decision recorded in ROZHODNUTI.md first.
 */
return [
    'redeem' => [
        // 1 point = 1 CZK off the price before VAT; another currency gets the CZK value at the Czech National Bank's rate of the day
        'point_value_czk_minor' => 100,
        // the smallest redemption
        'min_points' => 100,
        // every discount on the lines points may discount (promo code, commitment, streak, points) together stays within this share of
        // their list price before VAT; points only fill what is left
        'cap_pct' => 20,
        // never discounted by points: a domain stays at its list price, and a credit top-up is never a cart line
        'excluded_families' => ['domain'],
    ],
    'expiry' => [
        'months' => 24,          // points expire this many months after they were credited
        'warn_days' => 30,       // the customer is told this many days before
        // points credited before the rule existed count as credited on this day (they were promised "never expire")
        'counted_from' => '2026-10-05',
    ],
];
