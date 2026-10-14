<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Orders\Models\ConsentDocument;

/*
 * C-R10a (J4, TASK-0155; Comgate review rows 1, 3, 4, 5, 7, 16): the public footer links the legal documents in force and the
 * consumer's out-of-court dispute body (ČOI). A draft — the complaints rules of the `2026-10` texts before the lawyer — is not
 * linked until it is published, and the EU ODR platform (shut down 20 July 2025, L-13) is not linked at all. The prototype stays
 * byte-identical; demo mode keeps the prototype's footer.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Cache::flush();
});

/** @return array{cs: list<array{0:string,1:string}>, en: list<array{0:string,1:string}>} */
function publicLegalLinksData($test): array
{
    $js = $test->get('/surfaces/onhost-data.js')->assertOk()->getContent();
    preg_match('/var D = (\{.*\});\n  function L/s', $js, $m);
    $data = json_decode($m[1], true);

    return ['cs' => $data['cs']['legal'], 'en' => $data['en']['legal']];
}

it('links every document in force and the Czech Trade Inspection, never a draft and never the closed ODR platform', function () {
    $links = publicLegalLinksData($this);
    $hrefs = array_column($links['cs'], 1);

    foreach (['terms' => '/dokumenty/vop', 'privacy' => '/dokumenty/ochrana-osobnich-udaju', 'withdrawal_waiver' => '/dokumenty/odstoupeni'] as $key => $href) {
        if (ConsentDocument::current($key) !== null) {
            expect($hrefs)->toContain($href);
        }
    }
    expect($hrefs)->toContain('/dokumenty')->toContain('https://adr.coi.cz/cs')
        ->and(implode(' ', $hrefs))->not->toContain('ec.europa.eu')
        ->and(array_column($links['en'], 0))->toContain('Out-of-court disputes: ČOI');
    if (ConsentDocument::current('complaints') === null) { // the 2026-10 complaints rules are an unpublished draft until the lawyer
        expect($hrefs)->not->toContain('/dokumenty/reklamacni-rad');
    }
    // every linked document answers (no link to a 404)
    foreach (array_filter($hrefs, fn ($h) => str_starts_with($h, '/dokumenty/')) as $href) {
        $this->get($href)->assertOk();
    }
});

it('renders the legal block in the public footer and keeps the prototype footer in demo mode', function () {
    $html = $this->get('/')->assertOk()->getContent();
    expect($html)->toContain('<sc-for list="{{ footLegal }}" as="fl"')->toContain('<a href="{{ fl.href }}"')
        ->toContain('footLegal: ((window.ONHOST_DATA && window.ONHOST_DATA.legalLinks')
        // the card tile and the default method follow the gateway
        ->toContain("window.ONHOST_DATA.cardPayments() === false) ? [] : [['card', 'Karta'")
        ->toContain("pay: ((window.ONHOST_DATA && typeof window.ONHOST_DATA.cardPayments === 'function'");

    config(['onhost.ui.demo' => true]);
    expect($this->get('/')->assertOk()->getContent())->not->toContain('footLegal')->toContain("['card', 'Karta', 'Visa, Mastercard · Stripe']");
});
