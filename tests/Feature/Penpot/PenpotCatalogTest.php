<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Catalog\CatalogPreflight;
use Onhost\Domain\Catalog\CatalogRevisions;
use Onhost\Domain\Catalog\Commands\CatalogCommand;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Price;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\PanelNavigation;
use Onhost\Domain\Services\Penpot\PenpotHealth;
use Onhost\Platform\Errors\DomainError;
use Onhost\Providers\Penpot\PenpotCompose;

/*
 * TASK-0123 — the Penpot product is a proposal the owner applies (revision 2026-10-penpot): created as a draft with its plan and
 * zero prices, priced by staff, and never put on sale while a price is still zero or no Penpot node can run it.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    $this->seed(CatalogSeeder::class);
});

it('prepares Penpot as a proposal: a fresh install and a plain revision run do not create it', function () {
    expect(CatalogRevisions::proposals())->toContain('2026-10-penpot')->and(CatalogRevisions::ids())->not->toContain('2026-10-penpot')
        ->and(CatalogRevisions::seededProducts())->toBe(['limit-raise']);
    expect(Product::query()->where('key', 'penpot')->exists())->toBeFalse(); // CatalogSeeder ran: still nothing

    $this->artisan('onhost:catalog:revise', ['--apply' => true, '--yes' => true])->assertSuccessful();
    expect(Product::query()->where('key', 'penpot')->exists())->toBeFalse();

    $this->artisan('onhost:catalog:revise', ['revision' => '2026-10-penpot'])->assertSuccessful()->expectsOutputToContain('penpot');
    expect(Product::query()->where('key', 'penpot')->exists())->toBeFalse(); // a preview writes nothing
});

it('creates the draft product with its plan and zero prices when the owner applies it', function () {
    $this->artisan('onhost:catalog:revise', ['revision' => '2026-10-penpot', '--apply' => true, '--yes' => true])->assertSuccessful();

    $product = Product::query()->where('key', 'penpot')->firstOrFail();
    expect($product->state)->toBe('draft')->and($product->family)->toBe('penpot')->and($product->executor)->toBe('penpot')->and($product->isSellable())->toBeFalse()
        ->and(data_get($product->meta, 'admin_priced'))->toBeTrue();
    $plan = Plan::query()->where('product_id', $product->id)->where('key', 'penpot-team')->firstOrFail();
    $version = PlanVersion::query()->where('plan_id', $plan->id)->where('version', 1)->firstOrFail();
    expect($version->entitlements)->toMatchArray(['ram_mb' => 4096, 'cpus' => 2, 'storage_gb' => 20, 'backup_days' => 14]);
    $prices = Price::query()->where('plan_version_id', $version->id)->get();
    expect($prices)->toHaveCount(4)->and($prices->pluck('amount_minor')->unique()->all())->toBe([0])
        ->and($prices->map(fn (Price $p) => $p->currency.'/'.$p->period)->sort()->values()->all())->toBe(['CZK/month', 'CZK/year', 'EUR/month', 'EUR/year']);

    // a second run finds nothing to create; the product is never rewritten
    expect(app(CatalogRevisions::class)->pending('2026-10-penpot'))->toBe([]);
    expect(fn () => CatalogRevisions::createDefined('penpot'))->toThrow(DomainError::class);
});

it('refuses to put Penpot on sale while a price is zero, and the doctor says so', function () {
    $this->artisan('onhost:catalog:revise', ['revision' => '2026-10-penpot', '--apply' => true, '--yes' => true])->assertSuccessful();
    // the operator's switch (system actor, straight to the handler) and the staff pre-flight both refuse it
    $this->artisan('onhost:catalog:state', ['state' => 'active', 'products' => ['penpot']])->assertFailed()->expectsOutputToContain('price_unset');
    try {
        app(CatalogPreflight::class)->check(new CatalogCommand('pp-on-sale-1', ['op' => 'product.state', 'state' => 'active', 'products' => ['penpot']]));
        $this->fail('a zero-priced Penpot passed the pre-flight');
    } catch (DomainError $e) {
        expect($e->error)->toBe('price_unset')->and($e->status)->toBe(409)->and($e->extra['plans'])->toBe(['penpot-team']);
    }
    expect(Product::query()->where('key', 'penpot')->value('state'))->toBe('draft');
    // taking it off sale is never refused
    $this->artisan('onhost:catalog:state', ['state' => 'draft', 'products' => ['penpot']])->assertSuccessful();

    $rows = collect(app(PenpotHealth::class)->checks())->keyBy('check');
    expect($rows['Penpot has a price before it is on sale']['ok'])->toBeTrue() // a draft at zero is fine…
        ->and($rows['Penpot has a price before it is on sale']['detail'])->toContain('zero price on plan(s): penpot-team')
        ->and($rows['Penpot is sold only with a Penpot node to run it']['ok'])->toBeTrue();

    // …put on sale behind the guard's back (a hand edit), it is a blocking finding — and so is selling without a node
    Product::query()->where('key', 'penpot')->update(['state' => 'active']);
    $rows = collect(app(PenpotHealth::class)->checks())->keyBy('check');
    expect($rows['Penpot has a price before it is on sale']['ok'])->toBeFalse()->and($rows['Penpot has a price before it is on sale']['blocking'])->toBeTrue()
        ->and($rows['Penpot is sold only with a Penpot node to run it']['ok'])->toBeFalse();
    Artisan::call('onhost:doctor', ['--json' => true]);
    expect(Artisan::output())->toContain('Penpot has a price before it is on sale')->toContain('Penpot is sold only with a Penpot node to run it');
});

it('lists Penpot with the web hosting in the customer sidebar', function () {
    expect(PanelNavigation::categoryFor('penpot', 'penpot', 'penpot'))->toBe('web');
});

it('renders the stack files from the official names, with secrets only in the env file', function () {
    $env = PenpotCompose::env(['version' => '2.18', 'port' => 19005, 'public_uri' => 'https://ab12cd34.penpot.onhost.cz', 'flags' => ['disable-registration', 'enable-prepl-server'],
        'secret_key' => str_repeat('k', 64), 'db_password' => 'p$ss"word with space', 'postgres_image' => 'postgres:15', 'valkey_image' => 'valkey/valkey:8.1', 'valkey_maxmemory' => '128mb', 'max_body_size' => 367001600]);
    expect($env)->toContain('PENPOT_FLAGS="disable-registration enable-prepl-server"')->toContain('PENPOT_DATABASE_PASSWORD="p\\$ss\\"word with space"')
        ->toContain('PENPOT_HTTP_SERVER_MAX_BODY_SIZE=367001600')->toContain('ONHOST_PORT=19005');

    $compose = PenpotCompose::compose('penpot-ab12cd34ef', ['ram_mb' => 8192, 'cpus' => 4]);
    foreach (['PENPOT_PUBLIC_URI', 'PENPOT_SECRET_KEY', 'PENPOT_DATABASE_URI: postgresql://penpot-postgres/penpot', 'PENPOT_DATABASE_USERNAME: penpot', 'PENPOT_INTERNAL_URI: http://penpot-frontend:8080',
        'PENPOT_OBJECTS_STORAGE_BACKEND: fs', 'POSTGRES_INITDB_ARGS: --data-checksums', 'pg_isready -U penpot', 'valkey-cli ping | grep PONG', 'name: penpot-ab12cd34ef', 'cpus: "4"', 'memory: 3571m'] as $needle) {
        expect($compose)->toContain($needle);
    }
    expect($compose)->not->toContain('mailcatch')->not->toContain('9001:');

    expect(PenpotCompose::smtpEnv([], null))->not->toContain('PENPOT_SMTP_HOST');
    expect(PenpotCompose::smtpEnv(['host' => 'smtp.onhost.cz', 'from' => 'penpot@onhost.cz', 'username' => 'penpot'], 'tajne'))->toContain('PENPOT_SMTP_HOST=smtp.onhost.cz')->toContain('PENPOT_SMTP_PASSWORD=tajne')->toContain('PENPOT_SMTP_TLS=true');
    expect(PenpotCompose::proxySite('srv_x', 'ab12cd34.penpot.onhost.cz', 19005, 367001600))->toContain("ab12cd34.penpot.onhost.cz {\n")->toContain('reverse_proxy 127.0.0.1:19005')->toContain('max_size 350MB');
});
