<?php

declare(strict_types=1);

use App\Http\Controllers\Web\LegalDocumentController;
use App\Http\Support\PublicLegalLinks;
use App\Http\Support\ServicePageClaimsSeam;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Orders\LegalDocuments;
use Onhost\Domain\Orders\Models\ConsentDocument;

/*
 * L3 / M1 (K7-3, Comgate review row 1/3): the page "Podmínky, GDPR a cookies" (`#/sluzba/legal`, the `legal` block of
 * onhost-svc-corp.js) listed invented documents ("VOP v4.2", a DPA v3.1, a security policy, an archive "since 2019"), KPIs
 * (4 documents, 30 days' notice), ISO 27001 / NIS2 chips, a cookie banner, a 31/90-page comparison, an ISO tender case and an FAQ
 * with data "in Prague and Brno" and incident reports "within 24 hours"; its "Otevřít" links did nothing. The shared SLA band of
 * the corporate pages promised 99,99 %, a 15-minute P1 response and six European locations. The page now lists the documents in
 * force (`/dokumenty`), each opening its own address, and the band says what the SLA in force says.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, ContentSeeder::class]);
    Cache::flush();
    config(['onhost.ui.demo' => false]);
});

/** @return array<string, mixed> the `cs`/`en` data of the generated `onhost-data.js` */
function lpcData(object $test): array
{
    $js = (string) $test->get('/surfaces/onhost-data.js')->assertOk()->getContent();
    preg_match('/var D = (\{.*\});\n  function L/s', $js, $m);

    return (array) json_decode($m[1] ?? '{}', true);
}

/** @return list<string> keys of the documents in force (a published version with a text), as /dokumenty lists them */
function lpcInForce(): array
{
    $keys = [];
    foreach (LegalDocumentController::SLUGS as $key) {
        $document = ConsentDocument::current($key);
        if ($document !== null && LegalDocuments::path($key, (string) $document->version) !== null) {
            $keys[] = $key;
        }
    }

    return $keys;
}

it('lists exactly the documents in force with their real version, date and address', function () {
    $data = lpcData($this);
    $inForce = lpcInForce();
    expect($inForce)->not->toBeEmpty();

    foreach (['cs', 'en'] as $locale) {
        $docs = $data[$locale]['legalDocs'] ?? null;
        expect($docs)->toBeArray()->toHaveCount(count($inForce));
        $slugs = array_flip(LegalDocumentController::SLUGS);
        foreach ($docs as $i => $row) {
            $key = $inForce[$i];
            $current = ConsentDocument::current($key);
            expect($row)->toHaveCount(6)
                ->and($row[0])->toBe((string) ($current->title[$locale] ?? $current->title['cs']))
                ->and($row[1])->not->toBe('')
                ->and($row[2])->toBe((string) $current->version)
                ->and($row[3])->toBe($locale === 'en' ? $current->effective_from->format('j M Y') : $current->effective_from->format('j. n. Y'))
                ->and($row[5])->toBe('/dokumenty/'.$slugs[$key]);
            $this->get($row[5])->assertOk()->assertSee((string) $current->version);
        }
        // a draft (the 2026-10 texts before the lawyer) is never offered
        expect(json_encode($docs))->not->toContain('2026-10');
    }
    expect(PublicLegalLinks::documents('cs'))->toBe($data['cs']['legalDocs']);
});

it('serves the legal page without the invented documents, figures and certifications', function () {
    $prototype = (string) file_get_contents(base_path('apps/surfaces/onhost-svc-corp.js'));
    $served = (string) $this->get('/surfaces/onhost-svc-corp.js')->assertOk()->getContent();

    foreach ([
        "'v4.2'", "'v3.1'", "'v2.9'", "'v4.0'", "'v2.4'", "'v1.8'", "'v3.4'", '186 kB', 'od 2019', 'since 2019', "'ISO 27001', 'NIS2'", "'eIDAS'",
        'a cookie lišta, která', 'The cookie banner asks once', '~31 ', '~90 ', '12 stran', 'Praze a Brně', 'Prague and Brno', 'Do 24 hodin e-mailem', '< 24 h',
        'doklad o ISO 27001', 'ISO 27001 evidence', "['4', _('dokumenty celkem'", "_('oznámení před změnou', 'notice before a change')", 'Pojištění',
        "{ k: '99,99 %', v: _('dostupnost s pokutou'", "{ k: '15 min', v: _('reakce na P1 24/7'", "{ k: '6', v: _('lokalit v Evropě', 'European locations') },\n",
    ] as $claim) {
        expect(str_contains($prototype, $claim))->toBeTrue("the prototype carried {$claim}")
            ->and(str_contains($served, $claim))->toBeFalse("still served: {$claim}");
    }
    // the documents come from the data the server builds; the SLA band says what the SLA in force says
    expect($served)->toContain('window.ONHOST_DATA.legalDocs(cs)')
        ->toContain("{ k: '99,9 %', v: _('měsíční dostupnost (Standard, SLA)'")
        ->toContain("{ k: '4 h', v: _('reakce na incident (Standard)'");
    // the fields the page reads stay arrays (bench, kpis, feats, faq, plans), the block is still one object
    preg_match("/    'legal': C\\(\\{\n(.*?)\n    \\}\\),\n/s", $served, $block);
    expect($block)->not->toBeEmpty();
    foreach (['bench: [', 'kpis: [', 'feats: [', 'faq: [', 'plans: [', 'cases: [', 'tech: ['] as $field) {
        expect($block[1])->toContain($field);
    }
});

it('finds every legal-page needle exactly once in the byte-identical prototype', function () {
    $source = (string) file_get_contents(base_path('apps/surfaces/onhost-svc-corp.js'));
    foreach (ServicePageClaimsSeam::anchorCounts($source, 'onhost-svc-corp.js') as $needle => $hits) {
        expect($hits)->toBe(1, mb_substr($needle, 0, 100));
    }
    expect(trim((string) shell_exec('git -C '.escapeshellarg(base_path()).' status --porcelain -- apps/surfaces')))->toBe('');
});

it('opens the document a row names instead of swallowing the click', function () {
    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('if (d[5]) window.location.assign(d[5]);')
        ->not->toContain("openLabel: cs ? 'Otevřít' : 'Open', pdfLabel: cs ? 'PDF' : 'PDF',\n        on: (e) => { if (e && e.preventDefault) e.preventDefault(); }\n");
});

it('keeps the prototype copy in demo mode', function () {
    config(['onhost.ui.demo' => true]);
    Cache::flush();

    expect((string) $this->get('/')->assertOk()->getContent())->not->toContain('if (d[5]) window.location.assign(d[5]);');
});
