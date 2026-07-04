<?php

declare(strict_types=1);

use App\Domains\Products\Models\PricingPlan;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function p37MakeDomain(\App\Models\User $user, array $overrides = []): DomainRegistration
{
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'ns-test-' . uniqid(),
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    return DomainRegistration::create(array_merge([
        'service_id'  => $service->id,
        'domain'      => 'nstest-' . uniqid(),
        'tld'         => 'cz',
        'registrar'   => 'wedos',
        'auto_renew'  => true,
        'nameservers' => ['ns1.wedos.net', 'ns2.wedos.net'],
        'expires_at'  => now()->addYear()->toDateString(),
    ], $overrides));
}

function p37MakeService(\App\Models\User $user): Service
{
    $productId = \App\Domains\Products\Models\Product::value('id');

    return Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'svc-' . uniqid(),
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'next_due_date'       => now()->addDays(20)->toDateString(),
    ]);
}

// ── Domain Nameserver Management ──────────────────────────────────────────────

it('customer can view the domain show page', function (): void {
    $user   = customerUser();
    $domain = p37MakeDomain($user);

    $this->actingAs($user)
        ->get(route('panel.domains.show', $domain))
        ->assertOk()
        ->assertSee('Nameservery');
});

it('customer can update nameservers via PUT', function (): void {
    $user   = customerUser();
    $domain = p37MakeDomain($user);

    $this->actingAs($user)
        ->put(route('panel.domains.nameservers', $domain), [
            'nameservers' => ['ns1.example.com', 'ns2.example.com'],
        ])
        ->assertRedirect();

    $domain->refresh();
    expect($domain->nameservers)->toBe(['ns1.example.com', 'ns2.example.com']);
});

it('nameserver update is logged in activity log', function (): void {
    $user   = customerUser();
    $domain = p37MakeDomain($user);

    $this->actingAs($user)
        ->put(route('panel.domains.nameservers', $domain), [
            'nameservers' => ['ns3.host.cz', 'ns4.host.cz'],
        ])
        ->assertRedirect();

    expect(
        Activity::where('log_name', 'domain')
            ->where('description', 'domain.nameservers_updated')
            ->exists()
    )->toBeTrue();
});

it('nameserver update rejects more than 4 entries', function (): void {
    $user   = customerUser();
    $domain = p37MakeDomain($user);

    $this->actingAs($user)
        ->put(route('panel.domains.nameservers', $domain), [
            'nameservers' => ['ns1.a.com', 'ns2.a.com', 'ns3.a.com', 'ns4.a.com', 'ns5.a.com'],
        ])
        ->assertSessionHasErrors('nameservers');
});

it('nameserver update rejects invalid hostname format', function (): void {
    $user   = customerUser();
    $domain = p37MakeDomain($user);

    $this->actingAs($user)
        ->put(route('panel.domains.nameservers', $domain), [
            'nameservers' => ['not a hostname!!'],
        ])
        ->assertSessionHasErrors('nameservers.0');
});

it('nameserver update requires at least 1 entry', function (): void {
    $user   = customerUser();
    $domain = p37MakeDomain($user);

    $this->actingAs($user)
        ->put(route('panel.domains.nameservers', $domain), ['nameservers' => []])
        ->assertSessionHasErrors('nameservers');
});

it('nameserver update is forbidden for another customer', function (): void {
    $owner    = customerUser();
    $intruder = customerUser();
    $domain   = p37MakeDomain($owner);

    $this->actingAs($intruder)
        ->put(route('panel.domains.nameservers', $domain), [
            'nameservers' => ['ns1.evil.com'],
        ])
        ->assertForbidden();
});

it('unauthenticated user cannot update nameservers', function (): void {
    $user   = customerUser();
    $domain = p37MakeDomain($user);

    $this->put(route('panel.domains.nameservers', $domain), [
        'nameservers' => ['ns1.example.com'],
    ])->assertRedirect(route('login'));
});

// ── Plan Change Billing Preview ───────────────────────────────────────────────

it('changePlanPreview returns valid JSON structure', function (): void {
    $user = customerUser();

    $plan = PricingPlan::where('is_active', true)->first();
    expect($plan)->not->toBeNull('Seeder must provide at least one active plan');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $plan->product_id,
        'status'              => ServiceStatus::Active,
        'label'               => 'preview-svc-' . uniqid(),
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'next_due_date'       => now()->addDays(20)->toDateString(),
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('panel.services.change-plan-preview', $service) . '?plan_id=' . $plan->id)
        ->assertOk()
        ->assertJsonStructure([
            'current_plan_price',
            'new_plan_price',
            'prorated_days',
            'prorated_amount',
            'currency',
            'is_upgrade',
        ]);

    $data = $response->json();
    expect($data['currency'])->toBe('CZK');
    expect($data['prorated_days'])->toBeGreaterThanOrEqual(0);
});

it('changePlanPreview rejects missing plan_id', function (): void {
    $user    = customerUser();
    $service = p37MakeService($user);

    $this->actingAs($user)
        ->getJson(route('panel.services.change-plan-preview', $service))
        ->assertUnprocessable();
});

it('changePlanPreview rejects plan from a different product', function (): void {
    $user    = customerUser();
    $service = p37MakeService($user);

    $otherProductId = \App\Domains\Products\Models\Product::where('id', '!=', $service->product_id)->value('id');

    if ($otherProductId === null) {
        $this->markTestSkipped('Only one product in catalog; cannot test cross-product rejection.');
    }

    $otherPlan = PricingPlan::where('product_id', $otherProductId)->where('is_active', true)->first();

    if ($otherPlan === null) {
        $this->markTestSkipped('No plans for other product.');
    }

    $this->actingAs($user)
        ->get(route('panel.services.change-plan-preview', $service) . '?plan_id=' . $otherPlan->id)
        ->assertStatus(422);
});

it('changePlanPreview is forbidden for another customer\'s service', function (): void {
    $owner    = customerUser();
    $intruder = customerUser();
    $service  = p37MakeService($owner);

    $plan = PricingPlan::where('product_id', $service->product_id)->first();

    if ($plan === null) {
        $this->markTestSkipped('No plans.');
    }

    $this->actingAs($intruder)
        ->get(route('panel.services.change-plan-preview', $service) . '?plan_id=' . $plan->id)
        ->assertForbidden();
});

it('unauthenticated user cannot access changePlanPreview', function (): void {
    $user    = customerUser();
    $service = p37MakeService($user);

    $this->get(route('panel.services.change-plan-preview', $service) . '?plan_id=1')
        ->assertRedirect(route('login'));
});

// ── Change plan page loads ─────────────────────────────────────────────────────

it('change plan page renders for active service', function (): void {
    $user    = customerUser();
    $service = p37MakeService($user);

    $this->actingAs($user)
        ->get(route('panel.services.change-plan', $service))
        ->assertOk()
        ->assertViewIs('panel.services.change-plan');
});
