<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceSeries;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Enums\VatScenario;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Customer\Models\Customer;
use App\Domains\Reseller\Models\ResellerProfile;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Brick\Money\Money;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

function makeActiveReseller(string $suffix = 'A'): array
{
    Permission::findOrCreate('access-reseller', 'web');

    $user = customerUser();
    $user->givePermissionTo('access-reseller');

    $profile = ResellerProfile::factory()->active()->create(['user_id' => $user->id]);

    return ['user' => $user, 'profile' => $profile];
}

/** Create a paid invoice for a customer with a given total in minor units. */
function resellerTestInvoice(Customer $customer, int $totalMinor, InvoiceStatus $status = InvoiceStatus::Paid): Invoice
{
    static $seq = 0;
    $seq++;

    return Invoice::create([
        'customer_id'                  => $customer->id,
        'type'                         => InvoiceType::Invoice,
        'purpose'                      => 'order',
        'series'                       => InvoiceSeries::Czech->value,
        'number'                       => "CZ-RESEL-{$seq}",
        'status'                       => $status,
        'vat_scenario'                 => VatScenario::CzechB2C,
        'currency'                     => 'CZK',
        'subtotal'                     => Money::ofMinor((int) round($totalMinor / 1.21), 'CZK'),
        'tax_amount'                   => Money::ofMinor($totalMinor - (int) round($totalMinor / 1.21), 'CZK'),
        'total'                        => Money::ofMinor($totalMinor, 'CZK'),
        'variable_symbol'              => "20260{$seq}",
        'issue_date'                   => now()->toDateString(),
        'taxable_supply_date'          => now()->toDateString(),
        'due_date'                     => now()->toDateString(),
        'paid_at'                      => $status === InvoiceStatus::Paid ? now() : null,
        'snapshot_name'                => 'Test',
        'snapshot_company'             => 'Test s.r.o.',
        'snapshot_street'              => 'Testovací 1',
        'snapshot_city'                => 'Praha',
        'snapshot_zip'                 => '11000',
        'snapshot_country_code'        => 'CZ',
        'snapshot_registration_number' => '12345678',
    ]);
}

// ── Access control ────────────────────────────────────────────────────────────

it('reseller dashboard requires access-reseller permission', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('reseller.dashboard'))
        ->assertForbidden();
});

it('non-authenticated user is redirected from reseller dashboard', function (): void {
    $this->get(route('reseller.dashboard'))
        ->assertRedirect(route('login'));
});

// ── Zero-state (no sub-customers) ────────────────────────────────────────────

it('reseller dashboard returns 200 with zero KPIs when no sub-customers', function (): void {
    ['user' => $user] = makeActiveReseller('ZERO');

    $response = $this->actingAs($user)
        ->get(route('reseller.dashboard'))
        ->assertOk()
        ->assertViewIs('reseller.dashboard');

    expect($response->viewData('customerCount'))->toBe(0)
        ->and($response->viewData('orderCount'))->toBe(0)
        ->and($response->viewData('revenueMinor'))->toBe(0);
});

// ── KPI computation with sub-customers ───────────────────────────────────────

it('reseller dashboard shows correct customer count for linked sub-customers', function (): void {
    ['user' => $user, 'profile' => $profile] = makeActiveReseller('COUNT');

    Customer::factory()->count(3)->create(['reseller_id' => $profile->id]);

    $response = $this->actingAs($user)
        ->get(route('reseller.dashboard'))
        ->assertOk();

    expect($response->viewData('customerCount'))->toBe(3);
});

it('reseller dashboard shows correct order count across sub-customers', function (): void {
    ['user' => $user, 'profile' => $profile] = makeActiveReseller('ORDERS');

    $c1 = Customer::factory()->create(['reseller_id' => $profile->id]);
    $c2 = Customer::factory()->create(['reseller_id' => $profile->id]);

    $orderRow = fn (int $customerId) => [
        'uuid'         => \Illuminate\Support\Str::uuid()->toString(),
        'customer_id'  => $customerId,
        'status'       => 'active',
        'currency'     => 'CZK',
        'subtotal'     => 8264,
        'tax_amount'   => 1736,
        'total'        => 10000,
        'vat_scenario' => 'cz_b2c',
        'created_at'   => now(),
        'updated_at'   => now(),
    ];

    \Illuminate\Support\Facades\DB::table('orders')->insert([
        $orderRow($c1->id),
        $orderRow($c1->id),
        $orderRow($c2->id),
    ]);

    $response = $this->actingAs($user)
        ->get(route('reseller.dashboard'))
        ->assertOk();

    expect($response->viewData('orderCount'))->toBe(3);
});

it('reseller dashboard revenue sums only paid invoices for sub-customers', function (): void {
    ['user' => $user, 'profile' => $profile] = makeActiveReseller('REV');

    $customer = Customer::factory()->create(['reseller_id' => $profile->id]);

    resellerTestInvoice($customer, 50_000, InvoiceStatus::Paid);
    resellerTestInvoice($customer, 20_000, InvoiceStatus::Sent);

    $response = $this->actingAs($user)
        ->get(route('reseller.dashboard'))
        ->assertOk();

    expect($response->viewData('revenueMinor'))->toBe(50_000);
});

it('reseller dashboard revenue excludes invoices from non-linked customers', function (): void {
    ['user' => $user, 'profile' => $profile] = makeActiveReseller('REVISO');

    $ownCustomer = Customer::factory()->create(['reseller_id' => $profile->id]);
    resellerTestInvoice($ownCustomer, 30_000, InvoiceStatus::Paid);

    $otherCustomer = Customer::factory()->create(['reseller_id' => null]);
    resellerTestInvoice($otherCustomer, 99_000, InvoiceStatus::Paid);

    $response = $this->actingAs($user)
        ->get(route('reseller.dashboard'))
        ->assertOk();

    expect($response->viewData('revenueMinor'))->toBe(30_000);
});

it('reseller dashboard includes recentCustomers in view data', function (): void {
    ['user' => $user, 'profile' => $profile] = makeActiveReseller('RECENT');

    Customer::factory()->count(2)->create(['reseller_id' => $profile->id]);

    $response = $this->actingAs($user)
        ->get(route('reseller.dashboard'))
        ->assertOk();

    expect($response->viewData('recentCustomers'))->toHaveCount(2);
});

it('reseller dashboard renders chart data with 6 months', function (): void {
    ['user' => $user] = makeActiveReseller('CHART');

    $response = $this->actingAs($user)
        ->get(route('reseller.dashboard'))
        ->assertOk();

    expect($response->viewData('chartLabels'))->toHaveCount(6)
        ->and($response->viewData('chartRevenue'))->toHaveCount(6);
});

// ── Inactive reseller (pending/suspended) ─────────────────────────────────────

it('pending reseller sees zero-state dashboard', function (): void {
    Permission::findOrCreate('access-reseller', 'web');

    $user = customerUser();
    $user->givePermissionTo('access-reseller');

    ResellerProfile::factory()->create(['user_id' => $user->id, 'status' => 'pending']);

    $response = $this->actingAs($user)
        ->get(route('reseller.dashboard'))
        ->assertOk();

    expect($response->viewData('customerCount'))->toBe(0)
        ->and($response->viewData('revenueMinor'))->toBe(0);
});
