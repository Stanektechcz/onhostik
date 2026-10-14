<?php

declare(strict_types=1);

use App\Http\Support\SurfacePricing;
use App\Http\Support\SurfaceRenderer;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Invoicing\Models\LegalEntity;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Tax\VatPayerMode;

/*
 * L-10 (docs/legal/LEGAL_REVIEW_2026-10.md): ONhost is not a VAT payer (owner decision H-R0). A consumer must be shown the final
 * price (§ 12 of the Consumer Protection Act) and the price shown must be the price charged. The public site said "Všechny ceny
 * včetně DPH 21 %", the cart and the checkout "Bez DPH / DPH 21 %" and multiplied by 1.21, while the order of a non-payer carries
 * no VAT. Now the surfaces read the seller's VAT mode: no VAT line, no "bez DPH", the total equals the order.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
});

function l10NonPayer(): void
{
    config(['vat.payer' => false]);
    LegalEntity::query()->update(['vat_payer' => false]);
    VatPayerMode::forget();
    Cache::forget('surfaces:onhost-data.js');
}

/** @return array<string, mixed> */
function l10Data($test): array
{
    Cache::forget('surfaces:onhost-data.js');
    $js = $test->get('/surfaces/onhost-data.js')->assertOk()->getContent();
    preg_match('/var D = (\{.*\});\n  function L/s', $js, $m);

    return ['data' => json_decode($m[1] ?? '{}', true), 'js' => $js];
}

it('gives the surfaces no VAT rate and says it is not a payer, while a payer keeps the rate of the tax rules', function () {
    $payer = l10Data($this);
    expect($payer['data']['cs']['vatPayer'])->toBeTrue()->and($payer['data']['cs']['vat']['rate'])->toBeGreaterThan(0.0);

    l10NonPayer();
    $nonPayer = l10Data($this);
    expect(SurfacePricing::payer())->toBeFalse()
        ->and($nonPayer['data']['cs']['vatPayer'])->toBeFalse()->and($nonPayer['data']['en']['vatPayer'])->toBeFalse()
        ->and($nonPayer['data']['cs']['vat']['rate'])->toEqual(0.0)
        ->and($nonPayer['js'])->toContain('vatPayer: function () {');
    // the domains page never names a VAT the seller does not charge
    expect(json_encode($nonPayer['data']['cs']['pages']['domains']['plans'] ?? [], JSON_UNESCAPED_UNICODE))->not->toContain('DPH 0')->not->toContain('s DPH')->toContain('nejsme plátci DPH');
});

it('lets the public site, the cart and the checkout drop the VAT line and the "bez DPH" wording for a non-payer', function () {
    $html = app(SurfaceRenderer::class)->transform((string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html')), 'public', false);

    expect($html)->toContain('Nejsme plátci DPH, ceny jsou konečné.')->toContain('We are not VAT registered; prices are final.')
        // the VAT rows of the cart and the checkout render only while there is a VAT label
        ->toContain('<sc-if value="{{ ct.vatLabel }}" hint-placeholder-val="{{ true }}">')->toContain('<sc-if value="{{ nv.coVat }}" hint-placeholder-val="{{ true }}">')
        ->toContain('coNet: ('.SurfaceRenderer::NON_PAYER_JS." ? 'Cena' : 'Cena bez DPH'),")
        ->toContain('netLabel: ('.SurfaceRenderer::NON_PAYER_JS." ? _('Cena', 'Price') : _('Bez DPH', 'Excl. VAT'))");
    // the prototype itself stays byte-identical
    expect((string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html')))->toContain("footVat: 'Všechny ceny včetně DPH 21 %'");
});

it('quotes a non-payer\'s order without VAT, so the total the surfaces show is the amount of the order', function () {
    l10NonPayer();
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c', 'vat_status' => 'unknown']);

    expect((int) $quote->tax_minor)->toBe(0)->and((int) $quote->total_minor)->toBe((int) $quote->subtotal_minor - (int) $quote->discount_minor)->and(SurfacePricing::vat()['rate'] ?? null)->toEqual(0.0);
});
