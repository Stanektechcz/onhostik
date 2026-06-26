<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\ProcessMockPaymentAction;
use App\Domains\Partner\Enums\CommissionStatus;
use App\Domains\Partner\Enums\PayoutStatus;
use App\Domains\Partner\Enums\ReferralStatus;
use App\Domains\Partner\Models\PartnerCommission;
use App\Domains\Partner\Models\PartnerProfile;
use App\Domains\Partner\Models\PartnerPayout;
use App\Domains\Partner\Models\PartnerReferral;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ─────────────────────────────────────────────────────────────────
// Payout precision: link commissions to payout
// ─────────────────────────────────────────────────────────────────

it('payout includes only selected approved unpaid commissions', function (): void {
    $admin   = adminUser();
    $partner = makePartnerProfileForTest();

    $c1 = PartnerCommission::create([
        'partner_profile_id' => $partner->id,
        'amount' => 30000, 'currency' => 'CZK', 'rate_percent' => 10,
        'status' => CommissionStatus::Approved->value, 'eligible_at' => now()->subDay(),
    ]);
    $c2 = PartnerCommission::create([
        'partner_profile_id' => $partner->id,
        'amount' => 20000, 'currency' => 'CZK', 'rate_percent' => 10,
        'status' => CommissionStatus::Approved->value, 'eligible_at' => now()->subDay(),
    ]);
    $c3 = PartnerCommission::create([
        'partner_profile_id' => $partner->id,
        'amount' => 10000, 'currency' => 'CZK', 'rate_percent' => 10,
        'status' => CommissionStatus::Pending->value, 'eligible_at' => now()->addDays(10),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.partners.payouts.create', $partner), [
            'commission_ids' => [$c1->id, $c2->id], // only c1+c2, not c3
            'method' => 'Bank transfer',
        ])
        ->assertRedirect();

    $payout = PartnerPayout::where('partner_profile_id', $partner->id)->first();
    expect($payout)->not->toBeNull()
        ->and($payout->amount)->toBe(50000) // 300 + 200 CZK in haléře
        ->and((int) PartnerCommission::where('id', $c1->id)->value('partner_payout_id'))->toBe($payout->id)
        ->and((int) PartnerCommission::where('id', $c2->id)->value('partner_payout_id'))->toBe($payout->id)
        ->and(PartnerCommission::where('id', $c3->id)->value('partner_payout_id'))->toBeNull();
});

it('mark payout paid settles only commissions linked to that payout', function (): void {
    $admin   = adminUser();
    $partner = makePartnerProfileForTest();

    $c1 = PartnerCommission::create([
        'partner_profile_id' => $partner->id,
        'amount' => 15000, 'currency' => 'CZK', 'rate_percent' => 10,
        'status' => CommissionStatus::Approved->value, 'eligible_at' => now()->subDay(),
    ]);
    $c2 = PartnerCommission::create([
        'partner_profile_id' => $partner->id,
        'amount' => 25000, 'currency' => 'CZK', 'rate_percent' => 10,
        'status' => CommissionStatus::Approved->value, 'eligible_at' => now()->subDay(),
    ]);

    // Create payout with only c1
    $this->actingAs($admin)
        ->post(route('admin.partners.payouts.create', $partner), ['commission_ids' => [$c1->id]])
        ->assertRedirect();

    $payout = PartnerPayout::where('partner_profile_id', $partner->id)->first();

    $this->actingAs($admin)
        ->post(route('admin.partners.payouts.paid', [$partner, $payout]))
        ->assertRedirect();

    expect(PartnerCommission::find($c1->id)->status)->toBe(CommissionStatus::Paid)
        ->and(PartnerCommission::find($c2->id)->status)->toBe(CommissionStatus::Approved); // c2 NOT touched
});

