<?php

declare(strict_types=1);

use App\Domains\Partner\Models\PartnerProfile;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleSeeder;

// ── Idempotency regression (production incident 2026-07-16) ─────────────────
//
// A partial prior seed run can leave a stale partner_profiles row keyed to a
// different (now-gone) user id. Re-seeding must not crash on the
// referral_code unique constraint.

it('can run twice without crashing', function (): void {
    $this->seed(RoleSeeder::class);

    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);

    expect(User::where('email', 'admin@onhost.local')->count())->toBe(1)
        ->and(PartnerProfile::where('referral_code', 'ONHOSTDEV')->count())->toBe(1);
});

it('recovers when the demo referral code is already attached to a different user', function (): void {
    $this->seed(RoleSeeder::class);

    // Simulate the exact incident: an earlier partial run created the demo
    // partner profile against a since-diverged user id.
    $staleUser = User::create([
        'name'     => 'Stale Admin',
        'email'    => 'stale-admin@onhost.local',
        'password' => 'password',
    ]);
    PartnerProfile::create([
        'user_id'                 => $staleUser->id,
        'referral_code'           => 'ONHOSTDEV',
        'status'                  => 'active',
        'commission_rate_percent' => 10.0,
    ]);

    $this->seed(AdminUserSeeder::class);

    expect(PartnerProfile::where('referral_code', 'ONHOSTDEV')->count())->toBe(1);
});
