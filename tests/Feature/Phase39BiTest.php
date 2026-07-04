<?php

declare(strict_types=1);

use App\Console\Commands\ComputeCustomerInsightsCommand;
use App\Domains\Bi\Actions\ChurnRiskScorer;
use App\Domains\Bi\Enums\CustomerSegment;
use App\Domains\Bi\Services\RevenueForecaster;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Billing\Enums\VatScenario;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Shared\Enums\Currency;
use Brick\Money\Money;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── CustomerSegment enum ──────────────────────────────────────────────────────

it('CustomerSegment enum cases have labels and colors', function (): void {
    foreach (CustomerSegment::cases() as $seg) {
        expect($seg->label())->toBeString()->not->toBeEmpty();
        expect($seg->color())->toBeString()->not->toBeEmpty();
    }
});

// ── ChurnRiskScorer ───────────────────────────────────────────────────────────

it('scorer returns 0 for a healthy customer with no problems', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $score = app(ChurnRiskScorer::class)->score($customer);
    expect($score)->toBe(0);
});

it('scorer increments score for overdue invoice', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    Invoice::factory()->overdue()->create(['customer_id' => $customer->id]);

    $score = app(ChurnRiskScorer::class)->score($customer->fresh());
    expect($score)->toBeGreaterThanOrEqual(20);
});

it('scorer increments score for suspended service', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $productId = \App\Domains\Products\Models\Product::value('id');
    Service::create([
        'customer_id'         => $customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Suspended,
        'label'               => 'suspended-svc',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $score = app(ChurnRiskScorer::class)->score($customer->fresh());
    expect($score)->toBeGreaterThanOrEqual(20);
});

it('scorer caps at 100', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    Invoice::factory()->overdue()->create(['customer_id' => $customer->id]);
    Invoice::factory()->overdue()->create(['customer_id' => $customer->id]);
    Invoice::factory()->overdue()->create(['customer_id' => $customer->id]);
    Invoice::factory()->overdue()->create(['customer_id' => $customer->id]);

    $productId = \App\Domains\Products\Models\Product::value('id');
    Service::create([
        'customer_id'         => $customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Suspended,
        'label'               => 'cap-test',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $score = app(ChurnRiskScorer::class)->score($customer->fresh());
    expect($score)->toBeLessThanOrEqual(100);
});

it('segment returns Churned for score >= 70', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    $scorer   = app(ChurnRiskScorer::class);

    $seg = $scorer->segment($customer, 70);
    expect($seg)->toBe(CustomerSegment::Churned);
});

it('segment returns AtRisk for score >= 30', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $seg = app(ChurnRiskScorer::class)->segment($customer, 40);
    expect($seg)->toBe(CustomerSegment::AtRisk);
});

it('segment returns Healthy for score < 30 with low spend', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $seg = app(ChurnRiskScorer::class)->segment($customer, 10);
    expect($seg)->toBe(CustomerSegment::Healthy);
});

// ── RevenueForecaster ────────────────────────────────────────────────────────

it('forecaster returns expected structure', function (): void {
    $data = app(RevenueForecaster::class)->forecast();

    expect($data)->toHaveKeys(['forecast', 'trend', 'months', 'confidence']);
    expect($data['forecast'])->toBeFloat();
    expect($data['trend'])->toBeIn(['growing', 'declining', 'stable']);
    expect($data['confidence'])->toBeIn(['high', 'medium', 'low']);
    expect($data['months'])->toBeArray()->toHaveCount(3);
});

it('forecaster confidence is low when no paid invoices exist', function (): void {
    $data = app(RevenueForecaster::class)->forecast();
    expect($data['confidence'])->toBe('low');
    expect($data['forecast'])->toBe(0.0);
});

it('forecaster returns non-zero forecast when paid invoices exist', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $order = Order::create([
        'customer_id'  => $customer->id,
        'status'       => OrderStatus::Active,
        'currency'     => Currency::CZK,
        'subtotal'     => Money::of(826, 'CZK'),
        'tax_amount'   => Money::of(173, 'CZK'),
        'total'        => Money::of(999, 'CZK'),
        'vat_scenario' => VatScenario::CzechB2C,
    ]);

    Invoice::factory()->create([
        'customer_id' => $customer->id,
        'order_id'    => $order->id,
        'status'      => InvoiceStatus::Paid,
        'paid_at'     => now()->subMonth(),
    ]);

    $data = app(RevenueForecaster::class)->forecast();
    expect($data['forecast'])->toBeGreaterThan(0);
});

// ── ComputeCustomerInsightsCommand ────────────────────────────────────────────

it('compute-insights command runs successfully', function (): void {
    $user = customerUser();

    $this->artisan(ComputeCustomerInsightsCommand::class)->assertExitCode(0);

    $customer = $user->customer->fresh();
    expect($customer->churn_risk_score)->not->toBeNull();
    expect($customer->segment)->not->toBeNull();
    expect($customer->insights_updated_at)->not->toBeNull();
});

it('compute-insights assigns correct segment for overdue customer', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    Invoice::factory()->overdue()->create(['customer_id' => $customer->id]);
    Invoice::factory()->overdue()->create(['customer_id' => $customer->id]);
    Invoice::factory()->overdue()->create(['customer_id' => $customer->id]);

    $this->artisan(ComputeCustomerInsightsCommand::class)->assertExitCode(0);

    $customer->refresh();
    expect($customer->churn_risk_score)->toBeGreaterThanOrEqual(30);
    expect($customer->segment)->toBeIn([CustomerSegment::AtRisk->value, CustomerSegment::Churned->value]);
});

// ── Admin BI Dashboard ────────────────────────────────────────────────────────

it('admin BI dashboard returns 200', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.bi.index'))
        ->assertOk()
        ->assertViewIs('admin.bi')
        ->assertViewHas('forecast')
        ->assertViewHas('segmentCounts')
        ->assertViewHas('atRiskCustomers');
});

it('customer cannot access BI dashboard', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.bi.index'))
        ->assertForbidden();
});
