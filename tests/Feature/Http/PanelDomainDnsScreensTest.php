<?php

declare(strict_types=1);

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * The panel's domain and DNS screens (TASK-0056): holder contact, transfer in, DNS versions + rollback + export, standalone zones.
 * The modules are plain browser scripts; tests/js/domains-dns-screens.harness.mjs runs the real files in a vm against a recording
 * OnhostApi and presses the buttons (22 checks). This test runs it, and checks the files are served the way the panel loads them.
 */

it('serves the domains module and the workbench that loads it', function () {
    $served = fn (string $file) => (string) file_get_contents((string) $this->get('/surfaces/api/'.$file)->assertOk()->baseResponse->getFile());

    $module = $served('onhost-panel-domains.api.js');
    expect($module)->toContain('window.OnhostPanelDomains')->toContain("'/holder'")->toContain('/domains/transfer-in')->toContain("'/dns/zones/'")->toContain('/rollback')->toContain('/export')->toContain('transferIn: false');
    $workbench = $served('onhost-panel-workbench.api.js');
    expect($workbench)->toContain('onhost-panel-domains.api.js')->toContain('OnhostPanelDomains');
});

it('keeps the legacy domains module on routes and field names the API has', function () {
    $legacy = (string) file_get_contents((string) $this->get('/surfaces/api/onhost-domains.api.js')->assertOk()->baseResponse->getFile());

    expect($legacy)->toContain('/dns/zones/')->toContain('change: p.op')->not->toContain('/zone/rollback')->not->toContain('{ changes: changes');
});

it('runs the browser-side harness of the domain and DNS screens', function () {
    $node = (new ExecutableFinder)->find('node');
    if ($node === null) {
        $this->markTestSkipped('node is not installed; the harness is tests/js/domains-dns-screens.harness.mjs');
    }
    $process = new Process([$node, base_path('tests/js/domains-dns-screens.harness.mjs')], base_path(), null, null, 120);
    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput());
    expect($process->getOutput())->toContain('22/22 passed');
});
