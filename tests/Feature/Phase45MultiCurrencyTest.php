<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\ExchangeRate;
use App\Domains\Billing\Services\ExchangeRateService;
use App\Domains\Shared\Enums\Currency;
use Brick\Money\Money;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── ExchangeRateService ───────────────────────────────────────────────────────

it('ExchangeRateService returns null when no rate exists', function (): void {
    Cache::flush();
    expect(app(ExchangeRateService::class)->getLatestRate(Currency::EUR))->toBeNull();
});

it('ExchangeRateService returns 1.0 for CZK', function (): void {
    expect(app(ExchangeRateService::class)->getLatestRate(Currency::CZK))->toBe(1.0);
});

it('ExchangeRateService setRate persists and caches rate', function (): void {
    Cache::flush();
    $service = app(ExchangeRateService::class);

    $service->setRate(Currency::EUR, 25.30);

    $rate = $service->getLatestRate(Currency::EUR);
    expect($rate)->toBeFloat();
    expect((int) round($rate * 100))->toBe(2530); // 25.30 * 100 = 2530

    $record = ExchangeRate::where('currency', 'EUR')->first();
    expect($record)->not()->toBeNull();
    expect((int) round($record->rate * 100))->toBe(2530);
});

it('ExchangeRateService setRate is idempotent for same day', function (): void {
    $service = app(ExchangeRateService::class);

    $service->setRate(Currency::USD, 23.10);
    $service->setRate(Currency::USD, 23.50); // update for same day

    expect(ExchangeRate::where('currency', 'USD')->count())->toBe(1);
    expect((int) round($service->getLatestRate(Currency::USD) * 100))->toBe(2350);
});

it('ExchangeRateService setRate throws for CZK', function (): void {
    expect(fn () => app(ExchangeRateService::class)->setRate(Currency::CZK, 1.0))
        ->toThrow(InvalidArgumentException::class);
});

it('ExchangeRateService convertToCzk converts EUR amount', function (): void {
    $service = app(ExchangeRateService::class);
    $service->setRate(Currency::EUR, 25.00);

    // 2 EUR = 200 minor EUR units → 200 * 25 = 5000 minor CZK = 50 CZK
    $eur  = Money::of(2, 'EUR'); // 2 EUR = 200 minor units
    $czk  = $service->convertToCzk($eur);

    expect($czk->getCurrency()->getCurrencyCode())->toBe('CZK');
    // 2.00 EUR * 25 = 50.00 CZK = 5000 minor
    expect($czk->getMinorAmount()->toInt())->toBe(5000);
});

it('ExchangeRateService convertToCzk is identity for CZK', function (): void {
    $service  = app(ExchangeRateService::class);
    $original = Money::of(100, 'CZK');
    $result   = $service->convertToCzk($original);

    expect($result->getMinorAmount()->toInt())->toBe($original->getMinorAmount()->toInt());
});

it('ExchangeRateService convertToCzk throws when rate is missing', function (): void {
    Cache::flush();
    $service = app(ExchangeRateService::class);

    expect(fn () => $service->convertToCzk(Money::of(10, 'EUR')))
        ->toThrow(RuntimeException::class);
});

it('ExchangeRateService allLatestRates returns all configured currencies', function (): void {
    $service = app(ExchangeRateService::class);
    $service->setRate(Currency::EUR, 25.0);
    $service->setRate(Currency::USD, 23.0);

    $rates = $service->allLatestRates();
    expect($rates)->toHaveKey('EUR');
    expect($rates)->toHaveKey('USD');
    expect((int) round($rates['EUR']))->toBe(25);
});

// ── Admin exchange rate page ──────────────────────────────────────────────────

it('admin can view exchange rates page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.exchange-rates.index'))
        ->assertOk()
        ->assertViewIs('admin.exchange-rates.index');
});

it('customer cannot access exchange rates page', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.exchange-rates.index'))
        ->assertForbidden();
});

it('admin can update EUR exchange rate', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->put(route('admin.exchange-rates.update', 'EUR'), ['rate' => '25.3500'])
        ->assertRedirect();

    $rate = ExchangeRate::where('currency', 'EUR')->first();
    expect($rate)->not()->toBeNull();
    expect((int) round($rate->rate * 100))->toBe(2535);
});

it('admin exchange rate update validates min rate', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->put(route('admin.exchange-rates.update', 'EUR'), ['rate' => '0'])
        ->assertSessionHasErrors('rate');
});

it('admin exchange rate update returns 404 for unknown currency', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->put(route('admin.exchange-rates.update', 'XYZ'), ['rate' => '5.00'])
        ->assertNotFound();
});

it('admin exchange rate update returns 404 for CZK (base currency)', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->put(route('admin.exchange-rates.update', 'CZK'), ['rate' => '1.00'])
        ->assertNotFound();
});

// ── DAC7 export ───────────────────────────────────────────────────────────────

it('admin can download DAC7 CSV export', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    // Create a paid invoice for this year
    \App\Domains\Billing\Models\Invoice::factory()->create([
        'customer_id'           => $customer->customer->id,
        'status'                => InvoiceStatus::Paid,
        'paid_at'               => now(),
        'purpose'               => 'order',
        'currency'              => 'CZK',
        'total'                 => \Brick\Money\Money::of(999, 'CZK'),
        'subtotal'              => \Brick\Money\Money::of(999, 'CZK'),
        'tax_amount'            => \Brick\Money\Money::of(0, 'CZK'),
        'snapshot_country_code' => 'CZ',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.dac7.export', ['year' => now()->year]))
        ->assertOk();

    $content = $response->streamedContent();
    expect($content)->toContain('customer_id');
    expect($content)->toContain((string) $customer->customer->id);
});

it('customer cannot download DAC7 export', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.dac7.export', ['year' => now()->year]))
        ->assertForbidden();
});

it('DAC7 export validates year parameter', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.dac7.export', ['year' => 2010]))
        ->assertSessionHasErrors('year');
});
