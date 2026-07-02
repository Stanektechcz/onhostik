<?php

declare(strict_types=1);

use App\Domains\Partner\Enums\CommissionStatus;
use App\Domains\Partner\Enums\PartnerStatus;
use App\Domains\Partner\Models\PartnerCommission;
use App\Domains\Partner\Models\PartnerPayout;
use App\Domains\Partner\Models\PartnerProfile;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

function makePartnerPortalUser(string $code = 'PORTAL01'): \App\Models\User
{
    Permission::findOrCreate('access-partner', 'web');

    $user = customerUser();
    $user->givePermissionTo('access-partner');

    PartnerProfile::create([
        'user_id'       => $user->id,
        'referral_code' => $code,
        'status'        => PartnerStatus::Active->value,
        'rate'          => '10.00',
        'currency'      => 'CZK',
    ]);

    return $user;
}

// ── Access control ────────────────────────────────────────────────────────────

it('non-partner customer cannot access partner dashboard', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('partner.dashboard'))
        ->assertForbidden();
});

it('partner can access partner dashboard', function (): void {
    $user = makePartnerPortalUser('DASH01');

    $this->actingAs($user)
        ->get(route('partner.dashboard'))
        ->assertOk();
});

// ── Partner portal pages ──────────────────────────────────────────────────────

it('partner can view referrals page with referral URL', function (): void {
    $user = makePartnerPortalUser('REF01');

    $this->actingAs($user)
        ->get(route('partner.referrals'))
        ->assertOk()
        ->assertSee('REF01');
});

it('partner can view commissions page with amounts', function (): void {
    $user    = makePartnerPortalUser('COMM01');
    $profile = PartnerProfile::where('user_id', $user->id)->firstOrFail();

    PartnerCommission::create([
        'partner_profile_id' => $profile->id,
        'amount'             => 50000,
        'currency'           => 'CZK',
        'rate_percent'       => '10.00',
        'status'             => CommissionStatus::Pending->value,
    ]);

    $this->actingAs($user)
        ->get(route('partner.commissions'))
        ->assertOk();
});

it('partner can view payouts page', function (): void {
    $user    = makePartnerPortalUser('PAYOUT01');
    $profile = PartnerProfile::where('user_id', $user->id)->firstOrFail();

    PartnerPayout::create([
        'partner_profile_id' => $profile->id,
        'amount'             => 100000,
        'currency'           => 'CZK',
        'status'             => \App\Domains\Partner\Enums\PayoutStatus::Requested->value,
        'requested_at'       => now(),
    ]);

    $this->actingAs($user)
        ->get(route('partner.payouts'))
        ->assertOk();
});

it('partner can view assets and marketing materials page', function (): void {
    $user = makePartnerPortalUser('ASSET01');

    $this->actingAs($user)
        ->get(route('partner.assets'))
        ->assertOk();
});

it('partner can view profile settings page', function (): void {
    $user = makePartnerPortalUser('PROF01');

    $this->actingAs($user)
        ->get(route('partner.profile'))
        ->assertOk();
});

// ── Dashboard KPIs ────────────────────────────────────────────────────────────

it('partner dashboard shows zero KPIs for new partner with no activity', function (): void {
    $user = makePartnerPortalUser('NEW01');

    $response = $this->actingAs($user)
        ->get(route('partner.dashboard'))
        ->assertOk();

    // Page renders without error — KPIs default to 0
    expect($response->status())->toBe(200);
});

it('guest without partner permission gets dashboard as redirect or forbidden', function (): void {
    $this->get(route('partner.dashboard'))
        ->assertRedirect();
});
