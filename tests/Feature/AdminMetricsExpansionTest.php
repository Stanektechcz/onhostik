<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceSeries;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Billing\Enums\VatScenario;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Customer\Models\Customer;
use App\Domains\Products\Enums\ProductType;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Products\Models\Product;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Brick\Money\Money;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

/** Create a paid invoice for a customer with the given total (minor units). */
function metricsInvoice(Customer $customer, int $totalMinor, ?string $paidAt = null): Invoice
{
    static $metricsSeq = 0;
    $metricsSeq++;

    return Invoice::create([
        'customer_id'                  => $customer->id,
        'type'                         => InvoiceType::Invoice,
        'purpose'                      => 'order',
        'series'                       => InvoiceSeries::Czech->value,
        'number'                       => "CZ-METR-{$metricsSeq}",
        'status'                       => InvoiceStatus::Paid,
        'vat_scenario'                 => VatScenario::CzechB2C,
        'currency'                     => 'CZK',
        'subtotal'                     => Money::ofMinor((int) round($totalMinor / 1.21), 'CZK'),
        'tax_amount'                   => Money::ofMinor($totalMinor - (int) round($totalMinor / 1.21), 'CZK'),
        'total'                        => Money::ofMinor($totalMinor, 'CZK'),
        'variable_symbol'              => "20260{$metricsSeq}",
        'issue_date'                   => now()->toDateString(),
        'taxable_supply_date'          => now()->toDateString(),
        'due_date'                     => now()->toDateString(),
        'paid_at'                      => $paidAt ?? now()->toDateTimeString(),
        'snapshot_name'                => 'Test',
        'snapshot_company'             => 'Test s.r.o.',
        'snapshot_street'              => 'Testovací 1',
        'snapshot_city'                => 'Praha',
        'snapshot_zip'                 => '11000',
        'snapshot_country_code'        => 'CZ',
        'snapshot_registration_number' => '12345678',
    ]);
}

// ── Basic admin route access ──────────────────────────────────────────────────

it('admin metrics page returns 200 for admin user', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.metrics.index'))
        ->assertOk()
        ->assertViewIs('admin.metrics');
});

it('admin metrics page is forbidden to non-admin users', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.metrics.index'))
        ->assertForbidden();
});

it('admin metrics page passes all required view variables', function (): void {
    $admin = adminUser();

    $response = $this->actingAs($admin)
        ->get(route('admin.metrics.index'))
        ->assertOk();

    expect($response->viewData('revenueByType'))->not->toBeNull()
        ->and($response->viewData('newBuyersThisMonth'))->not->toBeNull()
        ->and($response->viewData('returningBuyersThisMonth'))->not->toBeNull()
        ->and($response->viewData('topProducts'))->not->toBeNull();
});

// ── Revenue by product type ────────────────────────────────────────────────────

it('revenueByType shows zero for all types when no active orders', function (): void {
    $admin = adminUser();

    $response = $this->actingAs($admin)
        ->get(route('admin.metrics.index'))
        ->assertOk();

    $revenueByType = $response->viewData('revenueByType');

    foreach ($revenueByType as $amount) {
        expect($amount)->toBe(0.0);
    }
});

