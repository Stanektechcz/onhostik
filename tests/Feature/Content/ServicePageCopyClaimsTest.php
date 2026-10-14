<?php

declare(strict_types=1);

use App\Http\Support\ServicePageClaimsSeam;
use App\Http\Support\SurfaceRenderer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * N1 (phase N, the rest of the unsupported figures after J-R2 / L-03): the compute pages promised 99,99 % "network and power
 * uptime", a P1 response "< 15 min", credits "converted automatically", "root in 55 s", "0 % CPU oversubscription" and "99,99 %
 * uptime in 2025"; the managed-server page a 15-minute (and 60-minute) response, "SLA 99,99 % with credits" and a measured median
 * of 15 minutes; every service page of Onhost.dc.html "an engineer replies within ten minutes" on a chat and "Try it for 14 days.
 * No card, no commitment … cancel in one click and pay nothing"; the game configurator "Servers in Prague, 24/7 support" and
 * "/ month incl. VAT" also for a seller who charges no VAT. Only what SLA 2026-09 (resources/legal/sla.md) and the VOP
 * (resources/legal/terms.md) say may stand in their place. The prototypes stay byte-identical; demo mode keeps the prototype copy.
 */

/** @return array<string, list<string>> script => what must not be served any more */
function spnWithdrawn(): array
{
    return [
        'onhost-svc-compute.js' => ["{ k: '99,99 %', v: _('dostupnost sítě a napájení'", "'< 15 min'", 'Každá minuta nad limit se přepočítá na kredit automaticky',
            'Every minute over the limit converts to credit automatically', 'root do 55 s', 'root in 55 s', 'od objednávky k rootu', 'from order to root',
            'přeprodej CPU', 'CPU oversubscription', 'dostupnost 2025', 'uptime in 2025'],
        'onhost-svc-dev.js' => ["['15 min', _('reakce na incident 24/7'", 'dostupnost s kreditem', 'uptime, credited', 'Reakce do 15 minut', '15-minute response',
            'Reakce do 60 minut', '60-minute response', 'SLA 99,99 % s kreditem', '99.99% SLA with credits', "'60 min', '15 min'", "['SLA', '99,9 %', '99,99 %'",
            'Onhost — reakce na P1', 'Medián doby od alertu k prvnímu zásahu člověka'],
    ];
}

/** @return array<string, list<string>> script => what the documents in force say instead */
function spnStated(): array
{
    return [
        'onhost-svc-compute.js' => ["{ k: '99,9 %', v: _('měsíční dostupnost (Standard, SLA)'", "{ k: '4 h', v: _('reakce na incident (Standard)'",
            "kicker: _('AMD EPYC · NVMe', 'AMD EPYC · NVMe'),"],
        'onhost-svc-dev.js' => ["_('Reakce na incident podle SLA', 'Incident response per the SLA')", "_('SLA s kompenzací kreditem', 'SLA with compensation as credit')",
            'Standard do 4 hodin, Business do 1 hodiny', "[_('Reakce', 'Response'), _('podle SLA', 'per the SLA'), _('podle SLA', 'per the SLA')"],
    ];
}

it('finds every new needle exactly once in the byte-identical prototypes', function () {
    foreach (array_keys(spnWithdrawn()) as $script) {
        $source = (string) file_get_contents(base_path('apps/surfaces/'.$script));
        foreach (ServicePageClaimsSeam::anchorCounts($source, $script) as $needle => $hits) {
            expect($hits)->toBe(1, "{$script}: ".mb_substr($needle, 0, 100));
        }
    }
    $page = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    foreach (array_keys(ServicePageClaimsSeam::pageCopySwaps()) as $needle) {
        expect(substr_count($page, $needle))->toBe(1, 'Onhost.dc.html: '.mb_substr($needle, 0, 100));
    }
});

it('serves the compute and managed-server pages with the SLA in force instead of invented figures', function (string $script) {
    config(['onhost.ui.demo' => false]);
    $prototype = (string) file_get_contents(base_path('apps/surfaces/'.$script));
    $served = (string) $this->get('/surfaces/'.$script)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->getContent();

    foreach (spnWithdrawn()[$script] as $claim) {
        expect(str_contains($prototype, $claim))->toBeTrue("the prototype of {$script} carried {$claim}")
            ->and(str_contains($served, $claim))->toBeFalse("{$script} still serves {$claim}");
    }
    foreach (spnStated()[$script] as $stated) {
        expect($served)->toContain($stated);
    }
    // the SLA figures that remain are those of SLA 2026-09: no 99,99 % in the bands and plans these needles covered
    expect(strlen($served))->toBeGreaterThan((int) (strlen($prototype) * 0.95));
})->with(['onhost-svc-compute.js', 'onhost-svc-dev.js']);

it('keeps the prototype copy of the managed-server page in demo mode', function () {
    config(['onhost.ui.demo' => true]);
    $response = $this->get('/surfaces/onhost-svc-dev.js')->assertOk();
    $served = $response->baseResponse instanceof BinaryFileResponse
        ? (string) file_get_contents($response->baseResponse->getFile()->getPathname())
        : (string) $response->getContent();

    expect($served)->toContain('Reakce do 15 minut')->toContain('SLA 99,99 % s kreditem');
});

