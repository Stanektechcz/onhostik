<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Orders\QuoteService;

/*
 * TASK-0066 (follow-up of TASK-0058): the cart sells a transfer at the list RENEWAL price for the years it brings (owner rule:
 * whole years at the list price), but the catalogue (`GET /v1/catalog/tlds`) and the domain check (`POST /v1/domains/check`)
 * still showed the catalogue's transfer price — 0 Kč for .cz. A customer saw "transfer 0 Kč" and paid 179 Kč. The shown price
 * is now what the cart charges: renewal × the years a transfer line brings by default (`transfer_years`).
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class]);
    Http::preventStrayRequests();
});

it('shows in the TLD list the transfer price the cart charges: renewal times the default years', function () {
    $tlds = collect($this->getJson('/v1/catalog/tlds?currency=CZK')->assertOk()->json('data'))->keyBy('tld');

    $cz = $tlds['cz'];
    expect($cz['transfer_years'])->toBe($cz['default_period'])
        ->and($cz['transfer']['minor'])->toBe($cz['renew']['minor'] * $cz['default_period'])
        ->and($cz['transfer']['minor'])->toBe(17900);

    $quote = app(QuoteService::class)->quote([['product_key' => 'domain', 'config' => ['fqdn' => 'cena-prevodu.cz', 'action' => 'transfer']]], 'CZK', ['country' => 'CZ']);
    expect($quote['lines'][0]['net'])->toBe($cz['transfer']['minor']);
});

it('shows in the domain check the transfer price the cart charges', function () {
    Cache::put('onhost:domain:avail:obsazena-cena.cz', ['available' => false, 'reason' => 'registered', 'premium' => false], 60);

    $row = $this->postJson('/v1/domains/check', ['names' => ['obsazena-cena.cz'], 'currency' => 'CZK'])->assertOk()->json('data.0');

    expect($row['price_transfer'])->toBe(17900)->and($row['price_transfer'])->toBe($row['price_renew'] * $row['transfer_years'])
        ->and($row['transfer_years'])->toBe(1);
});
