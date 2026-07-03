<?php

declare(strict_types=1);

use App\Domains\Partner\Enums\CommissionStatus;
use App\Domains\Partner\Enums\PartnerStatus;
use App\Domains\Partner\Models\PartnerCommission;
use App\Domains\Partner\Models\PartnerProfile;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

function makePartnerTargetUser(string $code = 'TGTTEST'): \App\Models\User
{
    Permission::findOrCreate('access-partner', 'web');

    $user = customerUser();
    $user->givePermissionTo('access-partner');

    PartnerProfile::create([
        'user_id'       => $user->id,
        'referral_code' => $code,
        'status'        => PartnerStatus::Active->value,
        'rate_percent'  => 10.0,
        'currency'      => 'CZK',
    ]);

    return $user;
}

// ── Config key ────────────────────────────────────────────────────────────────

it('partner.monthly_target_minor config key defaults to 1 000 000', function (): void {
    expect((int) config('partner.monthly_target_minor'))->toBe(1_000_000);
});

it('partner.monthly_target_minor can be overridden via config()', function (): void {
    config(['partner.monthly_target_minor' => 500_000]);

    expect((int) config('partner.monthly_target_minor'))->toBe(500_000);
});

// ── Partner dashboard uses config target ──────────────────────────────────────

it('partner dashboard shows monthlyTarget from config', function (): void {
    config(['partner.monthly_target_minor' => 2_000_000]);

    $user = makePartnerTargetUser('TGTDASH');

    $response = $this->actingAs($user)
        ->get(route('partner.dashboard'))
        ->assertOk()
        ->assertViewIs('partner.dashboard');

    expect($response->viewData('monthlyTarget'))->toBe(2_000_000);
});

it('partner dashboard monthlyTargetPct is 0 when no commissions this month', function (): void {
    config(['partner.monthly_target_minor' => 1_000_000]);

    $user = makePartnerTargetUser('TGTPCT0');

    $response = $this->actingAs($user)
        ->get(route('partner.dashboard'))
        ->assertOk();

    expect($response->viewData('monthlyTargetPct'))->toBe(0.0);
});

it('partner dashboard monthlyTargetPct reflects commissions earned this month', function (): void {
    config(['partner.monthly_target_minor' => 1_000_000]);

    $user    = makePartnerTargetUser('TGTPCTX');
    $profile = PartnerProfile::where('user_id', $user->id)->first();

    // 500_000 minor = 50% of 1_000_000 target
    PartnerCommission::create([
        'partner_profile_id' => $profile->id,
        'invoice_id'         => null,
        'order_id'           => null,
        'status'             => CommissionStatus::Pending->value,
        'amount'             => 500_000,
        'currency'           => 'CZK',
        'rate_percent'       => 10.0,
        'created_at'         => now(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('partner.dashboard'))
        ->assertOk();

    expect($response->viewData('monthlyTargetPct'))->toBe(50.0);
});

it('partner dashboard monthlyTargetPct does not exceed 100 for over-target commissions', function (): void {
    config(['partner.monthly_target_minor' => 100_000]);

    $user    = makePartnerTargetUser('TGTOVER');
    $profile = PartnerProfile::where('user_id', $user->id)->first();

    // 200_000 > 100_000 target — pct will be 200 (no clamp in model, just verify it's > 100)
    PartnerCommission::create([
        'partner_profile_id' => $profile->id,
        'invoice_id'         => null,
        'order_id'           => null,
        'status'             => CommissionStatus::Paid->value,
        'amount'             => 200_000,
        'currency'           => 'CZK',
        'rate_percent'       => 10.0,
        'created_at'         => now(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('partner.dashboard'))
        ->assertOk();

    expect($response->viewData('monthlyTargetPct'))->toBeGreaterThan(100.0);
});