it('gives every service page a reply channel and an ending the VOP back, not a ten-minute chat and a free trial', function () {
    $prototype = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    $live = app(SurfaceRenderer::class)->transform($prototype, 'public', false);

    foreach (['technik odpovídá do deseti minut', 'an engineer replies within ten minutes', 'Zkuste to 14 dní. Bez karty, bez závazku.', 'Try it for 14 days. No card, no commitment.',
        'zrušíte to jedním klikem a nic neplatíte. Migraci uděláme za vás.', 'cancel in one click and pay nothing. We handle the migration.'] as $claim) {
        expect($prototype)->toContain($claim)->and($live)->not->toContain($claim);
    }
    expect($live)->toContain("faqLead: cs ? 'Nenašli jste odpověď? Napište nám přes zákaznický panel nebo e-mailem.'")
        ->toContain('odstoupit do 14 dnů')->toContain("endTitle: p.endTitle || (cs ? 'Měsíčně nebo ročně, výpověď ke konci období.'");

    // every anchor hit exactly once, and demo mode keeps the prototype's words
    $report = collect(app(SurfaceRenderer::class)->anchorReport('public'))
        ->filter(fn (array $a) => str_contains($a['anchor'], 'faqLead: cs ?') || str_contains($a['anchor'], 'endTitle: p.endTitle') || str_contains($a['anchor'], 'endLead: p.endLead'));
    expect($report)->toHaveCount(3)->and($report->every(fn (array $a) => $a['hits'] === 1))->toBeTrue();
    expect(app(SurfaceRenderer::class)->transform($prototype, 'public', true))->toContain('technik odpovídá do deseti minut');
});

it('prices the game configurator as final for a non-payer and names no location or hours nobody documented', function () {
    $node = (new ExecutableFinder)->find('node');
    if ($node === null) {
        $this->markTestSkipped('node is not installed; the harness is tests/js/game-config-copy.harness.mjs');
    }
    $process = new Process([$node, base_path('tests/js/game-config-copy.harness.mjs')], base_path(), null, null, 60);
    $process->run();
    $out = json_decode($process->getOutput(), true);

    expect($out)->toBeArray('harness output: '.$process->getOutput().$process->getErrorOutput())
        ->and($out['failures'])->toBe([])
        ->and($process->getExitCode())->toBe(0)
        ->and($out['seen']['nonPayer.unit'])->toBe('/ měsíc')
        ->and($out['seen']['payer.unit'])->toBe('/ měsíc s DPH');
});

/*
 * O1 (phase O): what N1 left on the same pages. The compute SLA band still listed "2× N+1 power and cooling" and "10 Gbit/s as
 * standard"; the VPS page sold "dedicated cores, not oversold capacity" with "steal time under 0,5 %, measured publicly" (the
 * catalogue sells the VPS on cpu_class `shared`; dedicated cores are the VDS); the inference, vector database, storage and solution
 * pages promised "SLA 99,99 %" in plans and comparison tables the catalogue does not overlay (SLA 2026-09 has 99,99 % only "by
 * individual contract"); the home page and the sign-up form offered "the first 14 days free" (there is no trial, VOP čl. 2 bod 2);
 * the game configurator listed "Anti-DDoS included" — no node configuration, runbook or contract documents a mitigation.
 */

/** @return array<string, list<string>> script => what must not be served any more */
function o1Withdrawn(): array
{
    return [
        'onhost-svc-compute.js' => ["{ k: '2× N+1'", "v: _('konektivita v základu'", 'ne s přeprodaným výkonem', 'not oversold capacity', 'Steal time',
            'Nepřeprodáváme CPU', 'We do not oversell CPU', 'Jádra AMD EPYC vyhrazená jen vám', 'AMD EPYC cores reserved for you alone',
            "_('Vlastní domény endpointů', 'Custom endpoint domains'), 'SLA 99,99 %'", "_('GPU indexace', 'GPU indexing'), 'SLA 99,99 %'", "['SLA', '—', '99,95 %', '99,99 %']"],
        'onhost-svc-dev.js' => ["_('Export na disk poštou', 'Disk export by post'), _('SLA 99,99 %', '99.99% SLA')", "['SLA', '99,9 %', '99,95 %', '99,99 %']"],
        'onhost-svc-more.js' => ["{ k: '99,99 %', v: _('dostupnost s kreditem'", "_('SLA 99,99 %', '99.99% SLA')", "['99,99 %', _('dostupnost s pokutou'", 'SLA 99,99 % s pokutou',
            "['SLA', '99,9 %', '99,95 %', '99,99 %']", "['SLA', '99,9 %', '99,99 %', _('na míru'"],
    ];
}

