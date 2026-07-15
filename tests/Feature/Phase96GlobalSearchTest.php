<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Enums\VatScenario;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Shared\Enums\Currency;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use Brick\Money\Money;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Access control ────────────────────────────────────────────────────────────

it('guest is redirected from global search', function (): void {
    $this->getJson(route('admin.search.quick', ['q' => 'test']))->assertUnauthorized();
});

it('customer cannot access global search', function (): void {
    $user = customerUser();
    $this->actingAs($user)->getJson(route('admin.search.quick', ['q' => 'test']))->assertForbidden();
});

it('admin can access global search', function (): void {
    $admin = adminUser();
    $this->actingAs($admin)->getJson(route('admin.search.quick', ['q' => 'test']))->assertOk();
});

// ── Query length guard ────────────────────────────────────────────────────────

it('returns empty when query is shorter than 2 chars', function (): void {
    $admin    = adminUser();
    $response = $this->actingAs($admin)->getJson(route('admin.search.quick', ['q' => 'a']));

    $response->assertOk()->assertJson(['results' => []]);
});

// ── Customer search ───────────────────────────────────────────────────────────

it('finds customers by company name', function (): void {
    $admin = adminUser();
    Customer::factory()->create(['company_name' => 'Acme Corp s.r.o.', 'email' => 'acme@example.com']);

    $response = $this->actingAs($admin)->getJson(route('admin.search.quick', ['q' => 'Acme']));

    $response->assertOk()
        ->assertJsonPath('results.0.type', 'customer')
        ->assertJsonPath('results.0.title', 'Acme Corp s.r.o.');
});

it('finds customers by email', function (): void {
    $admin = adminUser();
    Customer::factory()->create(['email' => 'unique-find@example.cz']);

    $response = $this->actingAs($admin)->getJson(route('admin.search.quick', ['q' => 'unique-find']));

    $response->assertOk()
        ->assertJsonFragment(['type' => 'customer']);
});

// ── Service search ────────────────────────────────────────────────────────────

it('finds services by label', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    Service::factory()->create([
        'customer_id' => $customer->customer->id,
        'label'       => 'mujweb-produkce',
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.search.quick', ['q' => 'mujweb']));

    $response->assertOk()
        ->assertJsonFragment(['type' => 'service', 'title' => 'mujweb-produkce']);
});

// ── Invoice search ────────────────────────────────────────────────────────────

it('finds invoices by number', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    Invoice::factory()->create([
        'customer_id'  => $customer->customer->id,
        'number'       => 'INV-2026-9999',
        'type'         => InvoiceType::Invoice,
        'status'       => InvoiceStatus::Paid,
        'vat_scenario' => VatScenario::CzechB2C,
        'currency'     => Currency::CZK,
        'subtotal'     => Money::ofMinor(10000, 'CZK'),
        'tax_amount'   => Money::ofMinor(2100, 'CZK'),
        'total'        => Money::ofMinor(12100, 'CZK'),
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.search.quick', ['q' => 'INV-2026-9999']));

    $response->assertOk()
        ->assertJsonFragment(['type' => 'invoice', 'title' => 'Faktura INV-2026-9999']);
});

// ── Ticket search ─────────────────────────────────────────────────────────────

it('finds tickets by subject', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    SupportTicket::factory()->create([
        'customer_id' => $customer->customer->id,
        'subject'     => 'Problém s FTP přístupem',
        'status'      => TicketStatus::Open,
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.search.quick', ['q' => 'FTP přístup']));

    $response->assertOk()
        ->assertJsonFragment(['type' => 'ticket', 'title' => 'Problém s FTP přístupem']);
});

// ── No match ──────────────────────────────────────────────────────────────────

it('returns empty results when nothing matches', function (): void {
    $admin = adminUser();

    $response = $this->actingAs($admin)->getJson(route('admin.search.quick', ['q' => 'ZZZNOMATCH999']));

    $response->assertOk()->assertJson(['results' => []]);
});

// ── Mixed results structure ───────────────────────────────────────────────────

it('each result has required fields: type icon title subtitle url', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    Customer::factory()->create(['company_name' => 'SearchTest Corp']);

    $response = $this->actingAs($admin)->getJson(route('admin.search.quick', ['q' => 'SearchTest']));

    $response->assertOk();
    $result = $response->json('results.0');

    expect($result)->toHaveKeys(['type', 'icon', 'title', 'subtitle', 'url']);
});