it('revenueByType sums active order items for each product type', function (): void {
    $admin = adminUser();

    // Create a webhosting product and plan
    $webhostingProduct = Product::factory()->create(['type' => ProductType::Webhosting]);
    $plan              = PricingPlan::factory()->create(['product_id' => $webhostingProduct->id, 'price_czk' => 20_000]);

    $customer = customerUser()->customer;

    // Insert order with status=active and an order_item linked to webhosting plan
    $orderId = DB::table('orders')->insertGetId([
        'uuid'         => \Illuminate\Support\Str::uuid()->toString(),
        'customer_id'  => $customer->id,
        'status'       => OrderStatus::Active->value,
        'currency'     => 'CZK',
        'subtotal'     => 16_529,
        'tax_amount'   => 3_471,
        'total'        => 20_000,
        'vat_scenario' => 'cz_b2c',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    DB::table('order_items')->insert([
        'order_id'        => $orderId,
        'pricing_plan_id' => $plan->id,
        'description'     => 'Webhosting',
        'quantity'        => 1,
        'currency'        => 'CZK',
        'unit_price'      => 20_000,
        'vat_rate'        => '21.00',
        'total'           => 20_000,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.metrics.index'))
        ->assertOk();

    $revenueByType = $response->viewData('revenueByType');

    // 20_000 minor = 200.00 CZK
    expect($revenueByType['Webhosting'])->toBe(200.0);
});

it('revenueByType excludes non-active orders', function (): void {
    $admin = adminUser();

    $vpsProduct = Product::factory()->create(['type' => ProductType::Vps]);
    $plan       = PricingPlan::factory()->create(['product_id' => $vpsProduct->id, 'price_czk' => 50_000]);
    $customer   = customerUser()->customer;

    // Insert PENDING order — should NOT count
    $orderId = DB::table('orders')->insertGetId([
        'uuid'         => \Illuminate\Support\Str::uuid()->toString(),
        'customer_id'  => $customer->id,
        'status'       => OrderStatus::Pending->value,
        'currency'     => 'CZK',
        'subtotal'     => 41_322,
        'tax_amount'   => 8_678,
        'total'        => 50_000,
        'vat_scenario' => 'cz_b2c',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    DB::table('order_items')->insert([
        'order_id'        => $orderId,
        'pricing_plan_id' => $plan->id,
        'description'     => 'VPS',
        'quantity'        => 1,
        'currency'        => 'CZK',
        'unit_price'      => 50_000,
        'vat_rate'        => '21.00',
        'total'           => 50_000,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.metrics.index'))
        ->assertOk();

    $revenueByType = $response->viewData('revenueByType');

    expect($revenueByType['Cloud / VPS'])->toBe(0.0);
});

// ── New vs returning buyers ────────────────────────────────────────────────────

it('new buyers count customers created and paid this month', function (): void {
    $admin = adminUser();

    // New customer — created this month + has a paid invoice this month
    $newUser     = \App\Models\User::factory()->create();
    $newCustomer = Customer::factory()->for($newUser)->create([
        'created_at' => now()->startOfMonth()->addDay(),
    ]);
    metricsInvoice($newCustomer, 10_000, now()->toDateTimeString());

    $response = $this->actingAs($admin)
        ->get(route('admin.metrics.index'))
        ->assertOk();

    expect($response->viewData('newBuyersThisMonth'))->toBeGreaterThanOrEqual(1);
});

it('returning buyers count customers from prior months who paid this month', function (): void {
    $admin = adminUser();

    // Old customer — created last month, paying again this month
    $oldUser     = \App\Models\User::factory()->create();
    $oldCustomer = Customer::factory()->for($oldUser)->create([
        'created_at' => now()->subMonths(2)->toDateTimeString(),
    ]);
    metricsInvoice($oldCustomer, 15_000, now()->toDateTimeString());

    $response = $this->actingAs($admin)
        ->get(route('admin.metrics.index'))
        ->assertOk();

    expect($response->viewData('returningBuyersThisMonth'))->toBeGreaterThanOrEqual(1);
});

it('new and returning buyers split does not double-count a customer', function (): void {
    $admin = adminUser();

    // 1 new customer this month
    $u1 = \App\Models\User::factory()->create();
    $c1 = Customer::factory()->for($u1)->create(['created_at' => now()->startOfMonth()->addDay()]);
    metricsInvoice($c1, 10_000, now()->toDateTimeString());

    // 1 returning customer from last month
    $u2 = \App\Models\User::factory()->create();
    $c2 = Customer::factory()->for($u2)->create(['created_at' => now()->subMonths(2)->toDateTimeString()]);
    metricsInvoice($c2, 10_000, now()->toDateTimeString());

    $response = $this->actingAs($admin)
        ->get(route('admin.metrics.index'))
        ->assertOk();

    $newCount       = $response->viewData('newBuyersThisMonth');
    $returningCount = $response->viewData('returningBuyersThisMonth');

    // Total paid customers = newBuyers + returningBuyers
    $paidThisMonth = DB::table('invoices')
        ->where('status', InvoiceStatus::Paid->value)
        ->whereYear('paid_at', now()->year)
        ->whereMonth('paid_at', now()->month)
        ->distinct()
        ->count('customer_id');

    expect($newCount + $returningCount)->toBe($paidThisMonth);
});

// ── Top products by active service count ─────────────────────────────────────

it('topProducts is empty when no active services exist', function (): void {
    $admin = adminUser();

    $response = $this->actingAs($admin)
        ->get(route('admin.metrics.index'))
        ->assertOk();

    expect($response->viewData('topProducts')->count())->toBe(0);
});

it('topProducts shows active service counts per product type', function (): void {
    $admin = adminUser();

    $webhostingProduct = Product::factory()->create(['type' => ProductType::Webhosting]);
    $vpsProduct        = Product::factory()->create(['type' => ProductType::Vps]);

    // 3 active webhosting, 1 active VPS
    Service::factory()->count(3)->create([
        'product_id' => $webhostingProduct->id,
        'status'     => ServiceStatus::Active,
    ]);
    Service::factory()->create([
        'product_id' => $vpsProduct->id,
        'status'     => ServiceStatus::Active,
    ]);
    // Terminated service — should NOT count
    Service::factory()->create([
        'product_id' => $webhostingProduct->id,
        'status'     => ServiceStatus::Terminated,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.metrics.index'))
        ->assertOk();

    $top = $response->viewData('topProducts');

    expect($top->first()['label'])->toBe(ProductType::Webhosting->label())
        ->and($top->first()['count'])->toBe(3);
});

it('topProducts is limited to 5 entries', function (): void {
    $admin = adminUser();

    // Create 6 different product types by reusing types with different products
    $types = ProductType::cases();
    foreach ($types as $i => $type) {
        $prod = Product::factory()->create(['type' => $type]);
        Service::factory()->count($i + 1)->create([
            'product_id' => $prod->id,
            'status'     => ServiceStatus::Active,
        ]);
    }

    $response = $this->actingAs($admin)
        ->get(route('admin.metrics.index'))
        ->assertOk();

    expect($response->viewData('topProducts')->count())->toBeLessThanOrEqual(5);
});
