<?php

declare(strict_types=1);

use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Marketplace\Models\AppInstallation;
use App\Domains\Marketplace\Models\MarketplaceApp;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Brick\Money\Money;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

function activeServiceFor(\App\Models\User $user): Service
{
    return Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Active,
    ]);
}

function paidApp(int $priceHalere = 19900): MarketplaceApp
{
    return MarketplaceApp::create([
        'slug'         => 'premium-app-' . uniqid(),
        'name'         => 'Premium App',
        'category'     => 'other',
        'icon'         => 'package',
        'price_halere' => $priceHalere,
        'is_active'    => true,
        // Installable: without a recipe the marketplace now refuses up front.
        'install_url'  => 'https://example.com/premium-app.tar.gz',
    ]);
}

function balanceMinor(\App\Domains\Customer\Models\Customer $customer): int
{
    return app(CreditLedger::class)->getBalance($customer->fresh())->getMinorAmount()->toInt();
}

it('a free app installs without charging credit', function (): void {
    $user    = customerUser();
    $service = activeServiceFor($user);
    $currency = $user->customer->preferred_currency->value;
    app(CreditLedger::class)->deposit($user->customer, Money::of(100, $currency), 'seed');

    $free = MarketplaceApp::create(['slug' => 'free-app', 'name' => 'Free', 'category' => 'other', 'icon' => 'package', 'price_halere' => 0, 'is_active' => true, 'install_url' => 'https://example.com/free-app.tar.gz']);

    $this->actingAs($user)->post(route('panel.marketplace.install', [$service, $free]))->assertRedirect()->assertSessionHasNoErrors();

    expect(balanceMinor($user->customer))->toBe(10000); // unchanged
});

it('a paid app charges credit and records what was paid', function (): void {
    $user     = customerUser();
    $service  = activeServiceFor($user);
    $currency = $user->customer->preferred_currency->value;
    app(CreditLedger::class)->deposit($user->customer, Money::of(500, $currency), 'seed');

    $app = paidApp(19900); // 199.00

    $this->actingAs($user)->post(route('panel.marketplace.install', [$service, $app]))
        ->assertRedirect()->assertSessionHasNoErrors();

    $installation = AppInstallation::where('service_id', $service->id)->where('marketplace_app_id', $app->id)->first();

    // With provisioning in mock/dry-run mode nothing reaches the node, so the
    // installation stays pending — but the charge is real either way.
    expect($installation?->status)->toBe('pending')
        ->and($installation?->price_halere_paid)->toBe(19900)
        ->and(balanceMinor($user->customer))->toBe(50000 - 19900);
});

it('a paid app is refused when credit is insufficient — no install, no charge', function (): void {
    $user     = customerUser();
    $service  = activeServiceFor($user);
    $currency = $user->customer->preferred_currency->value;
    app(CreditLedger::class)->deposit($user->customer, Money::of(50, $currency), 'seed');

    $app = paidApp(19900); // 199.00 > 50.00 balance

    $this->actingAs($user)->post(route('panel.marketplace.install', [$service, $app]))
        ->assertSessionHasErrors('install');

    expect(AppInstallation::where('marketplace_app_id', $app->id)->where('status', 'installed')->exists())->toBeFalse()
        ->and(balanceMinor($user->customer))->toBe(5000); // untouched
});

it('admin can set a price when adding an app', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.marketplace.store'), [
            'slug'      => 'paid-theme',
            'name'      => 'Paid Theme',
            'category'  => 'cms',
            'price_czk' => '149.50',
            'is_active' => 1,
        ])->assertRedirect();

    expect(MarketplaceApp::where('slug', 'paid-theme')->value('price_halere'))->toBe(14950);
});
