<?php

declare(strict_types=1);

use App\Http\Support\PhaseTCopySeam;
use App\Http\Support\SurfaceRenderer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * T4 (phase T): the leftovers S2 found and left (`.ai/tasks/TASK-0211.md`, "Out of scope") — "Kde jsme dražší a proč" with "dražší o
 * 40 %", "290 Kč" and an engineer "at three in the morning", "1 400 lidí na Discordu", the mail page's ten-year archive and "Prague and
 * Brno", the SSL page's OV/EV products and insurance, the tender's NIS2 row with session recordings, the panel mock's "Telefon 24/7",
 * "SLA 99,99 %" on web, e-shop, database, VPS and dedicated plans, and "1 900 objednávek/h". Served as the documents in force say
 * (SLA 2026-09, support Mon–Fri 8–17 P-R37, no paid certificate on sale); prototypes byte-identical, demo mode keeps their copy.
 */

function ptcServed(object $test, string $script): string
{
    $response = $test->get('/surfaces/'.$script)->assertOk();

    return $response->baseResponse instanceof BinaryFileResponse
        ? (string) file_get_contents($response->baseResponse->getFile()->getPathname())
        : (string) $response->getContent();
}

function ptcNodeCheck(string $source, string $extension): void
{
    $node = (new ExecutableFinder)->find('node');
    if ($node === null) {
        return;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'ptc').$extension;
    file_put_contents($tmp, $source);
    $check = new Process([$node, '--check', $tmp]);
    $check->run();
    @unlink($tmp);
    expect($check->getExitCode())->toBe(0, $check->getErrorOutput());
}

