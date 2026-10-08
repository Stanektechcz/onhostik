<?php

declare(strict_types=1);

use App\Http\Support\SurfaceRenderer;

/*
 * L-03 (docs/legal/LEGAL_REVIEW_2026-10.md): the public site states no fact the operator cannot document — six locations, own
 * hardware in Prague, partner TIER III facilities, 62 % / 100 % green energy, 2 400+ customers, a 30-minute support response,
 * ISO 27001 for part of the scope, a 48-hour migration without downtime, "7 of 24 H100 cards left", 99,99 % uptime. What replaces
 * them is what the contract in force says. The prototype stays byte-identical, demo mode keeps its copy.
 */
it('replaces every undocumented factual claim of the public site with what the contract says', function () {
    $prototype = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    $live = app(SurfaceRenderer::class)->transform($prototype, 'public', false);

    foreach (['zbývá 7 z 24 karet', '7 of 24 cards left', 'Šest lokalit', 'Six locations', 'Vlastní hardware v Praze', 'partnerských TIER III', "'62 % OZE'", 'eko energie', "'2 400+'", 'odpověď do 30 minut',
        'reply within 30 minutes', 'do 48 hodin a bez odstávky', 'within 48 hours with no downtime', 'Certifikaci máme na část rozsahu', '30 minut, člověk', "text: '99,99 %'", "{ v: '99,99 %'"] as $claim) {
        expect($live)->not->toContain($claim);
    }
    expect($live)->toContain("text: '99,9 %', label: cs ? 'smluvní dostupnost Standard'")->toContain("label: cs ? 'dní na odstoupení'")
        ->toContain("tbHours: 'Podpora přes panel a e-mail',")->toContain('chips: []');

    // every anchor of the seam hit the prototype as expected (a changed prototype is noticed, not silently skipped)
    $report = collect(app(SurfaceRenderer::class)->anchorReport('public'))->filter(fn (array $a) => str_contains($a['anchor'], 'Šest lokalit') || str_contains($a['anchor'], 'zbývá 7 z 24') || str_contains($a['anchor'], '{ to: 99.99'));
    expect($report)->not->toBeEmpty()->and($report->every(fn (array $a) => $a['hits'] >= 1))->toBeTrue();

    expect($prototype)->toContain('zbývá 7 z 24 karet')->and(app(SurfaceRenderer::class)->transform($prototype, 'public', true))->toContain('zbývá 7 z 24 karet');
});
