<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Content\Models\StockItem;

/*
 * J-R1 (owner, 2026-10-11, "Stáhni smyšlené údaje z Techniky"): the public site no longer tells the invented "first year with a
 * customer" story, shows no eco figures (PUE 1,18, 2,4 GWh, 310 t CO₂, 40 homes heated) and no EPYC stock counts until the
 * owner supplies real, documented numbers. The prototype files stay byte-identical; demo mode keeps the prototype's copy.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, ContentSeeder::class]);
    Cache::flush();
});

function publicTechnikaHome($test): string
{
    return $test->get('/')->assertOk()->getContent();
}

it('serves no first-year story, no eco figures and no EPYC stock counts on the public site', function () {
    $html = publicTechnikaHome($this);

    foreach ([
        // the "first year with a customer" story (#/ceny-a-sla)
        'První rok u nás, doložený', 'A first year here, documented', 'Jeden skutečný zákazník měsíc po měsíci', 'Přišli z Wedosu na jeden VPS',
        'Came from Wedos on one VPS', 'Dostupnost 99,993 %', 'Uptime 99.993%', 'Tenhle rok neukazujeme proto', '{{ yearTitle }}', '{{ yearRows }}',
        // eco figures
        'PUE', '2,4 GWh', "'310 t'", 'CO₂', 'domácností vytápí odpadní teplo', 'homes heated by waste heat',
        'Úzkým místem naší GPU haly', 'The bottleneck in our GPU hall', 'elektřinu bereme z fotovoltaiky', 'our power comes from solar',
        'Čísla jsou zatím orientační', 'These figures are placeholders',
        // EPYC stock counts and the "in stock right now" section (#/technika)
        '24 kusů skladem', '41 kusů skladem', '24 units in stock', '41 units in stock', '{{ stockKicker }}', 'Skladem právě teď',
        'Čísla jsou z našeho skladu', 'a to, co máme právě teď skladem', 'and what is actually in stock',
    ] as $claim) {
        expect($html)->not->toContain($claim);
    }

    // the rest of #/technika and the price page still render
    expect($html)->toContain('{{ hwTitle }}')->toContain('{{ dayTitle }}')->toContain('{{ salesTitle }}')
        ->toContain('Čísla o energii a chlazení zveřejníme, až budou změřená a doložená.');
});

it('serves no stock row the prototype seed invented, but a row staff entered', function () {
    expect(collect($this->getJson('/v1/stock')->assertOk()->json('data'))->pluck(0)->all())->not->toContain('EPYC 9354')->not->toContain('EPYC 9124');

    // a database seeded before J-R1 still holds the invented rows: they are not served while they are untouched
    StockItem::query()->create(['sku' => 'epyc-9354', 'name' => 'EPYC 9354', 'spec' => ['cs' => '32 jader'], 'price_minor' => 890000, 'currency' => 'CZK', 'unit' => 'month', 'available' => 24, 'lead_days' => 0, 'availability' => 'ok', 'sort' => 1]);
    expect($this->getJson('/v1/stock')->json('data'))->toBe([]);

    // a real count entered by staff is served
    StockItem::query()->where('sku', 'epyc-9354')->update(['available' => 2]);
    expect($this->getJson('/v1/stock')->json('data.0.4'))->toBe('2 kusy skladem');
});

it('keeps the prototype copy in demo mode', function () {
    config(['onhost.ui.demo' => true]);

    expect(publicTechnikaHome($this))->toContain('První rok u nás, doložený')->toContain("{ v: 'PUE 1,18'")->toContain('24 kusů skladem');
});
