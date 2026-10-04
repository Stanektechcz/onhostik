<?php

declare(strict_types=1);

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * The web and mail screens use the backend that exists (TASK-0069, phase C5/C6): the destructive preview instead of
 * window.confirm, the service check, the settings document, SSH keys, the mail sending switch, the mailbox password link,
 * the reasons a feature is off, and projects/pages that read every page and fail out loud. The modules are plain browser
 * scripts; tests/js/webmail-screens.harness.mjs runs the real files in a vm against a recording OnhostApi and a fake DOM.
 * This test runs it, and checks the files are served the way the panel loads them.
 */

it('serves the modules with the backend calls they now use', function () {
    $served = fn (string $file) => (string) file_get_contents((string) $this->get('/surfaces/api/'.$file)->assertOk()->baseResponse->getFile());

    $workbench = $served('onhost-panel-workbench.api.js');
    expect($workbench)->toContain("'/actions/' + action + '/preview'")->toContain('confirm: p.fingerprint')->toContain('/mailbox-password-link')->toContain('function dialog(cmp, o)')
        ->toContain('function destructive(cmp, sel, action, params, o)')->toContain('function unavailableReason(_, why)');
    $tools = $served('onhost-panel-tools.api.js');
    expect($tools)->toContain("'/health'")->toContain("'/spec'")->toContain("'/ssh-keys'")->toContain("'sending.set'")->toContain('function nocPanel(ctx, core)');
    expect($served('onhost-panel-projects.api.js'))->toContain("A().all('/services')")->not->toContain('limit=200');
    expect($served('onhost-panel-pages.api.js'))->toContain("A().all('/services')")->not->toContain('limit=200');
});

it('runs the browser-side harness of the web and mail screens', function () {
    $node = (new ExecutableFinder)->find('node');
    if ($node === null) {
        $this->markTestSkipped('node is not installed; the harness is tests/js/webmail-screens.harness.mjs');
    }
    $process = new Process([$node, base_path('tests/js/webmail-screens.harness.mjs')], base_path(), null, null, 120);
    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput());
    expect($process->getOutput())->toContain('24/24 passed');
});
