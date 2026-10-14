<?php

declare(strict_types=1);

use App\Http\Support\ServicePageClaimsSeam;
use App\Http\Support\SurfaceRenderer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Product;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * Phase P (owner 2026-10-13, ROZHODNUTI.md P-R37, P-R69, P-R70):
 *  - P1 / OP-1: support answers Mon–Fri 08:00–17:00 Europe/Prague through the panel and e-mail; the telephone is for an outage in
 *    working hours. No page may promise "24/7" support, a 30-minute or 10-minute reply, a measured median or a night-time NOC.
 *  - P2 / DD-1: the game servers' DDoS protection is not documented — 1,2 Tbps, "a profile for every game", "detection within three
 *    seconds", the badge, the menu and search rows, the edge-filtering card and the catalogue's `ddos` bullet are withdrawn.
 *  - P3 / DD-2: the VPS Geekbench figures (1 842 pts, "VPS with oversold cores") and colocation "2N power, N+1 cooling" are withdrawn.
 * The prototypes stay byte-identical (the seams correct the served copy outside demo mode); demo mode keeps the prototype copy.
 */

/** @return array<string, list<string>> script => what must not be served any more */
function phpWithdrawn(): array
{
    return [
        'onhost-svc-compute.js' => ['1 842 pts', '1 310 pts', 'VPS s přeprodanými jádry', 'VPS with oversold cores', '2N napájení', '2N power',
            'chlazením N+1', 'N+1 cooling', '2N UPS', '2N + generátor', '2N + generator', "['2N', _('napájení a UPS'"],
        'onhost-svc-dev.js' => ['zásahy 24/7', '24/7 hands-on', 'Zásahy 24/7'],
        'onhost-svc-other.js' => ['1,2 Tbps', 'do tří sekund', 'within three seconds', 'pro každou hru zvlášť', 'tuned per game', 'pro každý titul zvlášť', '300 Gbps',
            "_('detekce útoku'", 'Anti-DDoS ladený pro Minecraft', "_('Anti-DDoS profil'", "· Anti-DDoS'", 'reakce na P1 24/7', 'reakce P1, 24/7',
            'Telefon 24/7', 'medián první odpovědi', 'Ve tři ráno taky', 'odpověď do 10 minut', 'Odpověď do 30 minut', 'Medián osm minut', 'Průměr českého hostingu', 'včetně svátků'],
        'onhost-svc-more.js' => ['1,2 Tbps', 'reakce podpory 24/7', 'support response, 24/7', 'NOC 24/7', "'24/7', _('24/7 + inženýr'", 'Anti-DDoS laděný pro hru',
            'Detekce do tří sekund', 'Anti-DDoS základní', 'Anti-DDoS Pro s L7', "['Anti-DDoS', _('základní'", 'anti-DDoS profil laděný', 'a anti-DDoS Pro.'],
        'onhost-svc-web.js' => ['první reakce podpory (24/7)', 'first support response (24/7)', "{ k: '99,98 %', v: _('měřená dostupnost za 12 měsíců'", "'< 10 min'", 'datacentra v Praze, Brně, Varšavě'],
    ];
}

/** @return array<string, list<string>> script => what the decisions and the documents in force say instead */
function phpStated(): array
{
    return [
        'onhost-svc-compute.js' => ['Výkon VPS zveřejníme, až ho doložíme měřením.'],
        'onhost-svc-dev.js' => ["_('Updaty · monitoring · zásahy podle SLA'"],
        'onhost-svc-other.js' => ['po–pá 8:00–17:00', 'Parametry ochrany proti DDoS', "{ k: '4 h', v: _('reakce na incident (Standard)'"],
        'onhost-svc-more.js' => ["{ k: _('po–pá 8–17', 'Mon–Fri 8–17'), v: _('podpora přes panel a e-mail'", 'Parametry ochrany proti DDoS'],
        'onhost-svc-web.js' => ["{ k: '99,9 %', v: _('měsíční dostupnost (Standard, SLA)'", "{ k: _('po–pá 8–17', 'Mon–Fri 8–17')"],
    ];
}

/** The public surface's withdrawn and stated copy (Onhost.dc.html). @return array{0: list<string>, 1: list<string>} */
function phpPage(): array
{
    return [
        ['Anti-DDoS 1,2 Tbps', 'Anti-DDoS 1.2 Tbps', "'1,2 Tbps, WAF, 14 PoP'", 'Anti-DDoS pro hry', 'anti-DDoS v ceně', 'anti-DDoS included', 'Anti-DDoS a sloty v ceně',
            'Profily ladíme zvlášť pro každou hru', "'na hraně', 'Filtrujeme", 'chat 24/7', 'e-mail 24/7', "{ v: '24/7', l:", 'NOC 24/7 na pozadí', 'Zvedneme to do 15 minut',
            'Měřeno měsíčně z devíti sond', 'Nevyčerpaný kredit vracíme na požádání', 'Podpora 24/7', 'Support 24/7'],
        ["tbHours: 'Podpora po–pá 8–17 · panel a e-mail',", "tbHours: 'Support Mon–Fri 8–17 · panel and e-mail',", "gmBadge: 'Herní servery s vlastním panelem',",
            "['Kontakt a podpora', 'Po–pá 8–17, panel a e-mail']", "'Reagujeme podle SLA'", "['Ochrana proti DDoS', 'nedoloženo',"],
    ];
}

