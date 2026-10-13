<?php

declare(strict_types=1);

use App\Http\Support\ServicePageClaimsSeam;
use App\Http\Support\SurfaceRenderer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * Phase Q (PROMPT-faze-Q-2026-10-13.md, ROZHODNUTI.md P-R37, P-R69, P-R77):
 *  - Q1: the leftovers phase P found — the about, enterprise and data-centre pages were made up end to end (42 people, a 24/7 NOC
 *    in Prague, six data centres, own halls, TIER III, ISO 27001, NIS2 "ready", SLA 99,99 % with a 15-minute reply, measured
 *    uptime) and are replaced by what the documents in force say; the careers page's "24/7 shifts", colocation "24/7 access",
 *    the CDN page's 1,2 Tbps / 14 PoP / < 3 s, the web SLA band's automatic credit "without asking" (SLA čl. 4: claimed in the
 *    panel), the public SLA page's "we filter up to 2 Tb/s", the prototype's "Anti-DDoS Pro" add-on, a game plan's "Anti-DDoS
 *    600 Gbps" and 10-minute support, a fabricated "480 Gbps" incident and colocation "remote hands 24/7" in the search.
 *  - Q3 (P-R77): no "Vyzkoušet zdarma" button (there is no trial) and no vector-database latency or recall figure.
 * The prototypes stay byte-identical (the seams correct the served copy outside demo mode); demo mode keeps the prototype copy.
 */

/** @return array<string, list<string>> script => what must not be served any more */
function pqcWithdrawn(): array
{
    return [
        'onhost-svc-compute.js' => ['přístupem 24/7', '24/7 access', '24/7 na kartu', '24/7 badge', "chips: ['TIER III'", "['14 ms', _('p95 dotaz", "['98,6 %', 'recall@10']",
            "note: '14 ms p95'", 'recall@10 nad 98 %'],
        'onhost-svc-corp.js' => ['Šest lokalit, dvě z nich', "'6 lokalit · TIER III", 'ISO 27001', "_('NOC 24/7', '24/7 NOC')", 'NOC v budově 24/7', 'Vlastní AS, peering na třech uzlech',
            'Všech šest lokalit', 'Dva sály stavíme sami'],
        'onhost-svc-other.js' => ['NOC 24/7 v Praze', '24/7 NOC in Prague', 'směny 24/7', '24/7 shifts', '42 lidí', '12 800', 'šest lokalit', '6 lokalit', 'ISO 27001',
            "'NIS2'", 'Připraveno na NIS2', 'Podle NIS2: klasifikace', "'SLA 99,99 %'", 'Reakce do 15 minut', 'Reakce do 30 minut', 'kredit vyplacený automaticky',
            'měření je nezávislé', 'Naměřená dostupnost a doba obnovy', 'Ve tři ráno vám odpoví'],
        'onhost-svc-more.js' => ['ISO 27001, DPA', 'Ano, včetně dokumentace, auditu'],
        'onhost-svc-web.js' => ['nemusíte o nic žádat', 'no ticket needed', '1,2 Tbps · 14 PoP', "['1,2 Tbps', _('kapacita mitigace'", "['< 3 s', _('detekce útoku'", 'Statika ze čtrnácti lokalit'],
        'onhost-public.js' => ['Filtrujeme do 2 Tb/s'],
    ];
}

/** @return array<string, list<string>> script => what the documents in force say instead */
function pqcStated(): array
{
    return [
        'onhost-svc-compute.js' => ['Fyzický přístup k racku domlouváme předem.', 'Výkon vektorové databáze zveřejníme, až ho doložíme měřením.'],
        'onhost-svc-corp.js' => ['Kde konkrétní služba běží, vám sdělí podpora.', 'Účinnost (PUE) zveřejníme, až ji doložíme měřením.'],
        'onhost-svc-other.js' => ['Služby pro firmy podle smlouvy', "_('SLA podle individuální smlouvy', 'SLA by individual contract')", 'Kdo Onhost provozuje',
            'Provozovatele (název, IČO a sídlo) uvádějí obchodní podmínky.', "specs: [_('Praha', 'Prague'), "],
        'onhost-svc-more.js' => ['Povinnosti podle NIS2 nese provozovatel regulované služby.'],
        'onhost-svc-web.js' => ['Kompenzaci za nedodrženou dostupnost uplatníte v zákaznickém panelu', 'CDN a WAF · připravujeme'],
        'onhost-public.js' => ['Parametry ochrany proti DDoS (kapacitu, vrstvy a dobu reakce) uvedeme, až je doložíme.'],
    ];
}

