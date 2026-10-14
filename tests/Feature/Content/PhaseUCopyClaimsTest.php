<?php

declare(strict_types=1);

use App\Http\Support\PhaseUCopySeam;
use App\Http\Support\SurfaceRenderer;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Catalog\CatalogRevisions;
use Onhost\Platform\Commands\CommandContext;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * U3 (phase U): the leftovers T4 found and left (`.ai/tasks/TASK-0215.md`, "Out of scope") — PITR on the managed-database page (owner
 * decision 2: not provided), the WordPress kpis "4 200" installs and "0" broken updates, the VPS "Neměřený přenos" (transfer is measured
 * against `traffic_tb` since phase T), "our own backbone Prague–Brno", the tender's "Enterprise do 30 minut telefonicky" (P-R37) and the
 * API changelog's invented history. Prototypes byte-identical, demo mode keeps their copy.
 */

function pucServed(object $test, string $script): string
{
    $response = $test->get('/surfaces/'.$script)->assertOk();

    return $response->baseResponse instanceof BinaryFileResponse
        ? (string) file_get_contents($response->baseResponse->getFile()->getPathname())
        : (string) $response->getContent();
}

function pucNodeCheck(string $source, string $extension): void
{
    $node = (new ExecutableFinder)->find('node');
    if ($node === null) {
        return;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'puc').$extension;
    file_put_contents($tmp, $source);
    $check = new Process([$node, '--check', $tmp]);
    $check->run();
    @unlink($tmp);
    expect($check->getExitCode())->toBe(0, $check->getErrorOutput());
}

