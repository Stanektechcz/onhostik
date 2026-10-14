<?php

declare(strict_types=1);

use App\Http\Support\PhaseRCopySeam;
use App\Http\Support\ServicePageClaimsSeam;
use App\Http\Support\SurfaceRenderer;
use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Incidents\Models\Incident;
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
    expect($served)->toContain('Odezvu DNS zveřejníme, až ji doložíme měřením.')->toContain("kicker: _('.cz .sk .eu .com · DNS v ceně'");
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
        ->toContain("'DNSSEC, .cz .sk .eu .com a další'")->toContain("pmKicker: cs ? 'Incidenty' : 'Incidents',");

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

/*
 * S2 (phase S, the rest of R1): the mail page's "4,2 mil. zpráv" sample and 99,7 % catch rate, the SSL page's "medián za červen 2026"
 * and 4-minute issuance, the CDN page (not sold, P-R46) with BGP anycast /24, 50 TB, a scrubbing centre and a 400 Gbps flood, "420+"
 * TLDs (the catalogue registers 10), the 55-second server and the rest of the invented #/jak-fungujeme (a seven-day trial without a
 * card, "not breached in twelve months", "two customers came back", an engineer answering at 3 a.m.), the PRG1 tours and the tender
 * rows' automatic credit and night-time reply. Served as the documents in force say (VOP, SLA 2026-09, DPA); prototypes byte-identical.
 */
it('serves the mail, SSL, domain and CDN pages without unmeasured figures', function () {
    config(['onhost.ui.demo' => false]);
    $prototype = (string) file_get_contents(base_path('apps/surfaces/onhost-svc-web.js'));
    $served = prcScriptServed($this, 'onhost-svc-web.js');

    foreach (['Vzorek 4,2 mil. zpráv', "['99,7 %'", '0,02 %', 'antispam s 99,7% záchytem', 'medián za červen 2026', "['4 min'", "note: '4 min'", 'BGP anycast /24', '50 TB',
        'Dedikované scrubbing centrum', '400 Gbps', 'ze čtrnácti PoP', 'Shield Max', "['420+'", '420+ koncovek', '420+ TLDs'] as $claim) {
        expect(str_contains($prototype, $claim))->toBeTrue("the prototype carried {$claim}")->and(str_contains($served, $claim))->toBeFalse("still served: {$claim}");
    }
    expect($served)->toContain('Míru záchytu spamu zveřejníme, až ji doložíme měřením.')->toContain('Dobu vystavení zveřejníme, až ji doložíme měřením.')
        ->toContain("kicker: _('CDN a WAF · připravujeme'")->toContain('.cz .sk .eu .com')
        ->and(substr_count($served, "    'cdn': P({"))->toBe(1)->and(substr_count($served, "    'backup': P({"))->toBe(1);
    prcNodeCheck($served);
});

it('serves the public site without the 55-second server, PRG1 tours, automatic credit or the invented rest of how we work', function () {
    $prototype = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    $live = app(SurfaceRenderer::class)->transform($prototype, 'public', false);

    foreach (['Server běží do 55 sekund', 'The server runs within 55 seconds', 'root do 55 sekund', 'Prohlídky PRG1', 'PRG1 tours', 'připisujeme ho sami, bez vaší žádosti',
        'posted by us without you asking', 'Reakci máme v minutách', 'Our response is in minutes', 'dva zákazníci, kteří od nás odešli', 'two customers who left',
        'Za dvanáct měsíců jsme prolomeni nebyli', 'We have not been breached in twelve months', 'Za tu cenu se nedá platit inženýr', 'That price cannot pay an engineer',
        'Začít bez karty', 'Bez závazku a bez karty', 'Praha, Brno, Frankfurt a peering', 'I konfigurace, ne jen data', 'nejméně šest měsíců předem',
        "state: cs ? 'v provozu' : 'operational', uptime"] as $claim) {
        expect($prototype)->toContain($claim)->and($live)->not->toContain($claim);
    }
    expect($live)->toContain('nejpozději do 48 hodin od zjištění')->toContain('do 5 pracovních dnů')->toContain('Zkušební období bez platby nenabízíme.')
        ->toContain('VOP čl. 5')->toContain('Standard do 4 hodin, Business do 1 hodiny')->toContain("'DNSSEC, .cz .sk .eu .com a další'")
        ->toContain('Lokality, ve kterých služby provozujeme, uvádí stránka Datacentra.')
        ->not->toContain('420+ koncovek')->not->toContain('420+ TLDs'); // the R1 menus said 420+, the catalogue registers 10
});

it('fills the 30-day status grid with the last 30 days, not 90 squeezed into 30 bars', function () {
    config(['onhost.ui.demo' => false]);
    Incident::query()->create(['number' => 'INC-S2-0001', 'title' => 'Výpadek', 'severity' => 'p1', 'state' => 'RESOLVED', 'visibility' => 'public',
        'components' => ['cloud-cz1'], 'affected_services' => [], 'started_at' => now()->subDays(60), 'detected_at' => now()->subDays(60), 'resolved_at' => now()->subDays(60)->addHours(2), 'sla_relevant' => true]);
    Cache::flush();

    $js = (string) $this->get('/surfaces/onhost-data.js')->assertOk()->getContent();
    expect(preg_match('/var D = (\{.*?\});\n/s', $js, $m))->toBe(1);
    $row = collect(json_decode($m[1], true, 512, JSON_THROW_ON_ERROR)['cs']['status'])->firstWhere('key', 'cloud-cz1');

    // an outage 60 days ago is outside the last 30 days: no red bar and full uptime for the 30 days the grid is labelled with
    expect($row['bad'])->toBe([])->and($row['u'])->toBe('100 %')->and($row)->toHaveKey('state');
});
