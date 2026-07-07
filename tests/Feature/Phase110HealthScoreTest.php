<?php

declare(strict_types=1);

use App\Console\Commands\ComputeHealthScoresCommand;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Bi\Actions\CustomerHealthScorer;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Database\Factories\InvoiceFactory;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── CustomerHealthScorer unit tests ──────────────────────────────────────────

it('CustomerHealthScorer gives 90 for healthy customer with active service and recent login', function (): void {
    $user = customerUser();
    $user->update(['last_login_at' => now()->subDay()]);

    Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    $scorer = new CustomerHealthScorer();
    $score  = $scorer->score($user->customer->fresh(['invoices', 'services', 'supportTickets', 'user']));

    expect($score)->toBeGreaterThanOrEqual(80);
});

it('CustomerHealthScorer penalises overdue invoice', function (): void {
    $user = customerUser();

    InvoiceFactory::new()->create([
        'customer_id' => $user->customer->id,
        'status'      => InvoiceStatus::Overdue,
    ]);

    $scorer     = new CustomerHealthScorer();
    $baseScore  = $scorer->score(Customer::factory()->create());
    $overdueSc  = $scorer->score($user->customer->fresh(['invoices', 'services', 'supportTickets', 'user']));

    expect($overdueSc)->toBeLessThan($baseScore);
});

it('CustomerHealthScorer score is clamped between 0 and 100', function (): void {
    $user = customerUser();

    // 3 overdue invoices + suspended service = heavily penalised
    InvoiceFactory::new()->count(3)->create([
        'customer_id' => $user->customer->id,
        'status'      => InvoiceStatus::Overdue,
    ]);
    Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Suspended,
    ]);

    $scorer = new CustomerHealthScorer();
    $score  = $scorer->score($user->customer->fresh(['invoices', 'services', 'supportTickets', 'user']));

    expect($score)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
});

it('CustomerHealthScorer::color returns success for score >= 80', function (): void {
    $scorer = new CustomerHealthScorer();
    expect($scorer->color(80))->toBe('success');
    expect($scorer->color(100))->toBe('success');
});

it('CustomerHealthScorer::color returns warning for score 50-79', function (): void {
    $scorer = new CustomerHealthScorer();
    expect($scorer->color(50))->toBe('warning');
    expect($scorer->color(79))->toBe('warning');
});

it('CustomerHealthScorer::color returns danger for score < 50', function (): void {
    $scorer = new CustomerHealthScorer();
    expect($scorer->color(0))->toBe('danger');
    expect($scorer->color(49))->toBe('danger');
});

// ── Command ──────────────────────────────────────────────────────────────────

it('crm:compute-health-scores persists score to customers table', function (): void {
    $user = customerUser();

    $this->artisan(ComputeHealthScoresCommand::class)->assertExitCode(0);

    $user->customer->refresh();

    expect($user->customer->health_score)->toBeInt()
        ->and($user->customer->health_score_updated_at)->not->toBeNull();
});

// ── Admin views ───────────────────────────────────────────────────────────────

it('admin customer-health-scores index returns 200 for admin', function (): void {
    customerUser()->customer->update(['health_score' => 75, 'health_score_updated_at' => now()]);

    $this->actingAs(adminUser())
        ->get(route('admin.customer-health-scores.index'))
        ->assertOk()
        ->assertSee('Zdraví zákazníků');
});

it('admin customer-health-scores tier filter works', function (): void {
    customerUser()->customer->update(['health_score' => 90, 'health_score_updated_at' => now()]);
    customerUser()->customer->update(['health_score' => 30, 'health_score_updated_at' => now()]);

    $this->actingAs(adminUser())
        ->get(route('admin.customer-health-scores.index', ['tier' => 'healthy']))
        ->assertOk()
        ->assertSee('Zdravý');
});

it('admin customers list shows health score badge after command runs', function (): void {
    $user = customerUser();
    $this->artisan(ComputeHealthScoresCommand::class)->assertExitCode(0);

    $user->customer->refresh();

    $this->actingAs(adminUser())
        ->get(route('admin.customers.index'))
        ->assertOk()
        ->assertSee((string) $user->customer->health_score);
});

it('admin customer-show displays health score widget', function (): void {
    $user = customerUser();
    $user->customer->update(['health_score' => 82, 'health_score_updated_at' => now()]);

    $this->actingAs(adminUser())
        ->get(route('admin.customers.show', $user->customer))
        ->assertOk()
        ->assertSee('82')
        ->assertSee('Zdravý');
});

it('guests cannot access health score dashboard', function (): void {
    $this->get(route('admin.customer-health-scores.index'))
        ->assertRedirect(route('login'));
});
