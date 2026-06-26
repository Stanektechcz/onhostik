<?php

declare(strict_types=1);

use App\Actions\Fortify\CreateNewUser;
use App\Domains\Billing\Actions\ProcessMockPaymentAction;
use App\Domains\Billing\Events\InvoicePaid;
use App\Domains\Billing\Models\Payment;
use App\Domains\Partner\Enums\CommissionStatus;
use App\Domains\Partner\Enums\PartnerStatus;
use App\Domains\Partner\Enums\ReferralStatus;
use App\Domains\Partner\Listeners\CreateCommissionOnInvoicePaid;
use App\Domains\Partner\Models\PartnerCommission;
use App\Domains\Partner\Models\PartnerProfile;
use App\Domains\Partner\Models\PartnerReferral;
use App\Domains\Partner\Services\ReferralTracker;
use App\Models\User;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ────────────────────────────────────────────────────────────────────────
// Helper
// ────────────────────────────────────────────────────────────────────────

function makePartner(float $rate = 10.0, string $status = 'active'): User
{
    Role::findOrCreate('partner', 'web');
    Permission::findOrCreate('access-partner', 'web');

    $user = User::factory()->create();
    $user->assignRole('partner');
    $user->givePermissionTo('access-partner');

    PartnerProfile::create([
        'user_id'                 => $user->id,
        'referral_code'           => 'TEST' . strtoupper(substr(md5((string) $user->id), 0, 4)),
        'status'                  => $status,
        'commission_rate_percent' => $rate,
    ]);

    return $user->load('customer');
}

// ────────────────────────────────────────────────────────────────────────
// Middleware / referral visit tracking
// ────────────────────────────────────────────────────────────────────────

it('visit with ?ref= creates a referral visitor record', function (): void {
    $partner = makePartner();
    $profile = $partner->partnerProfile ?? PartnerProfile::where('user_id', $partner->id)->first();

    $this->get('/?ref=' . $profile->referral_code);

    expect(PartnerReferral::where('partner_profile_id', $profile->id)
        ->where('status', ReferralStatus::Visitor->value)
        ->exists()
    )->toBeTrue();
});

it('duplicate visit from same IP does not create second referral record', function (): void {
    $partner = makePartner();
    $profile = PartnerProfile::where('user_id', $partner->id)->first();

    $this->get('/?ref=' . $profile->referral_code);
    $this->get('/?ref=' . $profile->referral_code);

    expect(PartnerReferral::where('partner_profile_id', $profile->id)->count())->toBe(1);
});

it('visit with invalid referral code does not create a record', function (): void {
    $this->get('/?ref=INVALID99');

    expect(PartnerReferral::count())->toBe(0);
});

it('visit for paused partner does not create referral', function (): void {
    $partner = makePartner(status: 'paused');
    $profile = PartnerProfile::where('user_id', $partner->id)->first();

    $this->get('/?ref=' . $profile->referral_code);

    expect(PartnerReferral::count())->toBe(0);
});

// ────────────────────────────────────────────────────────────────────────
// Registration hook
// ────────────────────────────────────────────────────────────────────────

it('registration with referral session links referral to new user', function (): void {
    $partner = makePartner();
    $profile = PartnerProfile::where('user_id', $partner->id)->first();

    // Simulate middleware having stored code in session
    session([config('partner.session_key') => $profile->referral_code]);

    $newUser = app(CreateNewUser::class)->create([
        'name'                  => 'Ref Customer',
        'email'                 => 'refcustomer@example.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
    ]);

    $referral = PartnerReferral::where('referred_user_id', $newUser->id)->first();

    expect($referral)->not->toBeNull()
        ->and($referral->partner_profile_id)->toBe($profile->id)
        ->and($referral->status->value)->toBe(ReferralStatus::Registered->value);
});

it('self-referral during registration is blocked', function (): void {
    $partner = makePartner();
    $profile = PartnerProfile::where('user_id', $partner->id)->first();

    session([config('partner.session_key') => $profile->referral_code]);

    // Partner trying to refer themselves — tracker should guard against this
    $tracker = app(ReferralTracker::class);
    $tracker->linkRegistration($partner, $profile->referral_code);

    expect(PartnerReferral::where('referred_user_id', $partner->id)->exists())->toBeFalse();
});

// ────────────────────────────────────────────────────────────────────────
// Commission creation on InvoicePaid
// ────────────────────────────────────────────────────────────────────────

it('paid order invoice creates pending commission for referred customer', function (): void {
    $partner = makePartner(rate: 10.0);
    $profile = PartnerProfile::where('user_id', $partner->id)->first();

    // Create a referred customer
    $customer = customerUser();
    PartnerReferral::create([
        'partner_profile_id'   => $profile->id,
        'referred_user_id'     => $customer->id,
        'referred_customer_id' => $customer->customer->id,
        'referral_code'        => $profile->referral_code,
        'first_seen_at'        => now(),
        'registered_at'        => now(),
        'status'               => ReferralStatus::Registered->value,
    ]);

    // Place and pay an order
    ['invoice' => $invoice] = placeOrder($customer);

    app(ProcessMockPaymentAction::class)->execute($invoice);

    $commission = PartnerCommission::where('invoice_id', $invoice->id)->first();

    expect($commission)->not->toBeNull()
        ->and($commission->status->value)->toBe(CommissionStatus::Pending->value)
        ->and($commission->partner_profile_id)->toBe($profile->id)
        ->and($commission->amount)->toBeGreaterThan(0);
});

it('InvoicePaid replay does not create duplicate commission', function (): void {
    $partner = makePartner();
    $profile = PartnerProfile::where('user_id', $partner->id)->first();

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
    app(ProcessMockPaymentAction::class)->execute($invoice); // replay

    expect(PartnerCommission::where('invoice_id', $invoice->id)->count())->toBe(1);
});

it('credit topup invoice does not create commission', function (): void {
    $partner = makePartner();
    $profile = PartnerProfile::where('user_id', $partner->id)->first();

    $customer = customerUser();
    PartnerReferral::create([
        'partner_profile_id'   => $profile->id,
        'referred_user_id'     => $customer->id,
        'referred_customer_id' => $customer->customer->id,
        'referral_code'        => $profile->referral_code,
        'first_seen_at'        => now(),
        'status'               => ReferralStatus::Registered->value,
    ]);

    // Create a credit top-up invoice via the existing action
    $topupInvoice = app(\App\Domains\Billing\Actions\CreateCreditTopUpInvoiceAction::class)
        ->execute($customer->customer, \Brick\Money\Money::of(500, 'CZK'));

    app(ProcessMockPaymentAction::class)->execute($topupInvoice);

    expect(PartnerCommission::where('invoice_id', $topupInvoice->id)->exists())->toBeFalse();
});

it('customer without referral does not create commission', function (): void {
    $customer = customerUser();
    ['invoice' => $invoice] = placeOrder($customer);

    app(ProcessMockPaymentAction::class)->execute($invoice);

    expect(PartnerCommission::count())->toBe(0);
});

// ────────────────────────────────────────────────────────────────────────
// Partner UI routes (real data)
// ────────────────────────────────────────────────────────────────────────

it('partner sub-pages return 200 for a partner with profile', function (string $route): void {
    $partner = makePartner();

    $this->actingAs($partner)->get(route($route))->assertOk();
})->with([
    'partner.dashboard',
    'partner.referrals',
    'partner.commissions',
    'partner.payouts',
    'partner.assets',
    'partner.profile',
]);

it('admin partners index returns 200', function (): void {
    $this->actingAs(adminUser())->get(route('admin.partners.index'))->assertOk();
});