it('pending and rejected commissions cannot be included in payout', function (): void {
    $admin   = adminUser();
    $partner = makePartnerProfileForTest();

    $pending  = PartnerCommission::create([
        'partner_profile_id' => $partner->id,
        'amount' => 10000, 'currency' => 'CZK', 'rate_percent' => 10,
        'status' => CommissionStatus::Pending->value, 'eligible_at' => now()->addDays(5),
    ]);
    $rejected = PartnerCommission::create([
        'partner_profile_id' => $partner->id,
        'amount' => 10000, 'currency' => 'CZK', 'rate_percent' => 10,
        'status' => CommissionStatus::Rejected->value, 'eligible_at' => now()->subDay(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.partners.payouts.create', $partner), [
            'commission_ids' => [$pending->id, $rejected->id],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('commission_ids');
});

it('payout cannot include commissions from a different partner', function (): void {
    $admin    = adminUser();
    $partner1 = makePartnerProfileForTest();
    $partner2 = makePartnerProfileForTest();

    $c = PartnerCommission::create([
        'partner_profile_id' => $partner2->id, // belongs to partner2!
        'amount' => 10000, 'currency' => 'CZK', 'rate_percent' => 10,
        'status' => CommissionStatus::Approved->value, 'eligible_at' => now()->subDay(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.partners.payouts.create', $partner1), [ // request for partner1
            'commission_ids' => [$c->id],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('commission_ids');
});

it('cancel payout returns commissions to approved state', function (): void {
    $admin   = adminUser();
    $partner = makePartnerProfileForTest();

    $c = PartnerCommission::create([
        'partner_profile_id' => $partner->id,
        'amount' => 10000, 'currency' => 'CZK', 'rate_percent' => 10,
        'status' => CommissionStatus::Approved->value, 'eligible_at' => now()->subDay(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.partners.payouts.create', $partner), ['commission_ids' => [$c->id]])
        ->assertRedirect();

    $payout = PartnerPayout::where('partner_profile_id', $partner->id)->first();

    $this->actingAs($admin)
        ->post(route('admin.partners.payouts.cancel', [$partner, $payout]))
        ->assertRedirect();

    expect(PartnerPayout::find($payout->id)->status)->toBe(PayoutStatus::Cancelled)
        ->and(PartnerCommission::find($c->id)->partner_payout_id)->toBeNull();
});

// ─────────────────────────────────────────────────────────────────
// Admin partner shortcut from customer detail
// ─────────────────────────────────────────────────────────────────

it('customer detail shows create partner link when user has no profile', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    $this->actingAs($admin)
        ->get(route('admin.customers.show', $customer->customer))
        ->assertOk()
        ->assertSee('Vytvořit partner profil');
});

it('customer detail shows partner profile link when profile exists', function (): void {
    $admin   = adminUser();
    $partner = makePartnerProfileForTest();
    $customerOfPartner = $partner->user->load('customer');

    // Partner user needs a customer for the admin customer page
    if (!$customerOfPartner->customer) {
        \App\Domains\Customer\Models\Customer::create([
            'user_id'            => $partner->user->id,
            'type'               => 'individual',
            'email'              => $partner->user->email,
            'preferred_currency' => 'CZK',
            'preferred_locale'   => 'cs',
            'country_code'       => 'CZ',
        ]);
    }

    $customer = \App\Domains\Customer\Models\Customer::where('user_id', $partner->user->id)->first();

    $this->actingAs($admin)
        ->get(route('admin.customers.show', $customer))
        ->assertOk()
        ->assertSee('Zobrazit partner profil');
});

it('create partner form pre-fills user_id from query parameter', function (): void {
    $admin = adminUser();
    $user  = \App\Models\User::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.partners.create') . '?user_id=' . $user->id)
        ->assertOk()
        ->assertSee((string) $user->id);
});

// ─────────────────────────────────────────────────────────────────
// Helper (local to this test file)
// ─────────────────────────────────────────────────────────────────

function makePartnerProfileForTest(): PartnerProfile
{
    Role::findOrCreate('partner', 'web');
    Permission::findOrCreate('access-partner', 'web');

    $user = \App\Models\User::factory()->create();
    $user->givePermissionTo('access-partner');

    return PartnerProfile::create([
        'user_id'                 => $user->id,
        'referral_code'           => strtoupper(substr(md5(uniqid()), 0, 8)),
        'status'                  => 'active',
        'commission_rate_percent' => 10.0,
    ]);
}
