<?php

declare(strict_types=1);

use App\Domains\Partner\Enums\CommissionStatus;
use App\Domains\Partner\Enums\PartnerStatus;
use App\Domains\Partner\Enums\PayoutStatus;
use App\Domains\Partner\Models\PartnerCommission;
use App\Domains\Partner\Models\PartnerProfile;
use App\Domains\Partner\Services\PartnerTierService;
use App\Models\User;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
    Permission::findOrCreate('access-partner', 'web');
});

// Helper — create a PartnerProfile without a factory
function makeProfile(User $user, string $status = 'active', float $rate = 5.0): PartnerProfile
{
    return PartnerProfile::create([
        'user_id'                 => $user->id,
        'referral_code'           => strtoupper(Str::random(8)),
        'status'                  => $status,
        'commission_rate_percent' => $rate,
        'payout_method'           => 'bank_czk',
    ]);
}

// ── PartnerStatus::Pending ─────────────────────────────────────────────────────

it('PartnerStatus has Pending case', function (): void {
    expect(PartnerStatus::Pending->value)->toBe('pending')
        ->and(PartnerStatus::Pending->label())->toBe('Čeká na schválení')
        ->and(PartnerStatus::Pending->canTrackReferrals())->toBeFalse();
});

// ── PartnerTierService ─────────────────────────────────────────────────────────

it('PartnerTierService returns Bronze for new partner with no earnings', function (): void {
    $user    = customerUser();
    $profile = makeProfile($user);

    $tier = app(PartnerTierService::class)->forProfile($profile);

    expect($tier['label'])->toBe('Bronze')
        ->and($tier['rate'])->toBe(5.0)
        ->and($tier['total_paid_minor'])->toBe(0);
});

it('PartnerTierService returns Silver tier when paid commissions ≥ 10 000 CZK', function (): void {
    $user    = customerUser();
    $profile = makeProfile($user);

    // 10 000 CZK = 1 000 000 minor
    PartnerCommission::create([
        'partner_profile_id' => $profile->id,
        'amount'             => 1_000_000,
        'currency'           => 'CZK',
        'rate_percent'       => 5.0,
        'status'             => CommissionStatus::Paid,
    ]);

    $tier = app(PartnerTierService::class)->forProfile($profile);

    expect($tier['label'])->toBe('Silver')
        ->and($tier['rate'])->toBe(8.0);
});

it('PartnerTierService returns Gold tier when paid commissions ≥ 50 000 CZK', function (): void {
    $user    = customerUser();
    $profile = makeProfile($user);

    PartnerCommission::create([
        'partner_profile_id' => $profile->id,
        'amount'             => 5_000_000,
        'currency'           => 'CZK',
        'rate_percent'       => 5.0,
        'status'             => CommissionStatus::Paid,
    ]);

    $tier = app(PartnerTierService::class)->forProfile($profile);

    expect($tier['label'])->toBe('Gold')
        ->and($tier['rate'])->toBe(12.0);
});

it('PartnerTierService maybeUpgrade upgrades commission rate when tier warrants it', function (): void {
    $user    = customerUser();
    $profile = makeProfile($user, rate: 5.0);

    PartnerCommission::create([
        'partner_profile_id' => $profile->id,
        'amount'             => 1_000_000,
        'currency'           => 'CZK',
        'rate_percent'       => 5.0,
        'status'             => CommissionStatus::Paid,
    ]);

    app(PartnerTierService::class)->maybeUpgrade($profile);

    expect($profile->fresh()->commission_rate_percent)->toBe(8.0);
});

it('PartnerTierService maybeUpgrade does not downgrade a manually set higher rate', function (): void {
    $user    = customerUser();
    $profile = makeProfile($user, rate: 15.0);

    app(PartnerTierService::class)->maybeUpgrade($profile);

    expect($profile->fresh()->commission_rate_percent)->toBe(15.0);
});

// ── Partner self-registration ──────────────────────────────────────────────────

it('authenticated user can view the partner apply form', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('partner.apply'))
        ->assertOk()
        ->assertSee('Přihláška');
});

it('guest is redirected from partner apply form', function (): void {
    $this->get(route('partner.apply'))
        ->assertRedirect(route('login'));
});

