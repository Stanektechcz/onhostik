<?php

declare(strict_types=1);

use Database\Seeders\InfrastructureSeeder;
use Illuminate\Support\Facades\Artisan;
use Onhost\Domain\Provisioning\Models\ProviderInstance;

/**
 * F12b: config/onhost.php gives WEDOS a default endpoint, so the infrastructure seeder created the `wedos-main` registrar
 * connection on every installation — and created it `active` with no WAPI login or password anywhere. A registrar that
 * cannot authenticate looked usable (registrar selection, the doctor's "registrar available"). Without credentials it is
 * seeded disabled, and the doctor names what is missing.
 */
function f12bWedosEnv(?array $values): void
{
    foreach (['WEDOS_MAIN_LOGIN', 'WEDOS_MAIN_WAPI_PASSWORD'] as $key) {
        unset($_ENV[$key]);
        putenv($key);
    }
    foreach ((array) $values as $key => $value) {
        $_ENV[$key] = $value;
    }
}

afterEach(fn () => f12bWedosEnv(null));

it('seeds the WEDOS registrar connection disabled when no WAPI credentials exist, and the doctor says why', function () {
    f12bWedosEnv(null);

    $this->seed(InfrastructureSeeder::class);

    $wedos = ProviderInstance::query()->where('key', 'wedos-main')->firstOrFail();
    expect($wedos->state)->toBe('disabled');

    expect(Artisan::call('onhost:doctor', ['--json' => true]))->toBe(0);
    $row = collect(json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'])->firstWhere('check', 'wedos-main (wedos, disabled)');
    expect($row)->not->toBeNull()->and($row['status'])->not->toBe('OK')->and($row['detail'])->toContain('missing credentials: login, wapi_password');
});

it('seeds the WEDOS registrar connection active once its WAPI credentials exist', function () {
    f12bWedosEnv(['WEDOS_MAIN_LOGIN' => 'onhost@example.test', 'WEDOS_MAIN_WAPI_PASSWORD' => 'test-only']);

    $this->seed(InfrastructureSeeder::class);

    expect(ProviderInstance::query()->where('key', 'wedos-main')->value('state'))->toBe('active');
});

it('takes a seeded connection out of use when it is re-seeded after its credentials are gone', function () {
    f12bWedosEnv(['WEDOS_MAIN_LOGIN' => 'onhost@example.test', 'WEDOS_MAIN_WAPI_PASSWORD' => 'test-only']);
    $this->seed(InfrastructureSeeder::class);
    f12bWedosEnv(['WEDOS_MAIN_LOGIN' => 'onhost@example.test']); // the password is missing

    $this->seed(InfrastructureSeeder::class);

    expect(ProviderInstance::query()->where('key', 'wedos-main')->value('state'))->toBe('disabled');
});
