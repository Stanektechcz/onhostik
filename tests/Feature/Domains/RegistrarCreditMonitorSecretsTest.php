<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Onhost\Domain\Domains\Models\RegistrarCreditSnapshot;
use Onhost\Domain\Domains\RegistrarCreditMonitor;
use Onhost\Domain\Provisioning\Models\ProviderInstance;

/*
 * Staging finding 2026-10-14 (phase V, step B): `onhost:registrar:credit` failed every hour (26× in a row) because the
 * staging registrar instance `wedos-main` has no WEDOS_MAIN_* secrets. The secret store throws a RuntimeException,
 * the monitor caught only ProviderException, so one registrar without credentials stopped the sample of every other
 * registrar and failed the scheduled job (doctor: "every scheduled job ran within its budget" WARN). The poll command
 * already skips that case; the credit sample now does the same per instance.
 */
beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('samples the other registrars when one registrar has no secrets, and the scheduled command succeeds', function () {
    $_ENV['WEDOS_MAIN_LOGIN'] = 'onhost@onhost.cz';
    $_ENV['WEDOS_MAIN_WAPI_PASSWORD'] = 'wapi-secret';
    unset($_ENV['WEDOS_ABSENT_LOGIN'], $_ENV['WEDOS_ABSENT_WAPI_PASSWORD']);
    // 'wedos-absent' sorts before 'wedos-main', so it is sampled first and used to stop the loop.
    ProviderInstance::query()->create(['key' => 'wedos-absent', 'provider' => 'wedos', 'name' => 'WEDOS without secrets', 'base_url' => 'https://api.wedos.com', 'secret_ref' => 'env://WEDOS_ABSENT', 'state' => 'active', 'capabilities' => ['registrar' => true], 'adapter_version' => '1.0.0']);
    ProviderInstance::query()->create(['key' => 'wedos-main', 'provider' => 'wedos', 'name' => 'WEDOS WAPI', 'base_url' => 'https://api.wedos.com', 'secret_ref' => 'env://WEDOS_MAIN', 'state' => 'active', 'capabilities' => ['registrar' => true], 'adapter_version' => '1.0.0']);
    $state = ['registered' => true, 'nsset' => true, 'expiration' => now()->addDays(400)->toDateString(), 'credit' => '1200.00'];
    registryFake($state);
    Log::spy();

    $snapshots = app(RegistrarCreditMonitor::class)->sampleAll();

    expect($snapshots)->toHaveCount(1)
        ->and($snapshots[0]->balance_minor)->toBe(120000)
        ->and(RegistrarCreditSnapshot::query()->count())->toBe(1);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => $message === 'registrar credit sample failed'
        && $context['instance'] === 'wedos-absent' && $context['error'] === RuntimeException::class);

    $this->artisan('onhost:registrar:credit')->assertSuccessful();
});

it('fails nothing and samples nothing when the only registrar has no secrets', function () {
    unset($_ENV['WEDOS_ABSENT_LOGIN'], $_ENV['WEDOS_ABSENT_WAPI_PASSWORD']);
    ProviderInstance::query()->create(['key' => 'wedos-absent', 'provider' => 'wedos', 'name' => 'WEDOS without secrets', 'base_url' => 'https://api.wedos.com', 'secret_ref' => 'env://WEDOS_ABSENT', 'state' => 'active', 'capabilities' => ['registrar' => true], 'adapter_version' => '1.0.0']);

    expect(app(RegistrarCreditMonitor::class)->sampleAll())->toBe([]);
    $this->artisan('onhost:registrar:credit')->assertSuccessful();
    expect(RegistrarCreditSnapshot::query()->count())->toBe(0);
});