it('user can submit a partner application', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('partner.apply.store'), [
            'payout_method' => 'bank_czk',
            'payout_info'   => 'CZ65 0800 0000 1920 0014 5399',
            'agree_terms'   => '1',
        ])
        ->assertRedirect(route('panel.dashboard'));

    $profile = PartnerProfile::where('user_id', $user->id)->first();

    expect($profile)->not->toBeNull()
        ->and($profile->status)->toBe(PartnerStatus::Pending)
        ->and($profile->payout_method)->toBe('bank_czk');
});

it('apply form validation rejects missing payout info', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('partner.apply.store'), [
            'payout_method' => 'bank_czk',
            'agree_terms'   => '1',
        ])
        ->assertSessionHasErrors(['payout_info']);
});

it('apply form validation rejects unchecked terms', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('partner.apply.store'), [
            'payout_method' => 'bank_czk',
            'payout_info'   => 'CZ65 0800 0000 1920 0014 5399',
        ])
        ->assertSessionHasErrors(['agree_terms']);
});

it('user with existing partner profile is redirected away from apply', function (): void {
    $user = customerUser();
    makeProfile($user, 'active');
    $user->givePermissionTo('access-partner');

    $this->actingAs($user)
        ->get(route('partner.apply'))
        ->assertRedirect(route('partner.dashboard'));
});

// ── Admin pending list ──────────────────────────────────────────────────────────

it('admin can view pending partner applications list', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.partners.pending'))
        ->assertOk()
        ->assertSee('Čekající přihlášky');
});

it('admin pending list shows pending partners', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $profile = makeProfile($user, 'pending');

    $this->actingAs($admin)
        ->get(route('admin.partners.pending'))
        ->assertOk()
        ->assertSee($profile->referral_code);
});

// ── Admin approve / reject ─────────────────────────────────────────────────────

it('admin can approve a pending partner application', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $profile = makeProfile($user, 'pending');

    $this->actingAs($admin)
        ->post(route('admin.partners.approve', $profile))
        ->assertRedirect(route('admin.partners.show', $profile));

    expect($profile->fresh()->status)->toBe(PartnerStatus::Active);
    expect($user->fresh()->hasPermissionTo('access-partner'))->toBeTrue();
});

it('admin can reject a pending partner application', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $profile = makeProfile($user, 'pending');

    $this->actingAs($admin)
        ->post(route('admin.partners.reject', $profile), ['reason' => 'Nedostatečné informace'])
        ->assertRedirect(route('admin.partners.index'));

    expect($profile->fresh()->status)->toBe(PartnerStatus::Banned);
});

it('approving an already active partner returns error', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $profile = makeProfile($user, 'active');

    $this->actingAs($admin)
        ->post(route('admin.partners.approve', $profile))
        ->assertSessionHasErrors(['partner']);
});

// ── Partner payout request ─────────────────────────────────────────────────────

it('active partner can request a payout when they have enough approved commissions', function (): void {
    $user    = customerUser();
    $profile = makeProfile($user, 'active');
    $user->givePermissionTo('access-partner');

    // 600 CZK approved — above 500 CZK minimum
    PartnerCommission::create([
        'partner_profile_id' => $profile->id,
        'amount'             => 60_000,
        'currency'           => 'CZK',
        'rate_percent'       => 5.0,
        'status'             => CommissionStatus::Approved,
    ]);

    $this->actingAs($user)
        ->post(route('partner.payout.request'))
        ->assertRedirect();

    $payout = $profile->payouts()->latest()->first();
    expect($payout)->not->toBeNull()
        ->and($payout->status)->toBe(PayoutStatus::Requested)
        ->and($payout->amount)->toBe(60_000);
});

it('active partner cannot request payout below minimum threshold', function (): void {
    $user    = customerUser();
    $profile = makeProfile($user, 'active');
    $user->givePermissionTo('access-partner');

    PartnerCommission::create([
        'partner_profile_id' => $profile->id,
        'amount'             => 100,  // 1.00 CZK — way below minimum
        'currency'           => 'CZK',
        'rate_percent'       => 5.0,
        'status'             => CommissionStatus::Approved,
    ]);

    $this->actingAs($user)
        ->post(route('partner.payout.request'))
        ->assertSessionHasErrors(['payout']);
});
