<?php

declare(strict_types=1);

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * TASK-0070 (audit 2026-10 P1-6 leftovers, package C11): the cart, the game configurator, the panel's order wizard and its shop
 * multiplied by a literal 1.21. They read the rate the tax rules publish (ONHOST_DATA.vat() / ONHOST_PANEL.vat, TASK-0064); the
 * statutory 21 % is only the fallback when the platform has no active rule set. The harness runs the modules with made-up rates.
 */

it('prices the cart line and the order wizard with the published VAT rate, not a hard-coded 21 %', function () {
    $node = (new ExecutableFinder)->find('node');
    if ($node === null) {
        $this->markTestSkipped('node is not installed; the harness is tests/js/vat-from-data.harness.mjs');
    }
    $process = new Process([$node, base_path('tests/js/vat-from-data.harness.mjs')], base_path(), null, null, 60);
    $process->run();
    $out = json_decode($process->getOutput(), true);

    expect($out)->toBeArray('harness output: '.$process->getOutput().$process->getErrorOutput())
        ->and($out['failures'])->toBe([])
        ->and($process->getExitCode())->toBe(0)
        ->and($out['seen']['cart@0.1'])->toBe("s DPH 1\u{a0}100 Kč"); // cs-CZ groups thousands with a no-break space
});

it('reads the rate from the published data in every module that shows a price with VAT', function () {
    $sources = [
        'onhost-cart.api.js' => 'ONHOST_DATA',
        'onhost-game-config.api.js' => 'ONHOST_DATA',
        'onhost-panel-order.api.js' => 'ONHOST_PANEL',
        'onhost-panel-shop.api.js' => 'ONHOST_PANEL',
    ];
    foreach ($sources as $file => $global) {
        $js = (string) file_get_contents(base_path('apps/surfaces/api/'.$file));
        expect($js)->toContain('function vatRate()')->toContain($global)
            ->and(preg_match('~[*/]\s*1\.21\b~', $js))->toBe(0, "{$file} still multiplies by 1.21");
    }
});
