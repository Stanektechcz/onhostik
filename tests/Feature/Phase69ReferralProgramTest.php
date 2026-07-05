<?php

declare(strict_types=1);

use App\Domains\Customer\Models\Customer;
use App\Domains\Customer\Models\CustomerReferral;
use App\Domains\Customer\Services\ReferralService;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Model helpers ─────────────────────────────────────────────────────────────

it('CustomerReferral statusLabel returns Czech strings', function (): void {
    expect((new CustomerReferral(['status' => 'pending']))->statusLabel())->toBe('Čeká na kvalifikaci')
        ->and((new CustomerReferral(['status' => 'qualified']))->statusLabel())->toBe('Kvalifikováno')
        ->and((new CustomerReferral(['status' => 'rewarded']))->statusLabel())->toBe('Odměněno')
        ->and((new CustomerReferral(['status' => 'expired']))->statusLabel())->toBe('Vypršelo');
});

// ── ReferralService: code generation ─────────────────────────────────────────

it('getOrCreateCode creates a unique code for customer', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $code = app(ReferralService::class)->getOrCreateCode($customer);

    expect($code)->toBeString()->toHaveLength(8);
    expect($customer->fresh()->referral_code)->toBe($code);
});

it('getOrCreateCode returns same code on second call', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $code1 = app(ReferralService::class)->getOrCreateCode($customer);
    $code2 = app(ReferralService::class)->getOrCreateCode($customer);

    expect($code1)->toBe($code2);
});

it('codes are unique across customers', function (): void {
    $user1 = customerUser();
    $user2 = customerUser();

    $code1 = app(ReferralService::class)->getOrCreateCode($user1->customer);
    $code2 = app(ReferralService::class)->getOrCreateCode($user2->customer);

    expect($code1)->not->toBe($code2);
});

// ── ReferralService: register ─────────────────────────────────────────────────

it('register creates a referral record', function (): void {
    $referrer = customerUser()->customer;
    $referee  = customerUser()->customer;

    $code = app(ReferralService::class)->getOrCreateCode($referrer);
    $referral = app(ReferralService::class)->register($referee, $code);

    expect($referral)->not->toBeNull()
        ->and($referral->referrer_id)->toBe($referrer->id)
        ->and($referral->referee_id)->toBe($referee->id)
        ->and($referral->status)->toBe('pending');
});

it('register returns null for unknown code', function (): void {
    $referee = customerUser()->customer;

    $result = app(ReferralService::class)->register($referee, 'BADCODE1');

    expect($result)->toBeNull();
});

it('register prevents self-referral', function (): void {
    $customer = customerUser()->customer;
    $code = app(ReferralService::class)->getOrCreateCode($customer);

    $result = app(ReferralService::class)->register($customer, $code);

    expect($result)->toBeNull();
});

it('register prevents duplicate referral', function (): void {
    $referrer = customerUser()->customer;
    $referee  = customerUser()->customer;
    $code     = app(ReferralService::class)->getOrCreateCode($referrer);

    app(ReferralService::class)->register($referee, $code);
    $second = app(ReferralService::class)->register($referee, $code);

    expect($second)->toBeNull();
    expect(CustomerReferral::where('referee_id', $referee->id)->count())->toBe(1);
});

// ── ReferralService: qualify & reward ────────────────────────────────────────

it('qualify changes status from pending to qualified', function (): void {
    $referrer = customerUser()->customer;
    $referee  = customerUser()->customer;
    $code     = app(ReferralService::class)->getOrCreateCode($referrer);

    $referral = app(ReferralService::class)->register($referee, $code);
    app(ReferralService::class)->qualify($referral);

    expect($referral->fresh()->status)->toBe('qualified')
        ->and($referral->fresh()->qualified_at)->not->toBeNull();
});

