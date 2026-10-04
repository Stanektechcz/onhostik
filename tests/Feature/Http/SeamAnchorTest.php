<?php

declare(strict_types=1);

use App\Http\Support\SurfaceRenderer;

/*
 * Audit 2026-10 P1-8 (package C3): a seam of SurfaceRenderer is a text replacement on the byte-identical prototype. When the
 * prototype drifts under it, the replacement used to find nothing and the page quietly fell back to the narrated mock data.
 * The renderer now counts every anchor (each needle of an array replacement on its own); outside demo mode every anchor
 * meant to land once lands exactly once on its surface, so a drifted prototype fails CI here.
 */

/** @return list<string> human-readable anchors that missed their expectation */
function seamAnchorMisses(SurfaceRenderer $renderer, string $surface): array
{
    $misses = [];
    foreach ($renderer->anchorReport($surface) as $a) {
        $ok = match ($a['expect']) {
            'once' => $a['hits'] === 1,
            'many' => $a['hits'] >= 1,
            default => true,
        };
        if (! $ok) {
            $misses[] = "[{$surface}] {$a['hits']}× (expected {$a['expect']}): ".str_replace("\n", '\n', mb_substr($a['anchor'], 0, 140));
        }
    }

    return $misses;
}

it('lands every seam anchor exactly once on every surface outside demo mode', function (string $surface) {
    $renderer = app(SurfaceRenderer::class);
    $report = $renderer->anchorReport($surface);

    expect($report)->not->toBeEmpty()
        ->and(seamAnchorMisses($renderer, $surface))->toBe([]);
    if (! in_array($surface, ['mobile', 'widgets'], true)) { // the two concepts carry only the asset rewrites and the safety nets
        // the safety nets are the exception, not the rule: almost every anchor is a once-anchor
        expect(count(array_filter($report, fn ($a) => $a['expect'] === 'once')))->toBeGreaterThan(count(array_filter($report, fn ($a) => $a['expect'] !== 'once')));
    }
})->with(array_keys(SurfaceRenderer::SURFACES));

it('counts array replacements needle by needle', function () {
    $report = app(SurfaceRenderer::class)->anchorReport('public');
    $anchors = array_column($report, 'anchor');

    // three needles of one array str_replace block (the checkout ETA and the prefilled details), each reported on its own
    expect($anchors)->toContain("coEta: 'Server běží za ~90 sekund od zaplacení',")
        ->toContain("coEta: 'Your server runs ~90 seconds after payment',")
        ->toContain("cof: { email: '', name: '', ico: '', dic: '', terms: false }");
});

it('fails when the prototype drifted under a seam', function () {
    $root = sys_get_temp_dir().'/onhost-seam-drift-'.bin2hex(random_bytes(4));
    mkdir($root);
    try {
        $panel = (string) file_get_contents(base_path('apps/surfaces/Onhost-app.dc.html'));
        // a designer renames one variable inside an array replacement block and duplicates another anchor
        $drifted = str_replace('const ORDER_TYPES = [', 'const ORDER_KINDS = [', $panel, $renamed);
        $drifted = str_replace("    if (T('api')) sets.api = {", "    if (T('api')) sets.api = {\n    if (T('api')) sets.api = {", $drifted, $doubled);
        expect($renamed)->toBe(1)->and($doubled)->toBe(1);
        file_put_contents($root.'/Onhost-app.dc.html', $drifted);

        $misses = seamAnchorMisses(new SurfaceRenderer($root), 'panel');

        expect($misses)->toHaveCount(2)
            ->and($misses[0].' '.$misses[1])->toContain('0× (expected once): const ORDER_TYPES = [')->toContain("2× (expected once):     if (T('api')) sets.api = {");
    } finally {
        @unlink($root.'/Onhost-app.dc.html');
        @rmdir($root);
    }
});

it('keeps the anchor report out of normal rendering', function () {
    $renderer = app(SurfaceRenderer::class);
    $renderer->anchorReport('admin');

    // a later transform does not collect into a stale report, and the report of another surface starts empty
    $renderer->transform((string) file_get_contents(base_path('apps/surfaces/Onhost-partner.dc.html')), 'partner', false);
    $public = $renderer->anchorReport('public');

    expect(array_column($public, 'anchor'))->not->toContain("        [_('Odhlásit se', 'Sign out'), '', () => { this.setState({ userOpen: false }); this.pushLog('auth.logout',");
});
