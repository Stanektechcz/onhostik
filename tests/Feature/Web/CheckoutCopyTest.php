<?php

declare(strict_types=1);

use App\Http\Support\SurfaceRenderer;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * M2 (= L4, K7-4 … K7-6, owner decision J-R9): the checkout said "Platba dle vás — Karta, převod nebo faktura se splatností 14 dní"
 * while no card gateway was set up and no invoice on credit exists, "Doplňky (ceny bez DPH)" and "Celkem k platbě (s DPH)" to the
 * customers of a seller who is not a VAT payer, offered credit tiers with a 10 % bonus the control plane never credits, a button
 * "Zaplatit a spustit server" for a web hosting order, a transfer amount "49.00 CZK", and every public page asked for
 * `/{{ gcx.gameArt }}` (an image source written before the template filled it in).
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, ContentSeeder::class]);
    Cache::flush();
    config(['onhost.ui.demo' => false]);
});

it('offers the card in the checkout promise only with a working gateway and names no invoice on credit', function () {
    $html = (string) $this->get('/')->assertOk()->getContent();

    foreach (["coRe4: 'Platba dle vás', coRe4d: 'Karta, převod nebo faktura se splatností 14 dní pro firmy a instituce.',", "coRe4: 'Pay your way', coRe4d: 'Card, bank transfer, or a 14-day invoice for companies and public bodies.',"] as $old) {
        expect($html)->not->toContain($old);
    }
    expect($html)->toContain(SurfaceRenderer::CARD_JS.' ? \'Kartou nebo převodem\' : \'Platba převodem\'')
        ->not->toContain('faktura se splatností 14 dní pro firmy');
});

it('names VAT in the add-on heading only for a VAT payer', function () {
    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain("coAddons: 'Doplňky (ceny bez DPH)',")->not->toContain("coAddons: 'Add-ons (excl. VAT)',")
        ->toContain('coAddons: ('.SurfaceRenderer::NON_PAYER_JS." ? 'Doplňky' : 'Doplňky (ceny bez DPH)'),");
});

it('offers credit tiers without a bonus the control plane never credits', function () {
    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain("bonus: v ? '+ ' + this.mny(v * 0.1) : '',")->toContain("bonus: '',");
    // what the backend does: a top-up credits exactly what arrived (Finance/BankImportTest: 5 000 Kč → 5 000 Kč spendable)
});

it('promises a server start only for a server and asks for no image before the template is filled', function () {
    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain("coPay: 'Zaplatit a spustit server',")->not->toContain("coPay: 'Pay and launch the server',")
        ->toContain("coPay: (window.OnhostCart && window.OnhostCart.payLabel) ? window.OnhostCart.payLabel(this.state, true) : 'Zaplatit objednávku',")
        ->not->toContain('src="{{ gcx.gameArt }}"');
});

it('keeps the prototype copy in demo mode', function () {
    config(['onhost.ui.demo' => true]);

    expect((string) $this->get('/')->assertOk()->getContent())->toContain("coPay: 'Zaplatit a spustit server',")->toContain("coAddons: 'Doplňky (ceny bez DPH)',");
});

it('runs the cart module: VAT wording by the VAT mode, Czech amount format, server-only pay label', function () {
    $node = (new ExecutableFinder)->find('node');
    if ($node === null) {
        $this->markTestSkipped('node is not installed; the harness is tests/js/checkout-copy.harness.mjs');
    }
    $process = new Process([$node, base_path('tests/js/checkout-copy.harness.mjs')], base_path(), null, null, 120);
    $process->run();
    $out = json_decode($process->getOutput(), true);

    expect($out)->toBeArray('harness output: '.$process->getOutput().$process->getErrorOutput())
        ->and($out['failures'])->toBe([])
        ->and($process->getExitCode())->toBe(0);
});
