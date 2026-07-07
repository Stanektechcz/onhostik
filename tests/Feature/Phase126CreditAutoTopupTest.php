<?php

declare(strict_types=1);

use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Customer\Models\Customer;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── Settings page ─────────────────────────────────────────────────────────────

it('customer can view auto-topup settings page', function (): void {
    $user = customerUser();

    $this->actingAs($user)
         ->get(route('panel.billing.auto-topup.show'))
         ->assertOk()
         ->assertSee('Automatické dobití kreditu');
});

it('guest is redirected from auto-topup settings page', function (): void {
    $this->get(route('panel.billing.auto-topup.show'))
         ->assertRedirect(route('login'));
});

// ── Update settings ───────────────────────────────────────────────────────────

it('customer can enable auto-topup and save settings', function (): void {
    $user = customerUser();

    $this->actingAs($user)
         ->put(route('panel.billing.auto-topup.update'), [
             'enabled'          => '1',
             'threshold_amount' => 500,
             'topup_amount'     => 1000,
         ])
         ->assertRedirect();

    $config = $user->customer->fresh()->credit_auto_topup;

    expect($config['enabled'])->toBeTrue()
         ->and($config['threshold_minor'])->toBe(50000)
         ->and($config['topup_minor'])->toBe(100000);
});

it('customer can disable auto-topup', function (): void {
    $user = customerUser();
    $user->customer->update(['credit_auto_topup' => ['enabled' => true, 'threshold_minor' => 50000, 'topup_minor' => 100000]]);

    $this->actingAs($user)
         ->put(route('panel.billing.auto-topup.update'), [
             'threshold_amount' => 500,
             'topup_amount'     => 1000,
         ])
         ->assertRedirect();

    $config = $user->customer->fresh()->credit_auto_topup;
    expect($config['enabled'])->toBeFalse();
});

// ── Command ───────────────────────────────────────────────────────────────────

it('process-auto-topups command issues invoice when balance is below threshold', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $customer->update([
        'credit_auto_topup' => [
            'enabled'         => true,
            'threshold_minor' => 50000,   // 500 CZK threshold
            'topup_minor'     => 100000,  // 1000 CZK topup
        ],
    ]);

    // Balance is 0 — below threshold
    Artisan::call('billing:process-auto-topups');

    expect($customer->invoices()->where('purpose', 'credit_topup')->count())->toBe(1);
});

it('process-auto-topups command skips customer with sufficient balance', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $customer->update([
        'credit_auto_topup' => [
            'enabled'         => true,
            'threshold_minor' => 50000,
            'topup_minor'     => 100000,
        ],
    ]);

    // Deposit 1000 CZK so balance is above threshold
    app(CreditLedger::class)->deposit($customer, \Brick\Money\Money::ofMinor(100000, 'CZK'), 'Test deposit');

    Artisan::call('billing:process-auto-topups');

    expect($customer->invoices()->where('purpose', 'credit_topup')->count())->toBe(0);
});

it('process-auto-topups command skips disabled auto-topup', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $customer->update([
        'credit_auto_topup' => [
            'enabled'         => false,
            'threshold_minor' => 50000,
            'topup_minor'     => 100000,
        ],
    ]);

    Artisan::call('billing:process-auto-topups');

    expect($customer->invoices()->where('purpose', 'credit_topup')->count())->toBe(0);
});

it('process-auto-topups command does not create duplicate invoice', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $customer->update([
        'credit_auto_topup' => [
            'enabled'         => true,
            'threshold_minor' => 50000,
            'topup_minor'     => 100000,
        ],
    ]);

    // Run the command twice
    Artisan::call('billing:process-auto-topups');
    Artisan::call('billing:process-auto-topups');

    // Should not have created a second invoice
    expect($customer->invoices()->where('purpose', 'credit_topup')->count())->toBe(1);
});
