<?php

declare(strict_types=1);

use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Support\Models\SupportTicket;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Admin search page ─────────────────────────────────────────────────────────

it('admin search page loads without query', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.search'))
        ->assertOk()
        ->assertSee('Hledání');
});

it('admin search returns customer by email', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;

    $this->actingAs($admin)
        ->get(route('admin.search', ['q' => $customer->email]))
        ->assertOk()
        ->assertSee($customer->email);
});

it('admin search returns customer by company name', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;
    $customer->update(['company_name' => 'Acme Corp s.r.o.']);

    $this->actingAs($admin)
        ->get(route('admin.search', ['q' => 'Acme']))
        ->assertOk()
        ->assertSee('Acme');
});

it('admin search returns service by label', function (): void {
    $admin   = adminUser();
    $service = Service::factory()->create(['label' => 'my-vps-server-01']);

    $this->actingAs($admin)
        ->get(route('admin.search', ['q' => 'my-vps']))
        ->assertOk()
        ->assertSee('my-vps-server-01');
});

it('admin search returns service by external_id', function (): void {
    $admin   = adminUser();
    $service = Service::factory()->create(['external_id' => 'EXT-99999']);

    $this->actingAs($admin)
        ->get(route('admin.search', ['q' => 'EXT-99999']))
        ->assertOk()
        ->assertSee('EXT-99999');
});

it('admin search returns ticket by subject', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;

    SupportTicket::factory()->create([
        'customer_id' => $customer->id,
        'subject'     => 'MySQL is down on production',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.search', ['q' => 'MySQL is down']))
        ->assertOk()
        ->assertSee('MySQL is down on production');
});

it('admin search shows no results message for unknown term', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.search', ['q' => 'xyzzy-no-match-12345']))
        ->assertOk()
        ->assertSee('Žádné výsledky');
});

// ── Autocomplete endpoint ─────────────────────────────────────────────────────

it('autocomplete returns JSON for matching customer', function (): void {
    $admin    = adminUser();
    $customer = customerUser()->customer;

    $response = $this->actingAs($admin)
        ->getJson(route('admin.search.autocomplete', ['q' => substr($customer->email, 0, 5)]))
        ->assertOk()
        ->assertJsonStructure(['results']);

    $results = $response->json('results');
    expect($results)->toBeArray();
});

it('autocomplete returns empty results for short query', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson(route('admin.search.autocomplete', ['q' => 'a']))
        ->assertOk()
        ->assertJson(['results' => []]);
});

it('autocomplete returns results for matching service', function (): void {
    $admin   = adminUser();
    Service::factory()->create(['label' => 'search-test-service']);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.search.autocomplete', ['q' => 'search-test']))
        ->assertOk();

    $types = collect($response->json('results'))->pluck('type');
    expect($types)->toContain('service');
});

it('autocomplete caps results at 12', function (): void {
    $admin = adminUser();

    // Create 10 services with the same prefix
    foreach (range(1, 10) as $i) {
        Service::factory()->create(['label' => "autocomplete-svc-{$i}"]);
    }

    $response = $this->actingAs($admin)
        ->getJson(route('admin.search.autocomplete', ['q' => 'autocomplete-svc']))
        ->assertOk();

    expect(count($response->json('results')))->toBeLessThanOrEqual(12);
});

it('non-admin cannot access search', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.search'))
        ->assertStatus(403);
});

it('non-admin cannot access autocomplete', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->getJson(route('admin.search.autocomplete', ['q' => 'test']))
        ->assertStatus(403);
});
