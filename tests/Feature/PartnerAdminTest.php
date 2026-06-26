<?php

declare(strict_types=1);

use App\Console\Commands\ApproveEligibleCommissionsCommand;
use App\Domains\Billing\Actions\ProcessMockPaymentAction;
use App\Domains\Partner\Enums\CommissionStatus;
use App\Domains\Partner\Enums\ReferralStatus;
use App\Domains\Partner\Models\PartnerCommission;
use App\Domains\Partner\Models\PartnerProfile;
use App\Domains\Partner\Models\PartnerReferral;
use App\Notifications\PartnerCommissionCreatedNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ────────────────────────────────────────────────────────────────────────
// Admin: create / edit partner profile
// ────────────────────────────────────────────────────────────────────────

it('admin can create a partner profile for an existing user', function (): void {
    $admin     = adminUser();
    $candidate = \App\Models\User::factory()->create();

    Permission::findOrCreate('access-partner', 'web');

    $this->actingAs($admin)
        ->post(route('admin.partners.store'), [
            'user_id'                 => $candidate->id,
            'referral_code'           => 'TESTCODE1',
            'status'                  => 'active',
            'commission_rate_percent' => 12.5,
            'payout_method'           => 'Bank transfer',
        ])
        ->assertRedirect();

    expect(PartnerProfile::where('user_id', $candidate->id)->exists())->toBeTrue();
    expect($candidate->fresh()->hasPermissionTo('access-partner'))->toBeTrue();
});

it('cannot create duplicate partner profile for same user', function (): void {
    $admin   = adminUser();
    $partner = makePartnerUser();

    $this->actingAs($admin)
        ->post(route('admin.partners.store'), [
            'user_id'                 => $partner->id,
            'referral_code'           => 'UNIQUE001',
            'status'                  => 'active',
            'commission_rate_percent' => 10,
        ])
        ->assertSessionHasErrors('user_id');
});

it('referral_code must be unique on create', function (): void {
    $admin      = adminUser();
    $existing   = makePartnerUser('MYCODE01');
    $candidate  = \App\Models\User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.partners.store'), [
            'user_id'                 => $candidate->id,
            'referral_code'           => 'MYCODE01', // duplicate
            'status'                  => 'active',
            'commission_rate_percent' => 10,
        ])
        ->assertSessionHasErrors('referral_code');
});

it('admin can edit partner status and rate', function (): void {
    $admin   = adminUser();
    $partner = PartnerProfile::where('user_id', makePartnerUser()->id)->first();

    $this->actingAs($admin)
        ->put(route('admin.partners.update', $partner), [
            'referral_code'           => $partner->referral_code,
            'status'                  => 'paused',
            'commission_rate_percent' => 5.0,
        ])
        ->assertRedirect(route('admin.partners.show', $partner));

    $partner->refresh();
    expect($partner->status->value)->toBe('paused')
        ->and($partner->commission_rate_percent)->toBe(5.0);
});

// ────────────────────────────────────────────────────────────────────────
// Partner onboarding: no profile
// ────────────────────────────────────────────────────────────────────────

it('partner user without a profile sees onboarding message not a 500', function (): void {
    Role::findOrCreate('partner', 'web');
    Permission::findOrCreate('access-partner', 'web');

    $user = \App\Models\User::factory()->create();
    $user->assignRole('partner');
    $user->givePermissionTo('access-partner');

    $this->actingAs($user)
        ->get(route('partner.dashboard'))
        ->assertOk()
        ->assertSee('Partner profil zatím není aktivní');
});

// ────────────────────────────────────────────────────────────────────────
// Commission auto-approve command
// ────────────────────────────────────────────────────────────────────────

