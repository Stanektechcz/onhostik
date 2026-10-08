<?php

declare(strict_types=1);

use App\Http\Support\SurfaceRenderer;

/*
 * TASK-0142 (docs/legal/LEGAL_REVIEW_2026-10.md L-01/L-02): the public site promises nothing the legal documents do not — no
 * 30-day money-back guarantee, no free trial, no top-up bonus, no certification or rating badge the operator cannot document —
 * and the checkout's consent line does not ask for a consent the contract does not need. The prototype stays byte-identical;
 * demo mode keeps its copy.
 */
it('replaces the public money-back, badge and bonus claims with what the terms say', function () {
    $prototype = (string) file_get_contents(base_path('apps/surfaces/Onhost.dc.html'));
    $live = app(SurfaceRenderer::class)->transform($prototype, 'public', false);

    foreach (['garance vrácení peněz 30 dní', 'Vrátíme celou částku', '30-day money-back guarantee', 'no pro-rating', 'Trustpilot', "'ISO 27001', 'TIER III", 'Cloudflare partner', 'Garance 30 dní', 'přidáme 10 % bonus', 'we add a 10% bonus', 'Souhlasím s VOP a zpracováním údajů.'] as $claim) {
        expect($live)->not->toContain($claim);
    }
    expect($live)->toContain("coRe1: '14 dní na odstoupení'")
        ->toContain('spotřebitel může do 14 dnů odstoupit od smlouvy')
        ->toContain('Kredit se nevyplácí a od dobití nelze odstoupit')
        ->toContain('Souhlasím s VOP a beru na vědomí zásady ochrany osobních údajů.');

    // the prototype itself is untouched and demo mode still shows its copy
    expect($prototype)->toContain('garance vrácení peněz 30 dní')
        ->and(app(SurfaceRenderer::class)->transform($prototype, 'public', true))->toContain('garance vrácení peněz 30 dní');
});