it('reward deposits credit to both referrer and referee', function (): void {
    $referrer = customerUser()->customer;
    $referee  = customerUser()->customer;
    $code     = app(ReferralService::class)->getOrCreateCode($referrer);

    $referral = app(ReferralService::class)->register($referee, $code);
    app(ReferralService::class)->qualify($referral);
    app(ReferralService::class)->reward($referral);

    $referral->refresh();
    expect($referral->status)->toBe('rewarded')
        ->and($referral->rewarded_at)->not->toBeNull();

    // Check credit transactions created
    expect($referrer->creditTransactions()->count())->toBeGreaterThan(0);
    expect($referee->creditTransactions()->count())->toBeGreaterThan(0);
});

it('reward is idempotent on non-qualified referral', function (): void {
    $referrer = customerUser()->customer;
    $referee  = customerUser()->customer;
    $code     = app(ReferralService::class)->getOrCreateCode($referrer);

    $referral = app(ReferralService::class)->register($referee, $code);
    // status = pending, not qualified → reward should be no-op
    app(ReferralService::class)->reward($referral);

    expect($referral->fresh()->status)->toBe('pending');
});

// ── ReferralService: stats ────────────────────────────────────────────────────

it('stats returns correct counts', function (): void {
    $referrer = customerUser()->customer;
    $code     = app(ReferralService::class)->getOrCreateCode($referrer);

    $referee1 = customerUser()->customer;
    $referee2 = customerUser()->customer;

    $ref1 = app(ReferralService::class)->register($referee1, $code);
    $ref2 = app(ReferralService::class)->register($referee2, $code);

    app(ReferralService::class)->qualify($ref1);
    app(ReferralService::class)->reward($ref1);

    $stats = app(ReferralService::class)->stats($referrer);

    expect($stats['total'])->toBe(2)
        ->and($stats['rewarded'])->toBe(1)
        ->and($stats['earned_haler'])->toBe(20000); // 200 Kč
});

// ── Admin routes ──────────────────────────────────────────────────────────────

it('admin can list referrals', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.referrals.index'))
        ->assertOk()
        ->assertSee('Referral program');
});

it('non-admin cannot view admin referrals', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.referrals.index'))
        ->assertStatus(403);
});

it('admin can qualify a pending referral', function (): void {
    $admin    = adminUser();
    $referrer = customerUser()->customer;
    $referee  = customerUser()->customer;
    $code     = app(ReferralService::class)->getOrCreateCode($referrer);

    $referral = app(ReferralService::class)->register($referee, $code);

    $this->actingAs($admin)
        ->post(route('admin.referrals.qualify', $referral))
        ->assertRedirect();

    expect($referral->fresh()->status)->toBe('qualified');
});

it('admin can reward a qualified referral', function (): void {
    $admin    = adminUser();
    $referrer = customerUser()->customer;
    $referee  = customerUser()->customer;
    $code     = app(ReferralService::class)->getOrCreateCode($referrer);

    $referral = app(ReferralService::class)->register($referee, $code);
    app(ReferralService::class)->qualify($referral);

    $this->actingAs($admin)
        ->post(route('admin.referrals.reward', $referral))
        ->assertRedirect();

    expect($referral->fresh()->status)->toBe('rewarded');
});

it('admin can expire a referral', function (): void {
    $admin    = adminUser();
    $referrer = customerUser()->customer;
    $referee  = customerUser()->customer;
    $code     = app(ReferralService::class)->getOrCreateCode($referrer);

    $referral = app(ReferralService::class)->register($referee, $code);

    $this->actingAs($admin)
        ->post(route('admin.referrals.expire', $referral))
        ->assertRedirect();

    expect($referral->fresh()->status)->toBe('expired');
});

// ── Panel routes ──────────────────────────────────────────────────────────────

it('customer can view their referral page', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.referral.index'))
        ->assertOk()
        ->assertSee('Referral program');
});

it('customer sees their referral code', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    app(ReferralService::class)->getOrCreateCode($customer);

    $this->actingAs($user)
        ->get(route('panel.referral.index'))
        ->assertOk()
        ->assertSee($customer->fresh()->referral_code);
});

it('unauthenticated user cannot view referral page', function (): void {
    $this->get(route('panel.referral.index'))->assertRedirect();
});