it('auto-approve command approves eligible pending commissions', function (): void {
    $partnerUser = makePartnerUser();
    $profile     = PartnerProfile::where('user_id', $partnerUser->id)->first();

    $customer = customerUser();
    PartnerReferral::create([
        'partner_profile_id'   => $profile->id,
        'referred_user_id'     => $customer->id,
        'referred_customer_id' => $customer->customer->id,
        'referral_code'        => $profile->referral_code,
        'first_seen_at'        => now(),
        'status'               => ReferralStatus::Registered->value,
    ]);

    // Create a commission already past its eligible_at
    PartnerCommission::create([
        'partner_profile_id' => $profile->id,
        'amount'             => 50000,
        'currency'           => 'CZK',
        'rate_percent'       => 10,
        'status'             => CommissionStatus::Pending->value,
        'eligible_at'        => now()->subDay(),
    ]);

    $this->artisan(ApproveEligibleCommissionsCommand::class)
        ->assertExitCode(0);

    expect(
        PartnerCommission::where('partner_profile_id', $profile->id)
            ->where('status', CommissionStatus::Approved->value)
            ->exists()
    )->toBeTrue();
});

it('auto-approve command is idempotent', function (): void {
    $profile = PartnerProfile::where('user_id', makePartnerUser()->id)->first();

    PartnerCommission::create([
        'partner_profile_id' => $profile->id,
        'amount'             => 30000,
        'currency'           => 'CZK',
        'rate_percent'       => 10,
        'status'             => CommissionStatus::Pending->value,
        'eligible_at'        => now()->subDay(),
    ]);

    $this->artisan(ApproveEligibleCommissionsCommand::class)->assertExitCode(0);
    $this->artisan(ApproveEligibleCommissionsCommand::class)->assertExitCode(0);

    expect(
        PartnerCommission::where('partner_profile_id', $profile->id)
            ->where('status', CommissionStatus::Approved->value)
            ->count()
    )->toBe(1);
});

it('auto-approve skips commissions not yet eligible', function (): void {
    $profile = PartnerProfile::where('user_id', makePartnerUser()->id)->first();

    PartnerCommission::create([
        'partner_profile_id' => $profile->id,
        'amount'             => 10000,
        'currency'           => 'CZK',
        'rate_percent'       => 10,
        'status'             => CommissionStatus::Pending->value,
        'eligible_at'        => now()->addDays(7), // still in hold period
    ]);

    $this->artisan(ApproveEligibleCommissionsCommand::class)->assertExitCode(0);

    expect(
        PartnerCommission::where('partner_profile_id', $profile->id)
            ->where('status', CommissionStatus::Pending->value)
            ->exists()
    )->toBeTrue();
});

// ────────────────────────────────────────────────────────────────────────
// Notification on commission creation
// ────────────────────────────────────────────────────────────────────────

it('partner receives notification when commission is created', function (): void {
    Notification::fake();

    $partnerUser = makePartnerUser();
    $profile     = PartnerProfile::where('user_id', $partnerUser->id)->first();

    $customer = customerUser();
    PartnerReferral::create([
        'partner_profile_id'   => $profile->id,
        'referred_user_id'     => $customer->id,
        'referred_customer_id' => $customer->customer->id,
        'referral_code'        => $profile->referral_code,
        'first_seen_at'        => now(),
        'status'               => ReferralStatus::Registered->value,
    ]);

    ['invoice' => $invoice] = placeOrder($customer);
    app(ProcessMockPaymentAction::class)->execute($invoice);

    Notification::assertSentTo($partnerUser, PartnerCommissionCreatedNotification::class);
});

// ────────────────────────────────────────────────────────────────────────
// Admin partner routes smoke
// ────────────────────────────────────────────────────────────────────────

it('admin partner management pages return 200', function (string $route): void {
    $this->actingAs(adminUser())->get(route($route))->assertOk();
})->with([
    'admin.partners.index',
    'admin.partners.create',
]);

// ────────────────────────────────────────────────────────────────────────
// Helpers
// ────────────────────────────────────────────────────────────────────────

function makePartnerUser(string $code = null): \App\Models\User
{
    Role::findOrCreate('partner', 'web');
    Permission::findOrCreate('access-partner', 'web');

    $user = \App\Models\User::factory()->create();
    $user->assignRole('partner');
    $user->givePermissionTo('access-partner');

    PartnerProfile::create([
        'user_id'                 => $user->id,
        'referral_code'           => $code ?? strtoupper(substr(md5((string) $user->id), 0, 8)),
        'status'                  => 'active',
        'commission_rate_percent' => 10.0,
    ]);

    return $user->load('customer');
}
