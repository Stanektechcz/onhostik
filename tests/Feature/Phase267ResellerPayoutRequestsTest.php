<?php

declare(strict_types=1);

use App\Models\ResellerPayoutRequest;
use App\Domains\Reseller\Models\ResellerProfile;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can list payout requests', function () {
    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.reseller-payout-requests.index'))
        ->assertOk()
        ->assertViewIs('admin.reseller-payout-requests.index');
});

it('admin can approve a payout request', function () {
    $admin = adminUser();
    $resellerUser = customerUser();
    $reseller = ResellerProfile::factory()->create(['user_id' => $resellerUser->id]);
    $req = ResellerPayoutRequest::create([
        'reseller_profile_id' => $reseller->id,
        'amount'              => 5000,
        'currency'            => 'CZK',
        'status'              => 'pending',
        'requested_by'        => $resellerUser->id,
    ]);
    $this->actingAs($admin)
        ->patch(route('admin.reseller-payout-requests.update', $req), ['status' => 'approved'])
        ->assertRedirect();
    $this->assertDatabaseHas('reseller_payout_requests', ['id' => $req->id, 'status' => 'approved']);
});

it('admin can reject a payout request', function () {
    $admin = adminUser();
    $resellerUser = customerUser();
    $reseller = ResellerProfile::factory()->create(['user_id' => $resellerUser->id]);
    $req = ResellerPayoutRequest::create([
        'reseller_profile_id' => $reseller->id,
        'amount'              => 2000,
        'currency'            => 'CZK',
        'status'              => 'pending',
        'requested_by'        => $resellerUser->id,
    ]);
    $this->actingAs($admin)
        ->patch(route('admin.reseller-payout-requests.update', $req), ['status' => 'rejected', 'note' => 'Insufficient balance'])
        ->assertRedirect();
    $this->assertDatabaseHas('reseller_payout_requests', ['id' => $req->id, 'status' => 'rejected']);
});

it('panel reseller can view own payout requests', function () {
    $user = customerUser();
    $reseller = ResellerProfile::factory()->create(['user_id' => $user->id]);
    ResellerPayoutRequest::create([
        'reseller_profile_id' => $reseller->id,
        'amount'              => 3000,
        'currency'            => 'CZK',
        'status'              => 'pending',
        'requested_by'        => $user->id,
    ]);
    $this->actingAs($user)
        ->get(route('panel.reseller-payout-requests.index'))
        ->assertOk()
        ->assertViewIs('panel.reseller-payout-requests.index');
});

it('panel reseller can submit a payout request', function () {
    $user = customerUser();
    ResellerProfile::factory()->active()->create(['user_id' => $user->id]);
    $this->actingAs($user)->post(route('panel.reseller-payout-requests.store'), [
        'amount'   => 1500,
        'currency' => 'CZK',
    ])->assertRedirect();
    $this->assertDatabaseHas('reseller_payout_requests', ['requested_by' => $user->id, 'amount' => 1500, 'status' => 'pending']);
});
