<?php

declare(strict_types=1);

use App\Http\Support\SurfacePricing;
use App\Http\Support\SurfaceRenderer;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Tax\Models\ExchangeRate;
use Onhost\Domain\Tax\Models\TaxRuleVersion;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * Audit 2026-10 P1-6, package C2 (owner decisions R13, R14): every number the public site and the panel show as a price comes
 * from the platform — plans from the catalogue (ONHOST_DATA), currency conversion from the ČNB list `onhost:fx:sync` stores,
 * VAT from the tax rules — and nothing that cannot be paid is offered: product pages without a catalogue product say
 * "Připravujeme" without a price or an order button, payment tiles the bridge refuses are gone.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class]);
    Http::preventStrayRequests();
    Cache::forget('surfaces:onhost-data.js');
});

/** @return array<string,mixed> the generated window.ONHOST_DATA payload */
function priceSourceData($test): array
{
    Cache::forget('surfaces:onhost-data.js');
    $js = $test->get('/surfaces/onhost-data.js')->assertOk()->getContent();
    preg_match('/var D = (\{.*\});\n  function L/s', $js, $m);

    return json_decode($m[1] ?? '{}', true);
}

it('runs every product page slug through the catalogue: no price without a SKU, unpriced pages say Připravujeme', function () {
    $node = (new ExecutableFinder)->find('node');
    if ($node === null) {
        $this->markTestSkipped('node is not installed; the harness is tests/js/product-pages-prices.harness.mjs');
    }
    $file = tempnam(sys_get_temp_dir(), 'onhost-data-').'.js';
    file_put_contents($file, $this->get('/surfaces/onhost-data.js')->assertOk()->getContent());
    try {
        $process = new Process([$node, base_path('tests/js/product-pages-prices.harness.mjs'), $file], base_path(), null, null, 120);
        $process->run();
        $out = json_decode($process->getOutput(), true);

        expect($out)->toBeArray('harness output: '.$process->getOutput().$process->getErrorOutput())
            ->and($out['failures'])->toBe([])
            ->and($process->getExitCode())->toBe(0);
        // every module's slugs were seen, the audit's pages are "coming", the catalogue's pages still sell
        expect($out['summary']['slugs'])->toBeGreaterThanOrEqual(45)
            ->and($out['summary']['coming'])->toContain('gpu', 'inference', 'vectordb', 'colocation', 'git', 'ci', 'migration', 'managed', 'storage', 'sol-dev')
            ->and($out['summary']['catalogue'])->toContain('web-hosting', 'vps', 'games', 'minecraft', 'eshop');
    } finally {
        @unlink($file);
    }
});

it('maps the game hosting page by its real slug', function () {
    $pages = priceSourceData($this)['cs']['pages'];

    expect($pages)->toHaveKey('games')->not->toHaveKey('gamehosting')
        ->and(array_column($pages['games']['plans'], 'sku'))->each->toHaveKey('product_key', 'game')
        ->and($pages['migration'])->toMatchArray(['coming' => true, 'label' => 'Připravujeme']);
});

it('offers only currencies with a fresh ČNB rate and converts at that rate', function () {
    // no list stored: only CZK, the switcher hides everything else
    expect(array_keys(priceSourceData($this)['cs']['currencies']))->toBe(['czk']);

    ExchangeRate::query()->create(['source' => 'cnb', 'currency' => 'EUR', 'valid_on' => now('Europe/Prague')->subDay()->toDateString(), 'amount' => 1, 'rate_micro' => 24_335_000, 'fetched_at' => now()]);
    $cur = priceSourceData($this)['cs']['currencies'];
    expect(array_keys($cur))->toBe(['czk', 'eur'])
        ->and($cur['eur']['rate'])->toEqualWithDelta(1 / 24.335, 1e-8)->and($cur['eur']['sym'])->toBe('€')->and($cur['eur']['source'])->toBe('cnb')
        ->and($cur['czk'])->toMatchArray(['rate' => 1, 'sym' => 'Kč', 'dec' => 0]);

    // a list older than the configured age is no rate of the day: the currency disappears again
    ExchangeRate::query()->delete();
    ExchangeRate::query()->create(['source' => 'cnb', 'currency' => 'EUR', 'valid_on' => now('Europe/Prague')->subDays(30)->toDateString(), 'amount' => 1, 'rate_micro' => 24_335_000, 'fetched_at' => now()]);
    expect(array_keys(SurfacePricing::currencies()))->toBe(['czk']);
});