it('finds every needle and block marker exactly once in the byte-identical prototypes', function () {
    foreach (array_keys(phpWithdrawn()) as $script) {
        $source = (string) file_get_contents(base_path('apps/surfaces/'.$script));
        foreach (ServicePageClaimsSeam::anchorCounts($source, $script) as $needle => $hits) {
            expect($hits)->toBe(1, "{$script}: ".mb_substr($needle, 0, 100));
        }
        foreach (ServicePageClaimsSeam::blocks()[$script] ?? [] as [$from, $to]) {
            expect(substr_count($source, $from))->toBe(1, "{$script} block start")->and(str_contains($source, $to))->toBeTrue("{$script} block end");
        }
    }
    $page = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    foreach (array_keys(ServicePageClaimsSeam::phasePCopySwaps()) as $needle) {
        expect(substr_count($page, $needle))->toBe(1, 'Onhost.dc.html: '.mb_substr($needle, 0, 100));
    }
});

it('serves the service scripts without 24/7 support, DDoS figures, benchmarks or 2N power outside demo mode', function (string $script) {
    config(['onhost.ui.demo' => false]);
    $prototype = (string) file_get_contents(base_path('apps/surfaces/'.$script));
    $served = (string) $this->get('/surfaces/'.$script)->assertOk()->getContent();

    foreach (phpWithdrawn()[$script] as $claim) {
        expect(str_contains($prototype, $claim))->toBeTrue("the prototype of {$script} carried {$claim}")
            ->and(str_contains($served, $claim))->toBeFalse("{$script} still serves {$claim}");
    }
    foreach (phpStated()[$script] as $stated) {
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
})->with(['onhost-svc-compute.js', 'onhost-svc-dev.js', 'onhost-svc-other.js', 'onhost-svc-more.js', 'onhost-svc-web.js']);

it('keeps the prototype copy in demo mode', function () {
    config(['onhost.ui.demo' => true]);
    $response = $this->get('/surfaces/onhost-svc-other.js')->assertOk();
    $served = $response->baseResponse instanceof BinaryFileResponse
        ? (string) file_get_contents($response->baseResponse->getFile()->getPathname())
        : (string) $response->getContent();

    expect($served)->toContain('1,2 Tbps')->toContain('Telefon 24/7');
    $prototype = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    expect(app(SurfaceRenderer::class)->transform($prototype, 'public', true))->toContain('Anti-DDoS 1,2 Tbps');
});

it('says support hours and no DDoS figure on the public site outside demo mode', function () {
    $prototype = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    $live = app(SurfaceRenderer::class)->transform($prototype, 'public', false);
    [$withdrawn, $stated] = phpPage();

    foreach ($withdrawn as $claim) {
        expect($prototype)->toContain($claim)->and($live)->not->toContain($claim);
    }
    foreach ($stated as $text) {
        expect($live)->toContain($text);
    }
    $report = collect(app(SurfaceRenderer::class)->anchorReport('public'))
        ->filter(fn (array $a) => in_array($a['anchor'], array_keys(ServicePageClaimsSeam::phasePCopySwaps()), true));
    expect($report)->toHaveCount(count(ServicePageClaimsSeam::phasePCopySwaps()))->and($report->every(fn (array $a) => $a['hits'] === 1))->toBeTrue();
});

it('drops the undocumented ddos bullet from unheld game plan versions and only while the seeded words stand', function () {
    Artisan::call('db:seed', ['--class' => 'CatalogSeeder', '--force' => true]);
    $product = Product::query()->where('key', 'game')->firstOrFail();
    $versions = fn () => PlanVersion::query()->whereIn('plan_id', Plan::query()->where('product_id', $product->id)->pluck('id'))->orderBy('id')->get();

    // the fresh seed no longer writes it
    expect($versions()->every(fn (PlanVersion $v) => ! array_key_exists('ddos', (array) $v->entitlements)))->toBeTrue();

    // a database migrated before phase P: two versions carry the seeded words, one carries words staff wrote
    $all = $versions();
    $before = DB::table('plan_versions')->count();
    $seeded = $all->firstWhere(fn (PlanVersion $v) => Plan::query()->find($v->plan_id)?->key === 'game-8');
    $custom = $all->firstWhere(fn (PlanVersion $v) => Plan::query()->find($v->plan_id)?->key === 'game-16');
    $seeded->forceFill(['entitlements' => (array) $seeded->entitlements + ['ddos' => 'upstream L3/L4']])->save();
    $custom->forceFill(['entitlements' => (array) $custom->entitlements + ['ddos' => 'upstream L3/L4 (doloženo smlouvou)']])->save();

    $migration = require base_path('database/migrations/0001_01_01_001170_game_plans_without_unbacked_ddos.php');
    $migration->up();
    expect((array) $seeded->fresh()->entitlements)->not->toHaveKey('ddos')
        ->and(((array) $custom->fresh()->entitlements)['ddos'])->toBe('upstream L3/L4 (doloženo smlouvou)');

    $migration->down();
    expect(((array) $seeded->fresh()->entitlements)['ddos'])->toBe('upstream L3/L4');
    expect(DB::table('plan_versions')->count())->toBe($before); // nothing created or removed
});

it('leaves the prototypes byte-identical', function () {
    $status = shell_exec('git -C '.escapeshellarg(base_path()).' status --porcelain -- apps/surfaces '.escapeshellarg(':(exclude)apps/surfaces/api').' 2>&1');
    expect(trim((string) $status))->toBe('');
});
