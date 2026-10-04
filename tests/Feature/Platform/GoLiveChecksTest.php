<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Artisan;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Platform\GoLiveChecks;
use Onhost\Domain\Tax\Models\ExchangeRate;

function goLiveRow(string $check, ?GoLiveChecks $checks = null): array
{
    $rows = collect(($checks ?? new GoLiveChecks)->rows());

    return (array) $rows->firstWhere('check', $check);
}

function goLiveDoctor(string $check, bool $production = false): array
{
    Artisan::call('onhost:doctor', ['--json' => true]);
    if ($production) {
        app()->instance('env', 'production');
        Artisan::call('onhost:doctor', ['--json' => true]);
    }
    $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
    app()->instance('env', 'testing');

    return (array) collect($report['checks'])->firstWhere('check', $check);
}

afterEach(fn () => TrustProxies::flushState());

it('counts staff without a confirmed authenticator and never prints a secret', function () {
    $this->seed([LegalEntitySeeder::class]);
    $row = fn () => goLiveRow('staff and demo accounts have an authenticator');
    User::query()->where('is_staff', true)->update(['totp_secret' => 'enrolled-fixture', 'totp_confirmed_at' => now()]);
    expect($row()['ok'])->toBeTrue()->and($row()['remedy'])->toBe('');

    $staff = User::query()->where('is_staff', true)->first() ?? User::factory()->create(['is_staff' => true]);
    $staff->forceFill(['is_staff' => true, 'totp_secret' => null, 'totp_confirmed_at' => null, 'email' => 'someone@demo.onhost.cz'])->save();
    $bad = $row();
    expect($bad['ok'])->toBeFalse()->and($bad['detail'])->toContain('without a confirmed authenticator')->toContain('1 demo')
        ->and($bad['remedy'])->toContain('onhost:staff:totp')->and(json_encode($bad))->not->toContain('enrolled-fixture');
});

it('names the PHP binary and what to pass to the deploy when it is too old', function () {
    $ok = goLiveRow('PHP binary is the one the deploy and the workers must use', new GoLiveChecks(phpBinary: '/www/server/php/83/bin/php', phpVersion: '8.3.12'));
    expect($ok['ok'])->toBeTrue()->and($ok['detail'])->toContain('/www/server/php/83/bin/php')->and($ok['remedy'])->toBe('');

    $old = goLiveRow('PHP binary is the one the deploy and the workers must use', new GoLiveChecks(phpBinary: '/usr/bin/php', phpVersion: '8.1.2'));
    expect($old['ok'])->toBeFalse()->and($old['remedy'])->toContain('PHP=')->toContain('S7');
});

it('reports the catalogue revision of the deliverable web plans as pending, then applied', function () {
    $this->seed([LegalEntitySeeder::class, CatalogSeeder::class]);
    $name = 'catalogue revision 2026-10-deliverable-web-plans applied';
    $pending = goLiveRow($name);
    expect($pending['ok'])->toBeFalse()->and($pending['remedy'])->toContain('onhost:catalog:revise 2026-10-deliverable-web-plans')->toContain('--apply');

    Artisan::call('onhost:catalog:revise', ['revision' => '2026-10-deliverable-web-plans', '--apply' => true, '--yes' => true]);
    expect(goLiveRow($name))->toMatchArray(['ok' => true, 'remedy' => '']);
});

it('judges the age of the national bank list', function () {
    $name = 'exchange rates are fresh';
    $none = goLiveRow($name);
    expect($none['ok'])->toBeFalse()->and($none['remedy'])->toContain('onhost:fx:sync');

    $make = fn (string $day) => ExchangeRate::query()->create(['source' => 'cnb', 'currency' => 'EUR', 'valid_on' => $day, 'amount' => 1, 'rate_micro' => 25_000_000, 'fetched_at' => now()]);
    $make(now()->subDays(30)->toDateString());
    $stale = goLiveRow($name);
    expect($stale['ok'])->toBeFalse()->and($stale['detail'])->toContain('30 day(s) old')->and($stale['remedy'])->toContain('onhost:fx:sync');

    $make(now()->toDateString());
    expect(goLiveRow($name))->toMatchArray(['ok' => true, 'remedy' => '']);
});

it('refuses a wildcard proxy, warns on an empty list in production and accepts exact addresses', function () {
    $name = 'trusted proxies are exact addresses';
    TrustProxies::at('*');
    $wild = goLiveRow($name, new GoLiveChecks(production: false));
    expect($wild['ok'])->toBeFalse()->and($wild['blocking'])->toBeTrue()->and($wild['remedy'])->toContain('exact address');

    TrustProxies::flushState();
    putenv('TRUSTED_PROXIES'); // unset: the environment must not hide the empty case
    $emptyProd = goLiveRow($name, new GoLiveChecks(production: true));
    $emptyDev = goLiveRow($name, new GoLiveChecks(production: false));
    expect($emptyProd['ok'])->toBeFalse()->and($emptyProd['blocking'])->toBeFalse()->and($emptyProd['remedy'])->toContain('TRUSTED_PROXIES')
        ->and($emptyDev['ok'])->toBeTrue();

    TrustProxies::at(['10.0.0.5', '10.0.0.6']);
    $exact = goLiveRow($name, new GoLiveChecks(production: true));
    expect($exact['ok'])->toBeTrue()->and($exact['detail'])->toContain('10.0.0.5')->and($exact['remedy'])->toBe('');
});