it('takes VAT from the tax rules, not from ×1,21', function () {
    $data = priceSourceData($this);
    expect($data['cs']['vat'])->toBe(['rate' => 0.21, 'percent' => '21']);

    // a new rule version with another standard rate reaches the surfaces without a code change
    $current = TaxRuleVersion::query()->where('state', 'active')->orderByDesc('version')->firstOrFail();
    $rules = $current->rules;
    $rules['standard_rates']['CZ'] = 23;
    TaxRuleVersion::query()->whereKey($current->getKey())->update(['state' => 'superseded', 'effective_to' => now()->subSecond()]);
    TaxRuleVersion::query()->create(['version' => $current->version + 1, 'state' => 'active', 'effective_from' => now()->subSecond(), 'rules' => $rules, 'note' => 'test: 23 %']);

    $data = priceSourceData($this);
    expect($data['cs']['vat'])->toBe(['rate' => 0.23, 'percent' => '23'])->and($data['en']['vat']['percent'])->toBe('23');
    // the domains page's gross price follows it: 179 Kč net → 220 Kč with 23 % (was 217 Kč with 21 %)
    expect($data['cs']['pages']['domains']['plans'][0]['priceNote'])->toContain('s DPH 220 Kč');
});

it('shows the .cz transfer price the cart charges in the public data and the panel', function () {
    $tlds = collect(priceSourceData($this)['cs']['tlds'])->keyBy('tld');
    $quote = app(QuoteService::class)->quote([['product_key' => 'domain', 'config' => ['fqdn' => 'cena-prevodu.cz', 'action' => 'transfer']]], 'CZK', ['country' => 'CZ']);

    expect($tlds['cz']['transfer'])->toEqual(179.0)->and((int) round($tlds['cz']['transfer'] * 100))->toBe($quote['lines'][0]['net']);
});

it('wires the public and panel seams to the platform numbers and hides the payment tiles nobody takes', function () {
    $html = $this->get('/')->assertOk()->getContent();

    // FX: the prototype's CUR table (1/25, 1/23 …) is replaced by ONHOST_DATA.currencies(); the switcher lists only what it holds
    expect($html)->toContain('  CUR = (window.ONHOST_DATA && typeof window.ONHOST_DATA.currencies === \'function\' && window.ONHOST_DATA.currencies())')
        ->not->toContain('eur: { rate: 1 / 25,')->toContain('.filter(c => !!this.CUR[c[0]]).map(c => ({');
    // VAT: czk(), the fallback totals, the checkout and the labels read the tax rules' rate
    expect($html)->not->toContain('n * 1.21 * c.rate')->not->toContain('vat: after * 0.21, total: after * 1.21')->not->toContain('vat: this.mny(net * 0.21), total: this.mny(net * 1.21 + s.credit)')
        ->not->toContain("vatLabel: _('DPH 21 %', 'VAT 21%')")->not->toContain("coVat: 'DPH 21 %'")->not->toContain("footVat: 'Všechny ceny včetně DPH 21 %'")
        ->toContain('window.ONHOST_DATA.vat()');
    // R13: card and bank transfer only — Apple Pay / Google Pay, PayPal, crypto and SEPA are gone
    expect($html)->toContain("['card', 'Karta', 'Visa, Mastercard · platební brána'], ['bank', 'Bankovní převod', 'QR platba, zálohová faktura']\n")
        ->not->toContain("['wallet', 'Apple Pay / Google Pay'")->not->toContain("['paypal', 'PayPal'")->not->toContain("['crypto', 'Krypto'")->not->toContain("['sepa', 'SEPA inkaso'");
    // the game landing never falls back to the prototype's slot prices
    expect($html)->not->toContain("? window.ONHOST_DATA.gameSlots(cs) : null) || [\n      { p: 149,");

    $panel = app(SurfaceRenderer::class)->transform((string) file_get_contents(base_path('apps/surfaces/Onhost-app.dc.html')), 'panel', false);
    expect($panel)->toContain('  CUR = (window.ONHOST_PANEL && window.ONHOST_PANEL.currencies)')->not->toContain("eur: { sym: '€', rate: 0.04,")
        ->not->toContain('orderSize[2] * 1.21');
});

it('names the renewal price in the domain renew confirmation', function () {
    $module = (string) file_get_contents(base_path('apps/surfaces/api/onhost-panel-workbench.api.js'));

    expect($module)->toContain('function renewPrice(sel)')->toContain("_('Prodloužit ' + sel.name + ' o 1 rok za ' + price");
});
