<?php

declare(strict_types=1);

use App\Http\Support\CatalogPresentation;
use App\Http\Support\PricingPageSeam;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Orders\QuoteService;

/*
 * K7-2 (L2, found by the K7 browser check, L-03): the pricing page `#/ceny` sells the catalogue's web hosting plans, but its
 * comparison table was a static VPS table (vCPU / RAM / NVMe, Anti-DDoS 100 Gbps–1,2 Tbps, SLA 99,99 %, support "10 min /
 * telefon") from config, its FAQ promised a migration "bez výpadku", 1,2 Tbps of DDoS filtering and five EU locations, the
 * yearly switch said "2 měsíce zdarma" and divided the monthly price by 12/10 in the browser whatever the catalogue said, and
 * the card button put "VPS <plan>" in the cart. The table now comes from the plans the cards show, the FAQ says only what the
 * documents in force say, the yearly price is the catalogue's and the cart line names the real product.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, ContentSeeder::class]);
    Cache::flush();
});

/** @return array<string, mixed> the `cs`/`en` data of the generated `onhost-data.js` */
function ppcData(object $test): array
{
    $js = (string) $test->get('/surfaces/onhost-data.js')->assertOk()->getContent();
    preg_match('/var D = (\{.*\});\n  function L/s', $js, $m);

    return (array) json_decode($m[1] ?? '{}', true);
}

it('builds the comparison table from the plans the pricing cards show, not from a VPS table', function () {
    $data = ppcData($this);

    foreach (['cs', 'en'] as $locale) {
        $compare = $data[$locale]['compare'];
        expect($compare)->not->toBeEmpty();
        $flat = json_encode($compare, JSON_UNESCAPED_UNICODE);
        foreach (['Tbps', 'Gbps', '99,99', '99.99', '10 min', 'telefon', 'phone', 'CI/CD', 'vCPU', '1 TB', 'replika', 'replica'] as $claim) {
            expect($flat)->not->toContain($claim);
        }
        foreach ($compare as $row) {
            expect($row)->toHaveCount(4); // label + the three plan columns of the table
        }
        // the columns are the cards' plans, in the cards' order, with the values the catalogue gives them
        $product = collect(app(CatalogService::class)->publicCatalog($locale, 'CZK'))->firstWhere('key', config('onhost.content.plans_product', 'web-hosting'));
        $plans = array_slice(array_map(fn ($p) => ['name' => $p['name'], 'entitlements' => (array) $p['entitlements']], (array) $product['plans']), 0, 3);
        $expected = array_map(fn ($r) => array_pad($r, 4, '—'), CatalogPresentation::compareRows($plans, (string) $product['family'], $locale));
        expect($compare)->toBe($expected)
            ->and(array_map(fn ($p) => $p['name'], $plans))->toBe(array_slice(array_map(fn ($c) => $c[$locale][0], $data[$locale]['plans']), 0, 3));
    }
});

it('names the real product in the cart line of a pricing card and gives the catalogue yearly price', function () {
    $data = ppcData($this);
    $product = collect(app(CatalogService::class)->publicCatalog('cs', 'CZK'))->firstWhere('key', config('onhost.content.plans_product', 'web-hosting'));

    foreach ($data['cs']['plans'] as $card) {
        expect($card['label'])->toBe($product['name'].' '.$card['cs'][0])->not->toStartWith('VPS ')
            ->and($data['cs']['skus'])->toHaveKey(mb_strtolower($card['label']))
            ->and($data['cs']['skus'][mb_strtolower($card['label'])])->toMatchArray(['plan_key' => $card['key']])
            ->and($card)->toHaveKey('priceYear');
        expect($card['cs'][1])->not->toContain('99,99');
    }
});

it('serves the pricing page without the static claims and with the catalogue-driven card and yearly price', function () {
    $html = $this->get('/')->assertOk()->getContent();

    foreach ([
        "save: 'Ročně = 2 měsíce zdarma'", "save: 'Yearly = 2 months free'", "order: 'Vyzkoušet zdarma →'", "order: 'Start free trial →'",
        'my přeneseme weby, databáze i e-maily většinou do 24 hodin a bez výpadku', 'usually within 24 hours, with no downtime',
        'Filtrujeme na hraně sítě, kapacita 1,2 Tbps', 'We filter at the network edge with 1.2 Tbps of capacity',
        'V EU — Praha, Brno, Frankfurt, Amsterdam, Varšava', 'In the EU — Prague, Brno, Frankfurt, Amsterdam, Warsaw',
        "go: pick('VPS ' + c[0], p.price) };",
        'price: this.czk(this.yr(p.price)), per, wrapStyle: planStyle(p.hi, i === 2)',
    ] as $claim) {
        expect($html)->not->toContain($claim);
    }
    expect($html)->toContain('window.ONHOST_DATA.yearlySave')
        ->toContain("go: pick(p.label || ('VPS ' + c[0]), p.price) };")
        ->toContain('S přenosem webů, databází i e-mailů pomůžeme; termín a případnou odstávku domluvíme předem.');
});

