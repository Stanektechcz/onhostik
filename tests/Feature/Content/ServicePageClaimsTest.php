<?php

declare(strict_types=1);

use App\Http\Support\ServicePageClaimsSeam;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/*
 * Owner decision J-R2 (K8b, follow-up 1b of J-R1): the service pages drawn by onhost-svc-compute/corp/other/more.js stop showing the
 * invented eco figures (PUE 1,18 and its comparison bars, 62 % renewables, 2,4 GWh / 1 640 MWh of heat, 164 k m³ of gas, 840 kWp,
 * 2 MWh of batteries, a 90 % target, 214 days of free cooling, "two buildings") and the EPYC build and stock claims (EPYC 9354,
 * "in stock within 24 hours", "delivery from stock") until the owner documents real ones. The scripts stay byte-identical; the
 * served copy is corrected outside demo mode.
 */

/** @return array<string, list<string>> script => what must not be served any more */
function spcWithdrawn(): array
{
    return [
        'onhost-svc-compute.js' => ['7 / 24', 'volných karet H100', 'H100 cards free', 'H100 v Praze. Vaše data', 'EPYC 9354', 'skladem do 24 hodin', 'in stock within 24 hours', 'dodání ze skladu', 'delivery from stock', 'Dodání do 24 h', 'Delivered within 24 h', 'PUE 1,18', "'1,18'", 'PUE 1,55', 'PUE 2,4', "'62 %'"],
        'onhost-svc-corp.js' => ["'1,18'", '~1,55', '~1,9', '214 dní', '214 days', "'2 budovy'", "'2 buildings'", 'dvě budovy', 'two neighbouring buildings', 'průměrné PUE v Praze', 'Roční průměr PUE za 2025'],
        'onhost-svc-other.js' => ['1,18', "'62 %'", '2,4 GWh', '2.4 GWh', '1 640 MWh', "'164 '", '840 kWp', "'2 MWh'", "'90 %'", 'dvě sousední budovy', 'two neighbouring buildings', 'PUE 1,40', 'PUE 2,40'],
        'onhost-svc-more.js' => ['dvě sousední budovy', 'two neighbouring Prague buildings'],
    ];
}

it('finds every needle exactly once in the byte-identical prototype scripts', function () {
    foreach (array_keys(ServicePageClaimsSeam::replacements()) as $script) {
        $source = (string) file_get_contents(base_path('apps/surfaces/'.$script));
        foreach (ServicePageClaimsSeam::anchorCounts($source, $script) as $needle => $hits) {
            expect($hits)->toBe(1, "{$script}: ".mb_substr($needle, 0, 100));
        }
    }
});

it('serves the service scripts without the withdrawn figures outside demo mode', function (string $script) {
    config(['onhost.ui.demo' => false]);
    $prototype = (string) file_get_contents(base_path('apps/surfaces/'.$script));
    $response = $this->get('/surfaces/'.$script)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $served = (string) $response->getContent();

    expect($response->headers->get('Content-Type'))->toContain('javascript');
    foreach (spcWithdrawn()[$script] as $claim) {
        expect(str_contains($prototype, $claim))->toBeTrue("the prototype of {$script} carried {$claim}")
            ->and(str_contains($served, $claim))->toBeFalse("{$script} still serves {$claim}");
    }
    // nothing else of the page changed: the rest of the script is the prototype, line for line, minus the withdrawn parts
    expect(strlen($served))->toBeLessThan(strlen($prototype))->toBeGreaterThan((int) (strlen($prototype) * 0.95));
})->with(['onhost-svc-compute.js', 'onhost-svc-corp.js', 'onhost-svc-other.js', 'onhost-svc-more.js']);

it('keeps the prototype copy in demo mode and leaves the prototype files untouched', function () {
    config(['onhost.ui.demo' => true]);
    $response = $this->get('/surfaces/onhost-svc-other.js')->assertOk();
    $served = $response->baseResponse instanceof BinaryFileResponse
        ? (string) file_get_contents($response->baseResponse->getFile()->getPathname())
        : (string) $response->getContent();

    expect($served)->toContain('2,4 GWh')->toContain('PUE 1,18');
    // the prototypes stay byte-identical (the seam works on the served copy only)
    expect(trim((string) shell_exec('git -C '.escapeshellarg(base_path()).' status --porcelain -- apps/surfaces')))->toBe('');
});

it('says honestly that PUE is not published yet instead of drawing an empty chart without a word', function () {
    config(['onhost.ui.demo' => false]);
    foreach (['onhost-svc-compute.js', 'onhost-svc-corp.js', 'onhost-svc-other.js'] as $script) {
        expect((string) $this->get('/surfaces/'.$script)->getContent())->toContain('Účinnost (PUE) zveřejníme, až ji doložíme měřením.');
    }
});
