<?php

declare(strict_types=1);

use App\Http\Support\PhaseRCopySeam;
use App\Http\Support\ServicePageClaimsSeam;
use App\Http\Support\SurfaceRenderer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * R1 (phase R, leftovers of Q1; P-R37, P-R69): the status page ("99,993 %", "3 incidenty", nine probe locations), the public
 * postmortems (46 200 Kč credited to 214 customers), the credit steps (automatic, cashable), the careers page (roles and salaries
 * nobody documented) and anycast DNS "in six locations" are served as the documents in force say (SLA 2026-09). The prototypes stay
 * byte-identical; demo mode keeps their copy.
 */

function prcScriptServed(object $test, string $script): string
{
    return (string) $test->get('/surfaces/'.$script)->assertOk()->getContent();
}

function prcNodeCheck(string $source): void
{
    $node = (new ExecutableFinder)->find('node');
    if ($node === null) {
        return;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'prc').'.js';
    file_put_contents($tmp, $source);
    $check = new Process([$node, '--check', $tmp]);
    $check->run();
    @unlink($tmp);
    expect($check->getExitCode())->toBe(0, $check->getErrorOutput());
}

it('finds every needle and block marker exactly once in the byte-identical prototypes', function () {
    foreach (PhaseRCopySeam::scriptSwaps() as $script => $swaps) {
        $source = (string) file_get_contents(base_path('apps/surfaces/'.$script));
        foreach (array_keys($swaps) as $needle) {
            expect(substr_count($source, $needle))->toBe(1, "{$script}: ".mb_substr($needle, 0, 100));
        }
    }
    foreach (PhaseRCopySeam::scriptBlocks() as $script => $blocks) {
        $source = (string) file_get_contents(base_path('apps/surfaces/'.$script));
        foreach ($blocks as [$from, $to]) {
            expect(substr_count($source, $from))->toBe(1, "{$script} block start")->and(substr_count($source, $to))->toBe(1, "{$script} block end");
        }
    }
    $page = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    foreach (array_keys(PhaseRCopySeam::publicSwaps()) as $needle) {
        expect(substr_count($page, $needle))->toBe(1, 'Onhost.dc.html: '.mb_substr($needle, 0, 100));
    }
    foreach (PhaseRCopySeam::publicBlocks() as [$from, $to]) {
        expect(substr_count($page, $from))->toBe(1, 'Onhost.dc.html block start')->and(substr_count($page, $to))->toBe(1, 'Onhost.dc.html block end');
    }
});

it('serves the status and careers pages as the SLA says, without invented figures', function () {
    config(['onhost.ui.demo' => false]);
    $prototype = (string) file_get_contents(base_path('apps/surfaces/onhost-svc-other.js'));
    $served = prcScriptServed($this, 'onhost-svc-other.js');

    foreach (['99,993 %', 'incidenty za 12 měsíců', 'z devíti evropských lokalit', 'Historie 24 měsíců', 'Monitoring vašich služeb', '55–75 tis. Kč', '90–140 tis. Kč',
        '50–70 tis. Kč', '81 %', 'Čtyřicet dva lidí', 'otevřené pozice'] as $claim) {
        expect(str_contains($prototype, $claim))->toBeTrue("the prototype carried {$claim}")->and(str_contains($served, $claim))->toBeFalse("still served: {$claim}");
    }
    expect($served)->toContain('Dostupnost se měří z nejméně tří nezávislých míst mimo naši síť')
        ->toContain('do pěti pracovních dnů shrnutí příčin a nápravných opatření')->toContain('Nárok uplatníte v zákaznickém panelu do 30 dnů')
        ->toContain('Žádnou pozici právě neinzerujeme.')
        // the pages around them are still there, once each
        ->and(substr_count($served, "    'about': C({"))->toBe(1)->and(substr_count($served, "    'support': C({"))->toBe(1)->and(substr_count($served, "    'status': C({"))->toBe(1)
        ->and(substr_count($served, "    'careers': C({"))->toBe(1);
    prcNodeCheck($served);
});

it('serves the DNS page without anycast in six locations or an unmeasured 9 ms', function () {
    config(['onhost.ui.demo' => false]);
    $served = prcScriptServed($this, 'onhost-svc-web.js');

    foreach (['Anycast v šesti lokalitách', 'anycast sítí v šesti lokalitách', 'Anycast v 6 lokalitách', 'Onhost anycast (Praha)', "['9 ms'", 'z deseti českých sítí, červen 2026'] as $claim) {
        expect(str_contains($served, $claim))->toBeFalse("still served: {$claim}");
    }
    expect($served)->toContain('Odezvu DNS zveřejníme, až ji doložíme měřením.')->toContain("kicker: _('420+ koncovek · DNS v ceně'");
    prcNodeCheck($served);
});

it('serves the public site without invented postmortems, credit steps, status figures or anycast DNS', function () {
    $prototype = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    $live = app(SurfaceRenderer::class)->transform($prototype, 'public', false);

    foreach (['46 200 Kč', '46,200 CZK', '214 zákazníkům', 'Degradace latence přes NIX.CZ', 'Plánovaná odstávka PRG1', 'Zavolejte v neděli ve dvě ráno', 'Čtyři zápisy z posledních dvanácti měsíců',
        'Spusťte server a zrušte ho do hodiny', 'A jde vybrat', 'Dostupnost měříme z devíti lokalit', "v: '99,98 %'", "v: cs ? 'do 15 min'", 'Devět lokalit, veřejné zápisy incidentů', 'Anycast, DNSSEC',
        'Kredit podle SLA vystavíme sami', 'kredit bez žádosti'] as $claim) {
        expect($prototype)->toContain($claim)->and($live)->not->toContain($claim);
    }
    expect($live)->toContain('postmortems: [],')->toContain('Nárok uplatníte v zákaznickém panelu do 30 dnů od konce měsíce')
        ->toContain("'DNSSEC, 420+ koncovek'")->toContain("pmKicker: cs ? 'Incidenty' : 'Incidents',");

    $anchors = collect(app(SurfaceRenderer::class)->anchorReport('public'));
    foreach (array_keys(PhaseRCopySeam::publicSwaps()) as $needle) {
        expect($anchors->firstWhere('anchor', $needle)['hits'] ?? null)->toBe(1, mb_substr($needle, 0, 80));
    }
    foreach (PhaseRCopySeam::publicBlocks() as [$from, $to]) {
        expect($anchors->firstWhere('anchor', $from.' … '.$to)['hits'] ?? null)->toBe(1);
    }
});

it('keeps the prototype copy in demo mode and the prototypes byte-identical', function () {
    config(['onhost.ui.demo' => true]);
    $response = $this->get('/surfaces/onhost-svc-other.js')->assertOk();
    $served = $response->baseResponse instanceof BinaryFileResponse
        ? (string) file_get_contents($response->baseResponse->getFile()->getPathname())
        : (string) $response->getContent();
    expect($served)->toContain('99,993 %')->toContain('55–75 tis. Kč');
    $prototype = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    expect(app(SurfaceRenderer::class)->transform($prototype, 'public', true))->toContain('46 200 Kč');
    expect(ServicePageClaimsSeam::covers('onhost-svc-web.js'))->toBeTrue();

    $status = shell_exec('git -C '.escapeshellarg(base_path()).' status --porcelain -- apps/surfaces '.escapeshellarg(':(exclude)apps/surfaces/api').' 2>&1');
    expect(trim((string) $status))->toBe('');
});