it('shows the API base URL as information only', function () {
    config(['onhost.api.base_url' => null]);
    $unset = goLiveRow('API base URL for the documentation');
    config(['onhost.api.base_url' => 'https://api.example.test/v1']);
    $set = goLiveRow('API base URL for the documentation');
    expect($unset['ok'])->toBeTrue()->and($unset['detail'])->toContain('not set')->and($set['ok'])->toBeTrue()->and($set['detail'])->toBe('https://api.example.test/v1');
});

it('warns about a process-local cache store in production only', function () {
    $name = 'cache store is shared (rate limits)';
    config(['cache.default' => 'file', 'cache.stores.file.driver' => 'file']);
    $prod = goLiveRow($name, new GoLiveChecks(production: true));
    $dev = goLiveRow($name, new GoLiveChecks(production: false));
    expect($prod['ok'])->toBeFalse()->and($prod['remedy'])->toContain('CACHE_STORE=redis')->and($dev['ok'])->toBeTrue();

    config(['cache.default' => 'redis', 'cache.stores.redis.driver' => 'redis']);
    expect(goLiveRow($name, new GoLiveChecks(production: true)))->toMatchArray(['ok' => true, 'remedy' => '']);
});

it('walks the operator through the R9 switch order while tokens may lack an organisation', function () {
    $name = 'API tokens must name an organisation (R9)';
    config(['onhost.token_organization_required' => false]);
    $off = goLiveRow($name);
    expect($off['ok'])->toBeFalse()->and($off['remedy'])->toContain('operator:tokens:unbound --dry-run')->toContain('ONHOST_TOKEN_ORGANIZATION_REQUIRED=true');

    config(['onhost.token_organization_required' => true]);
    expect(goLiveRow($name))->toMatchArray(['ok' => true, 'remedy' => '']);
});

it('puts every new row into the doctor with a status and the remedy, and fails only the blocking ones in production', function () {
    $this->seed([LegalEntitySeeder::class]);
    config(['onhost.token_organization_required' => false, 'cache.default' => 'array']);
    $report = goLiveDoctor('API tokens must name an organisation (R9)');
    expect($report['status'])->toBe('WARN')->and($report['remedy'])->toContain('operator:tokens:unbound');

    foreach (['staff and demo accounts have an authenticator', 'PHP binary is the one the deploy and the workers must use', 'catalogue revision 2026-10-deliverable-web-plans applied',
        'exchange rates are fresh', 'trusted proxies are exact addresses', 'API base URL for the documentation', 'cache store is shared (rate limits)'] as $check) {
        $row = goLiveDoctor($check);
        expect($row)->toHaveKeys(['status', 'detail', 'remedy'])->and($row['status'])->toBeIn(['OK', 'WARN', 'FAIL']);
        if ($row['status'] !== 'OK') {
            expect($row['remedy'])->not->toBe('', $check);
        }
    }
    TrustProxies::at('*');
    $wildcard = goLiveDoctor('trusted proxies are exact addresses', production: true);
    expect($wildcard['status'])->toBe('FAIL')->and($wildcard['remedy'])->toContain('TRUSTED_PROXIES');
});

it('gives the existing Turnstile and capacity-basis rows a remedy when they are not OK', function () {
    $this->seed([LegalEntitySeeder::class]);
    config(['onhost.turnstile.site_key' => '', 'onhost.turnstile.secret' => '', 'onhost.turnstile.enforce_register' => true]);
    $turnstile = goLiveDoctor('Turnstile protects registration and public forms');
    expect($turnstile['status'])->toBe('WARN')->and($turnstile['remedy'])->toContain('TURNSTILE_SITE_KEY');

    config(['onhost.turnstile.site_key' => 'k', 'onhost.turnstile.secret' => 's']);
    expect(goLiveDoctor('Turnstile protects registration and public forms'))->toMatchArray(['status' => 'OK', 'remedy' => '']);

    config(['onhost.provisioning.capacity_basis.disk' => 'measured']);
    $capacity = goLiveDoctor('capacity basis as decided (disk sold, RAM and CPU measured)');
    expect($capacity['status'])->toBe('WARN')->and($capacity['remedy'])->toContain('ONHOST_CAPACITY_DISK_BASIS=sold');
});