function pucLivePublic(): string
{
    return app(SurfaceRenderer::class)->transform((string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html')), 'public', false);
}

/** @return array<string, mixed> the Czech page rows of /surfaces/onhost-data.js */
function pucPages(object $test): array
{
    $js = (string) $test->get('/surfaces/onhost-data.js')->assertOk()->getContent();
    preg_match('/var D = (\{.*\});\n  function L/s', $js, $m);

    return (array) (json_decode($m[1] ?? '{}', true)['cs']['pages'] ?? []);
}

it('finds every script needle and block marker exactly once in the byte-identical prototypes', function () {
    foreach (PhaseUCopySeam::scriptSwaps() as $script => $swaps) {
        $source = (string) file_get_contents(base_path('apps/surfaces/'.$script));
        foreach (array_keys($swaps) as $needle) {
            expect(substr_count($source, $needle))->toBe(1, "{$script}: ".mb_substr($needle, 0, 100));
        }
    }
    foreach (PhaseUCopySeam::scriptBlocks() as $script => $blocks) {
        $source = (string) file_get_contents(base_path('apps/surfaces/'.$script));
        foreach ($blocks as [$from, $to]) {
            expect(substr_count($source, $from))->toBe(1, "{$script} block start")->and(substr_count($source, $to))->toBe(1, "{$script} block end");
        }
    }
});

it('serves the database and WordPress pages without PITR or uncounted install figures, and the VPS cards without unmetered transfer', function () {
    config(['onhost.ui.demo' => false]);
    $web = pucServed($this, 'onhost-svc-web.js');
    $webPrototype = (string) file_get_contents(base_path('apps/surfaces/onhost-svc-web.js'));
    foreach (['point-in-time obnovou', "_('PITR', 'PITR')", 'PITR 7 dní', 'PITR 30 dní', 'PITR 90 dní', "['PITR', _('7 dní'", "_('Point-in-time obnova'", "['4 200'", 'rozbitých updatů v 2025', 'LCP po zapnutí cache'] as $claim) {
        expect(str_contains($webPrototype, $claim))->toBeTrue("the prototype carried {$claim}")->and(str_contains($web, $claim))->toBeFalse("still served: {$claim}");
    }
    expect($web)->toContain('Obnovu k libovolné sekundě (point-in-time) neposkytujeme.')->toContain("_('Zálohy podle tarifu', 'Backups per plan')")
        ->and(substr_count($web, "    'database': P({"))->toBe(1)->and(substr_count($web, "    'wordpress': P({"))->toBe(1);
    pucNodeCheck($web, '.js');

    $compute = pucServed($this, 'onhost-svc-compute.js');
    expect($compute)->not->toContain("_('Neměřený přenos', 'Unmetered traffic')")->and(substr_count($compute, "_('Přenos podle tarifu, bez škrcení', 'Transfer per plan, never throttled')"))->toBe(2);
    pucNodeCheck($compute, '.js');
});

it('lets the catalogue overlay the database and VPS plans live (what the customer sees), the copy above being for no-JS readers', function () {
    $this->seed([CatalogSeeder::class]);
    // a fresh catalogue's db-s/db-m version 1 still carries pitr_days until the operator applies the revision 2026-09-honest-promises
    // (owner decision 2; the doctor names it while pending) — after it, nothing the customer is sold says PITR
    expect(json_encode(pucPages($this)['database'] ?? [], JSON_UNESCAPED_UNICODE))->toContain('PITR');
    app(CatalogRevisions::class)->apply(null, CommandContext::system('test:u3-copy'));
    Cache::flush(); // onhost-data.js is cached for a minute
    $pages = pucPages($this);

    expect($pages['database']['plans'] ?? [])->not->toBe([])->and(json_encode($pages['database'], JSON_UNESCAPED_UNICODE))->not->toContain('PITR')
        ->and($pages['vps']['plans'] ?? [])->not->toBe([])->and(json_encode($pages['vps'], JSON_UNESCAPED_UNICODE))->not->toContain('Neměřený')
        ->and(array_key_exists('lead', $pages['database']))->toBeFalse(); // the lead is not overlaid: the seam above is what corrects it
});

it('serves the API changelog only from docs/api/CHANGELOG.md and never the SLA data the page does not read', function () {
    config(['onhost.ui.demo' => false]);
    $prototype = (string) file_get_contents(base_path('apps/surfaces/onhost-public.js'));
    $served = pucServed($this, 'onhost-public.js');
    foreach (['Zpřísnění limitu na exporty', 'noční zálohovací skript', 'Oznámeno 412 účtům', 'ne všem čtyřem tisícům'] as $claim) {
        expect($prototype)->toContain($claim)->and($served)->not->toContain($claim);
    }
    expect($served)->toContain("      changes: [],\n      rules: [\n")->toContain('Object.keys(D.api).forEach(function (k) { P.api[k] = D.api[k]; });');
    pucNodeCheck($served, '.js');

    // slaData() (99,99 % per service, "Vyplaceno 2026 318 400 Kč") is built but never rendered: the page reads PUB.api and PUB.hooks only
    $live = pucLivePublic();
    expect(substr_count($live, 'PUB.sla'))->toBe(0)->and(substr_count($live, 'PUB.api'))->toBeGreaterThan(0);
});

it('serves the public site without unmetered VPS transfer, an own backbone or a 30-minute Enterprise telephone line', function () {
    $prototype = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    $live = pucLivePublic();

    foreach (['Neměřený, 1 Gbit/s', 'Unmetered, 1 Gbit/s', 'Vlastní páteř v ČR', 'Our own backbone in Czechia', 'vlastními trasami'] as $claim) {
        expect($prototype)->toContain($claim)->and($live)->not->toContain($claim);
    }
    expect($live)->not->toContain('Enterprise do 30 minut telefonicky')->not->toContain('Enterprise within 30 minutes by phone')
        ->toContain('Enterprise podle individuální smlouvy')->toContain("v: cs ? 'Měsíční přenos podle tarifu' : 'Monthly transfer per plan'")
        ->toContain("['Síť v ČR', 'podle lokality',");

    $anchors = collect(app(SurfaceRenderer::class)->anchorReport('public'));
    foreach (array_keys(PhaseUCopySeam::publicSwaps()) as $needle) {
        expect($anchors->firstWhere('anchor', $needle)['hits'] ?? null)->toBe(1, mb_substr($needle, 0, 80));
    }
    expect(preg_match_all('#<script type="text/x-dc" data-dc-script[^>]*>(.*?)</script>#s', $live, $modules))->toBeGreaterThan(0);
    foreach ($modules[1] as $module) {
        pucNodeCheck($module, '.mjs');
    }
});

it('keeps the prototype copy in demo mode and the prototypes byte-identical', function () {
    config(['onhost.ui.demo' => true]);
    expect(pucServed($this, 'onhost-svc-web.js'))->toContain('PITR 7 dní')->toContain('rozbitých updatů v 2025');
    $prototype = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    expect(app(SurfaceRenderer::class)->transform($prototype, 'public', true))->toContain('Vlastní páteř v ČR')->toContain('Neměřený, 1 Gbit/s');

    $status = shell_exec('git -C '.escapeshellarg(base_path()).' status --porcelain -- apps/surfaces '.escapeshellarg(':(exclude)apps/surfaces/api').' 2>&1');
    expect(trim((string) $status))->toBe('');
});
