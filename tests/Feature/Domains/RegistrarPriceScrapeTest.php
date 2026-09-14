<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Domains\Models\RegistrarTldCost;
use Onhost\Domain\Domains\RegistrarPriceScraper;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Providers\Subreg\SubregPublicPriceList;
use Onhost\Providers\Wedos\WedosPublicPriceList;

function priceFixture(string $name): string
{
    return (string) file_get_contents(base_path('tests/fixtures/prices/'.$name.'.html'));
}

it('parses the public price lists of both registrars (net CZK, promo flag, minimum years)', function () {
    $wedos = (new WedosPublicPriceList)->parse(priceFixture('wedos'));
    expect($wedos['cz'])->toMatchArray(['currency' => 'CZK', 'register' => '160.00', 'renew' => '160.00', 'promo' => false])
        ->and($wedos['fun'])->toMatchArray(['register' => '14.00', 'renew' => '1002.00', 'promo' => true])
        ->and($wedos['io']['register'])->toBe('1069.00')->and($wedos)->not->toHaveKey('registrace');

    expect($wedos['eu']['promo'])->toBeTrue()->and(count($wedos))->toBe(5);
    $subreg = (new SubregPublicPriceList)->parse(priceFixture('subreg'));
    expect($subreg['org'])->toMatchArray(['currency' => 'CZK', 'register' => '177.00', 'renew' => '354.00', 'transfer' => '354.00', 'promo' => true, 'min_years' => 1])
        ->and($subreg['cz'])->toMatchArray(['register' => '250.00', 'renew' => '329.00', 'promo' => false])->and($subreg['io']['transfer'])->toBe('1416.00')->and(count($subreg))->toBe(5);
    expect((new WedosPublicPriceList)->parse('<html><body>no table</body></html>'))->toBe([]);
});

it('imports scraped prices into the price book without overwriting manual or API rows, and exposes it to staff', function () {
    $this->seed([CatalogSeeder::class]);
    $_ENV['WEDOS_MAIN_LOGIN'] = 'onhost@onhost.cz';
    $_ENV['WEDOS_MAIN_WAPI_PASSWORD'] = 'wapi-secret';
    ProviderInstance::query()->firstOrCreate(['key' => 'wedos-main'], ['provider' => 'wedos', 'name' => 'WEDOS', 'base_url' => 'https://api.wedos.com', 'secret_ref' => 'env://WEDOS_MAIN', 'state' => 'active', 'capabilities' => ['registrar' => true], 'adapter_version' => '1.0.0']);
    RegistrarTldCost::query()->updateOrCreate(['registrar_provider' => 'wedos', 'tld' => 'com'], ['currency' => 'CZK', 'register_minor' => 25000, 'renew_minor' => 25000, 'transfer_minor' => 25000, 'source' => 'manual', 'fetched_at' => now()]);
    $seeded = RegistrarTldCost::query()->where('registrar_provider', 'wedos')->where('tld', 'cz')->firstOrFail();
    expect($seeded->source)->toBe('seed')->and($seeded->register_minor)->toBe(14500);

    $report = app(RegistrarPriceScraper::class)->scrape(null, ['wedos' => priceFixture('wedos'), 'subreg' => priceFixture('subreg')]);
    expect($report['registrars']['wedos'])->toMatchArray(['tlds' => 5, 'imported' => 4, 'skipped' => 1, 'error' => null])->and($report['registrars']['subreg']['imported'])->toBe(5);
    $cz = RegistrarTldCost::query()->where('registrar_provider', 'wedos')->where('tld', 'cz')->firstOrFail();
    expect($cz->source)->toBe('scrape')->and($cz->register_minor)->toBe(16000)->and($cz->meta['retail'])->toBeTrue()->and($cz->provider_instance_id)->toBe(ProviderInstance::query()->where('key', 'wedos-main')->value('id'));
    expect(RegistrarTldCost::query()->where('registrar_provider', 'wedos')->where('tld', 'com')->value('register_minor'))->toBe(25000); // manual row kept
    expect(RegistrarTldCost::query()->where('registrar_provider', 'subreg')->where('tld', 'cz')->value('register_minor'))->toBe(25000)
        ->and(RegistrarTldCost::query()->where('registrar_provider', 'subreg')->where('tld', 'org')->first()->meta['promo'])->toBeTrue();

    // the matrix shows both retail-derived costs per TLD (.cz: 160 Kč vs 250 Kč) and where the selling price falls below cost
    $staff = $this->staff('platform_owner');
    $this->actingAs($staff, 'sanctum');
    $tlds = collect($this->getJson('/v1/staff/registrars')->assertOk()->json('data.tlds'));
    $czRow = $tlds->firstWhere('tld', 'cz');
    expect($czRow['costs']['wedos'])->toMatchArray(['register' => 16000, 'source' => 'scrape'])->and($czRow['costs']['subreg']['register'])->toBe(25000)->and($czRow['margin_czk'])->toBe(17900 - 16000);
    expect($tlds->firstWhere('tld', 'io')['margin_czk'])->toBe(129000 - 106900); // sold at 1 290 Kč, cheapest scraped cost 1 069 Kč (the price was raised above cost during the audit)

    // the command takes saved pages, the staff endpoint fetches live (faked here)
    Artisan::call('onhost:registrar:scrape-prices', ['--registrar' => 'wedos', '--file' => ['wedos='.base_path('tests/fixtures/prices/wedos.html')]]);
    expect(Artisan::output())->toContain('wedos: 5 TLDs on the page, 4 imported, 1 kept');
    Http::fake(['vedos.cz/*' => Http::response(priceFixture('wedos')), 'subreg.cz/*' => Http::response('<html></html>', 503)]);
    $live = $this->postJson('/v1/staff/registrars/costs/scrape', [], ['Idempotency-Key' => 'sc-1'])->assertOk()->json();
    expect($live['registrars']['wedos']['imported'])->toBe(4)->and($live['registrars']['subreg']['error'])->toContain('HTTP 503');
    $this->postJson('/v1/staff/registrars/costs/scrape', ['registrar' => 'nope'], ['Idempotency-Key' => 'sc-2'])->assertUnprocessable();
});