function ptcLivePublic(): string
{
    return app(SurfaceRenderer::class)->transform((string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html')), 'public', false);
}

it('finds every script needle and block marker exactly once in the byte-identical prototypes', function () {
    foreach (PhaseTCopySeam::scriptSwaps() as $script => $swaps) {
        $source = (string) file_get_contents(base_path('apps/surfaces/'.$script));
        foreach (array_keys($swaps) as $needle) {
            expect(substr_count($source, $needle))->toBe(1, "{$script}: ".mb_substr($needle, 0, 100));
        }
    }
    foreach (PhaseTCopySeam::scriptBlocks() as $script => $blocks) {
        $source = (string) file_get_contents(base_path('apps/surfaces/'.$script));
        foreach ($blocks as [$from, $to]) {
            expect(substr_count($source, $from))->toBe(1, "{$script} block start")->and(substr_count($source, $to))->toBe(1, "{$script} block end");
        }
    }
});

it('serves the mail, SSL, WordPress, e-shop and database pages without the archive, OV/EV, 99,99 % or 1 900 orders an hour', function () {
    config(['onhost.ui.demo' => false]);
    $prototype = (string) file_get_contents(base_path('apps/surfaces/onhost-svc-web.js'));
    $served = ptcServed($this, 'onhost-svc-web.js');

    foreach (['Archiv 10 let', '10-year archive', 'eDiscovery', 'DLP', 'Archiv 3 roky', 'Archiv, který obstojí u soudu', 'v Praze a Brně', 'DV · OV · EV', 'pojištěním',
        'Pojištění', 'OV Business', 'Wildcard OV', 'Ověření firmy s námi', "'1 900'", '1 900+', '1 900 obj./h', 'Zátěžový test: WooCommerce', 'SLA 99,99 %', '99.99% SLA',
        "['SLA', '99,9 %', '99,95 %', '99,99 %']", 'dostupnost v HA', 'granularita PITR'] as $claim) {
        expect(str_contains($prototype, $claim))->toBeTrue("the prototype carried {$claim}")->and(str_contains($served, $claim))->toBeFalse("still served: {$claim}");
    }
    expect($served)->toContain("kicker: _('IMAP · DKIM · DMARC'")->toContain('Placené certifikáty OV a EV zatím neprodáváme.')
        ->toContain('Kapacitu e-shopu zveřejníme, až ji doloží zátěžový test.')->toContain("_('SLA podle smlouvy', 'SLA per contract')")
        ->toContain("['SLA', '99,9 %', '99,95 %', _('podle smlouvy', 'per contract')]")->toContain('uvádí stránka Datacentra')
        // what S2 put on the same pages is still there, and the SSL page still has its features
        ->toContain('Dobu vystavení zveřejníme, až ji doložíme měřením.')->toContain('Míru záchytu spamu zveřejníme, až ji doložíme měřením.')
        ->toContain("      feats: [\n        ['01', _('Obnova bez zásahu', 'Hands-off renewal'),")
        ->and(substr_count($served, "    'ssl': P({"))->toBe(1)->and(substr_count($served, "    'mail': P({"))->toBe(1);
    ptcNodeCheck($served, '.js');
});

it('serves the VPS, dedicated, IoT and developer pages without 99,99 % plans or data in Prague and Brno', function () {
    config(['onhost.ui.demo' => false]);
    $compute = ptcServed($this, 'onhost-svc-compute.js');
    expect($compute)->not->toContain("['SLA', '99,9 %', '99,95 %', '99,99 %']")->not->toContain("['SLA', '99,95 %', '99,99 %', '99,99 %']")
        ->toContain("['SLA', '99,95 %', _('podle smlouvy', 'per contract'), _('podle smlouvy', 'per contract')]");
    ptcNodeCheck($compute, '.js');
    foreach (['onhost-svc-more.js', 'onhost-svc-dev.js'] as $script) {
        $served = ptcServed($this, $script);
        expect($served)->not->toContain('V Praze a Brně')->not->toContain('In Prague and Brno')->toContain('uvádí stránka Datacentra');
        ptcNodeCheck($served, '.js');
    }
});

it('serves the public site without market comparisons, a Discord community, a 24/7 phone or session recordings', function () {
    $prototype = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    $live = ptcLivePublic();

    foreach (['Kde jsme dražší a proč', 'dražší o 40 %', '290 Kč', 've tři ráno', 'Where we cost more, and why', 'at three in the morning', '1 400 lidí na Discordu',
        '1,400 people on Discord', "_('Telefon 24/7', 'Phone, 24/7')", 'NOC Praha', 'P1 do 15 min', 'nahrávkou celé relace', 'full session recording', 'termín a nahrávku',
        'an expiry and a recording', 'Lokality Praha a Brno'] as $claim) {
        expect($prototype)->toContain($claim)->and($live)->not->toContain($claim);
    }
    expect($live)->toContain("dearTitle: cs ? 'Co v ceně je a co ne'")->toContain('Noční podporu nenabízíme.')->toContain("title: cs ? 'Návody a dokumentace'")
        ->toContain("meta: _('při výpadku v pracovní době', 'for an outage in working hours')")->toContain('zásah se zapíše do auditního záznamu')
        ->toContain("]).map(x => ({\n        item: x[0], tag: x[1], us: x[2], them: x[3], why: x[5],");

    $anchors = collect(app(SurfaceRenderer::class)->anchorReport('public'));
    foreach (array_keys(PhaseTCopySeam::publicSwaps()) as $needle) {
        expect($anchors->firstWhere('anchor', $needle)['hits'] ?? null)->toBe(1, mb_substr($needle, 0, 80));
    }
    foreach (PhaseTCopySeam::publicBlocks() as [$from, $to]) {
        expect($anchors->firstWhere('anchor', $from.' … '.$to)['hits'] ?? null)->toBe(1);
    }

    // the page's inline module (the data-dc-script block) still parses
    expect(preg_match_all('#<script type="text/x-dc" data-dc-script[^>]*>(.*?)</script>#s', $live, $modules))->toBeGreaterThan(0);
    foreach ($modules[1] as $module) {
        ptcNodeCheck($module, '.mjs');
    }
});

it('keeps the prototype copy in demo mode and the prototypes byte-identical', function () {
    config(['onhost.ui.demo' => true]);
    expect(ptcServed($this, 'onhost-svc-web.js'))->toContain('Archiv 10 let')->toContain('OV Business');
    $prototype = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    expect(app(SurfaceRenderer::class)->transform($prototype, 'public', true))->toContain('dražší o 40 %')->toContain('1 400 lidí na Discordu');

    $status = shell_exec('git -C '.escapeshellarg(base_path()).' status --porcelain -- apps/surfaces '.escapeshellarg(':(exclude)apps/surfaces/api').' 2>&1');
    expect(trim((string) $status))->toBe('');
});
