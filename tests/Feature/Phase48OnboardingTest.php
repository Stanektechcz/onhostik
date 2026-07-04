<?php

declare(strict_types=1);

use App\Domains\Customer\Actions\EnrollInDripSequenceAction;
use App\Domains\Customer\Models\Customer;
use App\Domains\Customer\Models\CustomerAddress;
use App\Domains\Customer\Services\OnboardingService;
use App\Models\EmailDripEnrollment;
use App\Models\EmailDripSequence;
use App\Models\EmailDripStep;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── OnboardingService ─────────────────────────────────────────────────────────

it('OnboardingService reports profile incomplete when no company_name or phone', function (): void {
    $user     = customerUser(['company_name' => null, 'phone' => null]);
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $steps = app(OnboardingService::class)->steps($customer);
    expect($steps['profile'])->toBeFalse();
});

it('OnboardingService reports profile complete when company_name is set', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);
    $customer->update(['company_name' => 'ACME s.r.o.']);

    $steps = app(OnboardingService::class)->steps($customer);
    expect($steps['profile'])->toBeTrue();
});

it('OnboardingService reports profile complete when phone is set', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);
    $customer->update(['phone' => '+420777123456']);

    $steps = app(OnboardingService::class)->steps($customer);
    expect($steps['profile'])->toBeTrue();
});

it('OnboardingService reports billing_address incomplete when no addresses', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $steps = app(OnboardingService::class)->steps($customer);
    expect($steps['billing_address'])->toBeFalse();
});

it('OnboardingService reports billing_address complete when address exists', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    CustomerAddress::create([
        'customer_id'  => $customer->id,
        'type'         => 'billing',
        'street'       => 'Hlavní 1',
        'city'         => 'Praha',
        'zip'          => '11000',
        'country_code' => 'CZ',
        'is_primary'   => true,
    ]);

    $steps = app(OnboardingService::class)->steps($customer);
    expect($steps['billing_address'])->toBeTrue();
});

it('OnboardingService reports two_factor incomplete when not confirmed', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $steps = app(OnboardingService::class)->steps($customer);
    expect($steps['two_factor'])->toBeFalse();
});

it('OnboardingService reports two_factor complete when 2FA confirmed', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);
    $user->update(['two_factor_confirmed_at' => now()]);

    $steps = app(OnboardingService::class)->steps($customer);
    expect($steps['two_factor'])->toBeTrue();
});

it('OnboardingService percent returns 0 for fresh customer', function (): void {
    $user     = customerUser(['company_name' => null, 'phone' => null]);
    $customer = $user->customer;
    assert($customer instanceof Customer);

    expect(app(OnboardingService::class)->percent($customer))->toBe(0);
});

it('OnboardingService percent returns 100 when all steps complete', function (): void {
    $user     = customerUser(['company_name' => 'ACME s.r.o.', 'phone' => null]);
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $user->update(['two_factor_confirmed_at' => now()]);
    CustomerAddress::create([
        'customer_id'  => $customer->id,
        'type'         => 'billing',
        'street'       => 'X',
        'city'         => 'Y',
        'zip'          => '12345',
        'country_code' => 'CZ',
        'is_primary'   => true,
    ]);

    // Seed a service via factory (has customer_id)
    \App\Domains\Provisioning\Models\Service::factory()->create(['customer_id' => $customer->id]);

    expect(app(OnboardingService::class)->percent($customer))->toBe(100);
});

it('OnboardingService isComplete returns false for fresh customer', function (): void {
    $user     = customerUser(['company_name' => null, 'phone' => null]);
    $customer = $user->customer;
    assert($customer instanceof Customer);

    expect(app(OnboardingService::class)->isComplete($customer))->toBeFalse();
});

// ── EnrollInDripSequenceAction ────────────────────────────────────────────────

it('EnrollInDripSequenceAction enrolls customer in active signup sequences', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $sequence = EmailDripSequence::create([
        'name'          => 'Onboarding',
        'trigger_event' => 'signup',
        'is_active'     => true,
        'description'   => 'Welcome sequence',
    ]);

    EmailDripStep::create([
        'drip_sequence_id' => $sequence->id,
        'subject'          => 'Vítejte!',
        'body_html'        => '<p>Obsah emailu</p>',
        'delay_days'       => 0,
        'sort_order'       => 1,
    ]);

    app(EnrollInDripSequenceAction::class)->execute($customer, 'signup');

    $enrollment = EmailDripEnrollment::where('drip_sequence_id', $sequence->id)
        ->where('customer_id', $customer->id)
        ->first();

    expect($enrollment)->not->toBeNull();
    expect($enrollment->email)->toBe($customer->email);
    expect($enrollment->next_step_index)->toBe(0);
});

it('EnrollInDripSequenceAction is idempotent — no duplicate enrollment', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $sequence = EmailDripSequence::create([
        'name'          => 'Onboarding 2',
        'trigger_event' => 'signup',
        'is_active'     => true,
    ]);

    $action = app(EnrollInDripSequenceAction::class);
    $action->execute($customer, 'signup');
    $action->execute($customer, 'signup');

    $count = EmailDripEnrollment::where('drip_sequence_id', $sequence->id)
        ->where('customer_id', $customer->id)
        ->count();

    expect($count)->toBe(1);
});

it('EnrollInDripSequenceAction skips inactive sequences', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    EmailDripSequence::create([
        'name'          => 'Inactive',
        'trigger_event' => 'signup',
        'is_active'     => false,
    ]);

    app(EnrollInDripSequenceAction::class)->execute($customer, 'signup');

    expect(EmailDripEnrollment::where('customer_id', $customer->id)->count())->toBe(0);
});

// ── Panel dashboard with onboarding ──────────────────────────────────────────

it('panel dashboard shows onboarding checklist for new customer', function (): void {
    $user = customerUser();

    $response = $this->actingAs($user)
        ->get(route('panel.dashboard'))
        ->assertOk();

    expect($response->viewData('showOnboarding'))->toBeTrue();
    expect($response->viewData('onboardingSteps'))->toHaveKey('profile');
    expect($response->viewData('onboardingPercent'))->toBeInt();
});

it('panel dashboard hides onboarding when already dismissed', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);
    $customer->update(['onboarding_completed_at' => now()]);

    $response = $this->actingAs($user)
        ->get(route('panel.dashboard'))
        ->assertOk();

    expect($response->viewData('showOnboarding'))->toBeFalse();
});

// ── Dismiss endpoint ──────────────────────────────────────────────────────────

it('customer can dismiss onboarding checklist', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    expect($customer->onboarding_completed_at)->toBeNull();

    $this->actingAs($user)
        ->post(route('panel.onboarding.dismiss'))
        ->assertRedirect(route('panel.dashboard'));

    $customer->refresh();
    expect($customer->onboarding_completed_at)->not->toBeNull();
});

it('dismissing onboarding twice is idempotent', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    assert($customer instanceof Customer);

    $this->actingAs($user)->post(route('panel.onboarding.dismiss'));
    $first = $customer->refresh()->onboarding_completed_at;

    $this->actingAs($user)->post(route('panel.onboarding.dismiss'));
    $second = $customer->refresh()->onboarding_completed_at;

    expect($first?->toDateTimeString())->toBe($second?->toDateTimeString());
});