it('finds every new needle and block marker exactly once in the byte-identical prototypes', function () {
    foreach (array_keys(pqcWithdrawn()) as $script) {
        $source = (string) file_get_contents(base_path('apps/surfaces/'.$script));
        foreach (ServicePageClaimsSeam::anchorCounts($source, $script) as $needle => $hits) {
            expect($hits)->toBe(1, "{$script}: ".mb_substr($needle, 0, 100));
        }
        foreach (ServicePageClaimsSeam::blocks()[$script] ?? [] as [$from, $to]) {
            expect(substr_count($source, $from))->toBe(1, "{$script} block start")->and(substr_count($source, $to))->toBe(1, "{$script} block end");
        }
    }
    $page = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    foreach (array_keys(ServicePageClaimsSeam::phaseQCopySwaps()) as $needle) {
        expect(substr_count($page, $needle))->toBe(1, 'Onhost.dc.html: '.mb_substr($needle, 0, 100));
    }
});

it('serves the scripts without the invented company, data-centre, DDoS and vector figures outside demo mode', function (string $script) {
    config(['onhost.ui.demo' => false]);
    $prototype = (string) file_get_contents(base_path('apps/surfaces/'.$script));
    $served = (string) $this->get('/surfaces/'.$script)->assertOk()->getContent();

    foreach (pqcWithdrawn()[$script] as $claim) {
        expect(str_contains($prototype, $claim))->toBeTrue("the prototype of {$script} carried {$claim}")
            ->and(str_contains($served, $claim))->toBeFalse("{$script} still serves {$claim}");
    }
    foreach (pqcStated()[$script] as $stated) {
        expect($served)->toContain($stated);
    }
    // the served script is still valid JavaScript (a block or needle swap must not break the module)
    $node = (new ExecutableFinder)->find('node');
    if ($node !== null) {
        $tmp = tempnam(sys_get_temp_dir(), 'svc').'.js';
        file_put_contents($tmp, $served);
        $check = new Process([$node, '--check', $tmp]);
        $check->run();
        @unlink($tmp);
        expect($check->getExitCode())->toBe(0, $check->getErrorOutput());
    }
})->with(['onhost-svc-compute.js', 'onhost-svc-corp.js', 'onhost-svc-other.js', 'onhost-svc-more.js', 'onhost-svc-web.js', 'onhost-public.js']);

it('has no trial button, no Anti-DDoS Pro and no invented DDoS incident on the public site outside demo mode', function () {
    $prototype = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    $live = app(SurfaceRenderer::class)->transform($prototype, 'public', false);

    foreach (['Vyzkoušet zdarma', 'Start free trial', 'Anti-DDoS Pro', 'DDoS 480 Gbps', 'ruce na místě 24/7', 'remote hands 24/7'] as $claim) {
        expect($prototype)->toContain($claim)->and($live)->not->toContain($claim);
    }
    expect($live)->toContain("navCta: 'Vytvořit účet',")->toContain("navCta: 'Create account',")->toContain("cfgOrder: 'Objednat', cfgFine:");
    $report = collect(app(SurfaceRenderer::class)->anchorReport('public'))
        ->filter(fn (array $a) => in_array($a['anchor'], array_keys(ServicePageClaimsSeam::phaseQCopySwaps()), true));
    expect($report)->toHaveCount(count(ServicePageClaimsSeam::phaseQCopySwaps()))->and($report->every(fn (array $a) => $a['hits'] === 1))->toBeTrue();
});

it('keeps the prototype copy in demo mode and the prototypes byte-identical', function () {
    config(['onhost.ui.demo' => true]);
    $response = $this->get('/surfaces/onhost-svc-other.js')->assertOk();
    $served = $response->baseResponse instanceof BinaryFileResponse
        ? (string) file_get_contents($response->baseResponse->getFile()->getPathname())
        : (string) $response->getContent();
    expect($served)->toContain('NOC 24/7 v Praze')->toContain('ISO 27001');
    $prototype = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    expect(app(SurfaceRenderer::class)->transform($prototype, 'public', true))->toContain('Vyzkoušet zdarma')->toContain('Anti-DDoS Pro');

    $status = shell_exec('git -C '.escapeshellarg(base_path()).' status --porcelain -- apps/surfaces '.escapeshellarg(':(exclude)apps/surfaces/api').' 2>&1');
    expect(trim((string) $status))->toBe('');
});