/** @return array<string, list<string>> script => what the catalogue and SLA 2026-09 say instead */
function o1Stated(): array
{
    return [
        'onhost-svc-compute.js' => ["title: _('VPS na KVM s hodinovým účtováním a měsíčním stropem'", "_('Třída CPU podle tarifu', 'CPU class by plan')",
            "'SLA 99,95 %'", "['SLA', '—', '99,95 %', _('podle smlouvy', 'per contract')]", "_('SLA podle individuální smlouvy', 'SLA by individual contract')"],
        'onhost-svc-dev.js' => ["_('SLA podle individuální smlouvy', 'SLA by individual contract')", "['SLA', '99,9 %', '99,95 %', _('podle smlouvy', 'per contract')]"],
        'onhost-svc-more.js' => ["{ k: '99,9 %', v: _('měsíční dostupnost (Standard, SLA)'", "_('SLA podle individuální smlouvy', 'SLA by individual contract')",
            "['SLA', '99,9 %', '99,95 %', _('podle smlouvy', 'per contract')]", "['SLA', '99,9 %', _('podle smlouvy', 'per contract'), _('na míru', 'bespoke')]"],
    ];
}

it('O1: finds every needle exactly once in the byte-identical prototypes', function () {
    foreach (array_keys(o1Withdrawn()) as $script) {
        $source = (string) file_get_contents(base_path('apps/surfaces/'.$script));
        foreach (ServicePageClaimsSeam::anchorCounts($source, $script) as $needle => $hits) {
            expect($hits)->toBe(1, "{$script}: ".mb_substr($needle, 0, 100));
        }
    }
    $page = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    expect(ServicePageClaimsSeam::homeCopySwaps())->toHaveCount(6);
    foreach (array_keys(ServicePageClaimsSeam::homeCopySwaps()) as $needle) {
        expect(substr_count($page, $needle))->toBe(1, 'Onhost.dc.html: '.mb_substr($needle, 0, 100));
    }
});

it('O1: serves the compute, storage and solution pages without the undocumented figures', function (string $script) {
    config(['onhost.ui.demo' => false]);
    $prototype = (string) file_get_contents(base_path('apps/surfaces/'.$script));
    $served = (string) $this->get('/surfaces/'.$script)->assertOk()->getContent();

    foreach (o1Withdrawn()[$script] as $claim) {
        expect(str_contains($prototype, $claim))->toBeTrue("the prototype of {$script} carried {$claim}")
            ->and(str_contains($served, $claim))->toBeFalse("{$script} still serves {$claim}");
    }
    foreach (o1Stated()[$script] as $stated) {
        expect($served)->toContain($stated);
    }
    if ($script === 'onhost-svc-more.js') { // the solution pages have no catalogue overlay: no 99,99 % is left at all
        expect($served)->not->toContain('99,99')->not->toContain('99.99');
    }
})->with(['onhost-svc-compute.js', 'onhost-svc-dev.js', 'onhost-svc-more.js']);

it('O1: keeps the prototype copy of the compute page in demo mode', function () {
    config(['onhost.ui.demo' => true]);
    $response = $this->get('/surfaces/onhost-svc-compute.js')->assertOk();
    $served = $response->baseResponse instanceof BinaryFileResponse
        ? (string) file_get_contents($response->baseResponse->getFile()->getPathname())
        : (string) $response->getContent();

    expect($served)->toContain('ne s přeprodaným výkonem')->toContain("{ k: '2× N+1'");
});

it('O1: offers no free trial on the home page or the sign-up form', function () {
    $prototype = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    $live = app(SurfaceRenderer::class)->transform($prototype, 'public', false);

    foreach (['Prvních 14 dní zdarma', 'First 14 days free', 'začít 14 dní zdarma', 'start 14 days free', '14 dní zdarma, bez rizika', '14 days free, no risk',
        'zrušíte to jedním klikem a nic neplatíte.', 'cancel in one click and pay nothing.'] as $claim) {
        expect($prototype)->toContain($claim)->and($live)->not->toContain($claim);
    }
    expect($live)->toContain("authRegBtn: 'Vytvořit účet',")->toContain("authRegBtn: 'Create account',")
        ->toContain("ctaTitle: 'Měsíčně nebo ročně, výpověď ke konci období.'");

    $report = collect(app(SurfaceRenderer::class)->anchorReport('public'))
        ->filter(fn (array $a) => in_array($a['anchor'], array_keys(ServicePageClaimsSeam::homeCopySwaps()), true));
    expect($report)->toHaveCount(6)->and($report->every(fn (array $a) => $a['hits'] === 1))->toBeTrue();
    expect(app(SurfaceRenderer::class)->transform($prototype, 'public', true))->toContain('Prvních 14 dní zdarma');
});

it('leaves the prototypes byte-identical', function () {
    $status = shell_exec('git -C '.escapeshellarg(base_path()).' status --porcelain -- apps/surfaces '.escapeshellarg(':(exclude)apps/surfaces/api').' 2>&1');
    expect(trim((string) $status))->toBe(''); // a git error is output too (2>&1); the API bridges are code and may change
});
