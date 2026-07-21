<?php

declare(strict_types=1);

use App\Domains\Approvals\Exceptions\ApprovalException;
use App\Domains\Approvals\Models\ApprovalRequest;
use App\Domains\Approvals\Services\ApprovalService;
use App\Domains\Reseller\Models\ResellerProfile;
use App\Models\ResellerPayoutRequest;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function approvedPayout(int $amount = 500000): ResellerPayoutRequest
{
    $resellerUser = customerUser();
    $reseller = ResellerProfile::factory()->create(['user_id' => $resellerUser->id]);

    return ResellerPayoutRequest::create([
        'reseller_profile_id' => $reseller->id,
        'amount'              => $amount,
        'currency'            => 'CZK',
        'status'              => 'approved',
        'requested_by'        => $resellerUser->id,
    ]);
}

// ── the gate: required() ────────────────────────────────────────────────────────

it('does not require approval when the action is disabled (default)', function (): void {
    config(['approvals.actions.reseller_payout_paid.enabled' => false]);

    expect(app(ApprovalService::class)->required('reseller_payout_paid', ['amount_minor' => 999999]))
        ->toBeFalse();
});

it('requires approval above the configured threshold', function (): void {
    config([
        'approvals.actions.reseller_payout_paid.enabled'          => true,
        'approvals.actions.reseller_payout_paid.min_amount_minor' => 100000,
    ]);

    $svc = app(ApprovalService::class);

    expect($svc->required('reseller_payout_paid', ['amount_minor' => 50000]))->toBeFalse()
        ->and($svc->required('reseller_payout_paid', ['amount_minor' => 100000]))->toBeTrue();
});

// ── the invariant: no self-approval ─────────────────────────────────────────────

it('refuses to let the requester approve their own request', function (): void {
    $requester = adminUser();
    $req = app(ApprovalService::class)->request('reseller_payout_paid', [], $requester, approvedPayout());

    // The whole point of four eyes.
    expect(fn () => app(ApprovalService::class)->approve($req, $requester))
        ->toThrow(ApprovalException::class);

    expect($req->fresh()->status)->toBe(ApprovalRequest::STATUS_PENDING);
});

it('lets a different admin approve and then executes the action', function (): void {
    $requester = adminUser();
    $approver  = adminUser();
    $payout    = approvedPayout();

    $req = app(ApprovalService::class)->request('reseller_payout_paid', [], $requester, $payout);
    app(ApprovalService::class)->approve($req, $approver);

    expect($req->fresh()->status)->toBe(ApprovalRequest::STATUS_EXECUTED)
        // The executor actually paid the payout.
        ->and($payout->fresh()->status)->toBe('paid');
});

it('cannot approve a request twice', function (): void {
    $req = app(ApprovalService::class)->request('reseller_payout_paid', [], adminUser(), approvedPayout());
    app(ApprovalService::class)->approve($req, adminUser());

    expect(fn () => app(ApprovalService::class)->approve($req->fresh(), adminUser()))
        ->toThrow(ApprovalException::class);
});

it('records a failure when the executor throws, and does not mark it executed', function (): void {
    $payout = approvedPayout();
    // Move it out of "approved" so the executor's double-pay guard trips.
    $payout->update(['status' => 'paid']);

    $req = app(ApprovalService::class)->request('reseller_payout_paid', [], adminUser(), $payout);

    expect(fn () => app(ApprovalService::class)->approve($req, adminUser()))
        ->toThrow(ApprovalException::class);

    expect($req->fresh()->status)->toBe(ApprovalRequest::STATUS_FAILED)
        ->and($req->fresh()->failure_reason)->not->toBeNull();
});

it('rejecting a request executes nothing', function (): void {
    $payout = approvedPayout();
    $req = app(ApprovalService::class)->request('reseller_payout_paid', [], adminUser(), $payout);

    app(ApprovalService::class)->reject($req, adminUser(), 'Nesedí částka');

    expect($req->fresh()->status)->toBe(ApprovalRequest::STATUS_REJECTED)
        ->and($payout->fresh()->status)->toBe('approved'); // untouched
});

// ── controller integration on the payout path ───────────────────────────────────

it('stages the payout instead of paying it when four-eyes is on', function (): void {
    config([
        'approvals.actions.reseller_payout_paid.enabled'          => true,
        'approvals.actions.reseller_payout_paid.min_amount_minor' => 0,
    ]);

    $payout = approvedPayout();

    $this->actingAs(adminUser())
        ->patch(route('admin.reseller-payout-requests.update', $payout), ['status' => 'paid'])
        ->assertRedirect();

    // Not paid yet — a request is waiting for a second admin.
    expect($payout->fresh()->status)->toBe('approved')
        ->and(ApprovalRequest::where('action', 'reseller_payout_paid')->where('status', 'pending')->count())->toBe(1);
});

it('pays straight through when four-eyes is off (unchanged behaviour)', function (): void {
    config(['approvals.actions.reseller_payout_paid.enabled' => false]);

    $payout = approvedPayout();

    $this->actingAs(adminUser())
        ->patch(route('admin.reseller-payout-requests.update', $payout), ['status' => 'paid'])
        ->assertRedirect();

    expect($payout->fresh()->status)->toBe('paid')
        ->and(ApprovalRequest::count())->toBe(0);
});

// ── admin review UI ───────────────────────────────────────────────────────────

it('shows the pending queue and lets a second admin approve over HTTP', function (): void {
    $payout = approvedPayout();
    $req = app(ApprovalService::class)->request('reseller_payout_paid', [], adminUser(), $payout);

    $this->actingAs(adminUser())
        ->post(route('admin.approvals.approve', $req))
        ->assertRedirect();

    expect($payout->fresh()->status)->toBe('paid');
});

it('surfaces a friendly error if an admin tries to approve their own request over HTTP', function (): void {
    $requester = adminUser();
    $req = app(ApprovalService::class)->request('reseller_payout_paid', [], $requester, approvedPayout());

    $this->actingAs($requester)
        ->from(route('admin.approvals.index'))
        ->post(route('admin.approvals.approve', $req))
        ->assertRedirect()
        ->assertSessionHasErrors('approval');
});

it('renders the approvals review page', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.approvals.index'))
        ->assertOk()
        ->assertViewIs('admin.approvals.index');
});