it('says how many months the year saves only when the catalogue prices the year that way', function () {
    $plan = fn (float $month, ?float $year) => ['price' => ['month' => $month], 'price_year' => $year === null ? null : ['year' => $year]];

    expect(PricingPageSeam::yearlySave([$plan(89, 890), $plan(189, 1890)], 'cs'))->toBe('Ročně = 2 měsíce zdarma')
        ->and(PricingPageSeam::yearlySave([$plan(89, 890), $plan(189, 1890)], 'en'))->toBe('Yearly = 2 months free')
        ->and(PricingPageSeam::yearlySave([$plan(100, 1100)], 'cs'))->toBe('Ročně = 1 měsíc zdarma')
        ->and(PricingPageSeam::yearlySave([$plan(100, 700)], 'cs'))->toBe('Ročně = 5 měsíců zdarma')
        // different savings per plan: no number, only that the year is cheaper by the price list
        ->and(PricingPageSeam::yearlySave([$plan(89, 890), $plan(189, 2000)], 'cs'))->toBe('Ročně levněji podle ceníku')
        // the year costs twelve months, or the catalogue has no yearly price: nothing is promised
        ->and(PricingPageSeam::yearlySave([$plan(89, 1068)], 'cs'))->toBe('')
        ->and(PricingPageSeam::yearlySave([$plan(89, null)], 'cs'))->toBe('')
        ->and(PricingPageSeam::yearlySave([], 'cs'))->toBe('');

    // the generated data carries the derived text for both languages
    $data = ppcData($this);
    expect($data['cs'])->toHaveKey('yearlySave')->and($data['en'])->toHaveKey('yearlySave');
});

it('tags a plan with the SLA figure of the document in force only', function () {
    expect(PricingPageSeam::slaTag('business', 'cs'))->toBe('SLA 99,95 %')
        ->and(PricingPageSeam::slaTag('business', 'en'))->toBe('SLA 99.95%')
        ->and(PricingPageSeam::slaTag('ha', 'cs'))->toBe('') // the SLA 2026-09 knows Standard and Business only
        ->and(PricingPageSeam::slaTag('standard', 'cs'))->toBe('')
        ->and(PricingPageSeam::slaTag(null, 'cs'))->toBe('');
});

it('keeps the prototype copy in demo mode', function () {
    config(['onhost.ui.demo' => true]);

    expect($this->get('/')->assertOk()->getContent())->toContain("save: 'Ročně = 2 měsíce zdarma'")->toContain("go: pick('VPS ' + c[0], p.price) };");
});

it('shows on every pricing card the monthly and yearly price the cart quotes, in minor units of CZK', function () {
    $data = ppcData($this);
    $skus = $data['cs']['skus'];
    $quotes = app(QuoteService::class); // the service behind POST api/v1/cart/quote (CartController::quote)
    $customer = ['country' => 'CZ', 'customer_class' => 'b2c', 'vat_status' => 'unknown', 'ip_country' => null];

    expect($data['cs']['plans'])->not->toBeEmpty();
    foreach ($data['cs']['plans'] as $card) {
        $sku = $skus[mb_strtolower($card['label'])];
        expect($sku['plan_key'])->toBe($card['key'])->and($card['priceYear'])->not->toBeNull();
        $item = ['line_id' => 'l1', 'product_key' => $sku['product_key'], 'plan_key' => $sku['plan_key'], 'qty' => 1, 'config' => []];
        $month = $quotes->quote([$item + ['period' => 'month']], 'CZK', $customer, 1);
        $year = $quotes->quote([$item + ['period' => 'year']], 'CZK', $customer, 12);

        // The card shows the catalogue's list price WITHOUT VAT (SurfaceDataController::planRows() reads the plan's
        // firstPeriodAmount, the amount QuoteService prices a line with before tax), so it is compared with the line's
        // `unit_net`, not with its total including VAT. The card carries whole crowns (month) and crowns with two decimals
        // (year); both are turned into minor units (haléře) and compared as integers.
        $monthLine = (array) $month->lines[0];
        $yearLine = (array) $year->lines[0];
        expect($month->currency)->toBe('CZK')->and($year->currency)->toBe('CZK')
            ->and($monthLine['period'])->toBe('month')->and($yearLine['period'])->toBe('year')
            ->and((int) $card['price'] * 100)->toBe((int) $monthLine['unit_net'])
            ->and((int) round((float) $card['priceYear'] * 100))->toBe((int) $yearLine['unit_net']);
    }
});
