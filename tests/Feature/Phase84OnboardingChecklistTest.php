<?php

declare(strict_types=1);

use App\Domains\Customer\Models\CustomerOnboardingStep;
use App\Domains\Customer\Services\OnboardingService;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Model ─────────────────────────────────────────────────────────────────────

it('CustomerOnboardingStep isComplete returns true when completed_at is set', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $step = CustomerOnboardingStep::create([
        'customer_id' => $customer->id,
        'step'        => 'profile',
        'completed_at' => now(),
    ]);

    expect($step->isComplete())->toBeTrue();
});

it('CustomerOnboardingStep isComplete returns false when completed_at is null', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $step = CustomerOnboardingStep::create([
        'customer_id'  => $customer->id,
        'step'         => 'profile',
        'completed_at' => null,
    ]);

    expect($step->isComplete())->toBeFalse();
});

// ── Service ───────────────────────────────────────────────────────────────────

it('STEP_LABELS covers all steps', function (): void {
    expect(OnboardingService::STEP_LABELS)->toHaveKey('profile')
        ->and(OnboardingService::STEP_LABELS)->toHaveKey('billing_address')
        ->and(OnboardingService::STEP_LABELS)->toHaveKey('two_factor')
        ->and(OnboardingService::STEP_LABELS)->toHaveKey('first_service');
});

it('steps returns completion map for all steps', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $service = new OnboardingService();
    $steps   = $service->steps($customer);

    expect($steps)->toHaveKey('profile')
        ->and($steps)->toHaveKey('billing_address')
        ->and($steps)->toHaveKey('two_factor')
        ->and($steps)->toHaveKey('first_service');
});

it('percent returns a value between 0 and 100', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $service = new OnboardingService();
    $pct     = $service->percent($customer);
    expect($pct)->toBeGreaterThanOrEqual(0)->and($pct)->toBeLessThanOrEqual(100);
});

it('syncToDB persists completed steps', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    // Make profile step complete
    $customer->update(['company_name' => 'Test s.r.o.']);

    $service = new OnboardingService();
    $service->syncToDB($customer);

    expect(CustomerOnboardingStep::where('customer_id', $customer->id)
        ->where('step', 'profile')
        ->whereNotNull('completed_at')
        ->exists()
    )->toBeTrue();
});

it('syncToDB is idempotent', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    $customer->update(['company_name' => 'Test s.r.o.']);

    $service = new OnboardingService();
    $service->syncToDB($customer);
    $service->syncToDB($customer);

    expect(CustomerOnboardingStep::where('customer_id', $customer->id)
        ->where('step', 'profile')
        ->count()
    )->toBe(1);
});

it('checklist returns ordered steps with labels', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $service   = new OnboardingService();
    $checklist = $service->checklist($customer);

    expect($checklist)->toHaveCount(4);
    expect($checklist->first())->toHaveKey('step')
        ->and($checklist->first())->toHaveKey('label')
        ->and($checklist->first())->toHaveKey('completed')
        ->and($checklist->first())->toHaveKey('completed_at');
});

it('globalStats returns summary including step rates', function (): void {
    $service = new OnboardingService();
    $stats   = $service->globalStats();

    expect($stats)->toHaveKey('total')
        ->and($stats)->toHaveKey('fullyComplete')
        ->and($stats)->toHaveKey('inProgress')
        ->and($stats)->toHaveKey('notStarted')
        ->and($stats)->toHaveKey('stepRates');
});

// ── Admin routes ──────────────────────────────────────────────────────────────

it('admin can view onboarding stats page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.onboarding-stats.index'))
        ->assertOk()
        ->assertSee('Onboarding');
});

it('non-admin cannot view onboarding stats', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.onboarding-stats.index'))
        ->assertStatus(403);
});

// ── Panel dismiss route ───────────────────────────────────────────────────────

it('customer can dismiss onboarding checklist', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    expect($customer->onboarding_completed_at)->toBeNull();

    $this->actingAs($user)
        ->post(route('panel.onboarding.dismiss'))
        ->assertRedirect(route('panel.dashboard'));

    expect($customer->fresh()->onboarding_completed_at)->not->toBeNull();
});

it('dismiss is idempotent', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $this->actingAs($user)->post(route('panel.onboarding.dismiss'));
    $first = $customer->fresh()->onboarding_completed_at;

    $this->actingAs($user)->post(route('panel.onboarding.dismiss'));
    $second = $customer->fresh()->onboarding_completed_at;

    expect($first->eq($second))->toBeTrue();
});
