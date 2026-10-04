<?php

declare(strict_types=1);

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * Phase C close-out (TASK-0072): the customer ends or renews a subscription from the costs page and the billing area of the
 * service detail (POST /v1/subscriptions/{id}/cancel and /auto-renew, each behind the page's own confirmation), and on a closed
 * shared aaPanel node the Node projects that already run there can still be stopped or deleted (feature node_projects_exit).
 * The modules are plain browser scripts; tests/js/phase-c-closeout.harness.mjs runs the real files in a vm against a recording
 * OnhostApi and a fake DOM. This test runs it, and checks the files are served the way the panel loads them.
 */

it('serves the modules with the subscription and Node exit controls', function () {
    $served = fn (string $file) => (string) file_get_contents((string) $this->get('/surfaces/api/'.$file)->assertOk()->baseResponse->getFile());

    expect($served('onhost-panel-workbench.api.js'))->toContain('function subscriptionChange(cmp, sub, kind, after)')->toContain("'/cancel' : '/subscriptions/'");
    expect($served('onhost-panel-pages.api.js'))->toContain('function managePanel(cmp, _, s, cs)')->toContain("load(cmp, 'subs', '/subscriptions', null, true)");
    expect($served('onhost-panel-tools.api.js'))->toContain("ctx.on('node_projects_exit')")->toContain('function renewalPairs(ctx, pairs)');
});

it('runs the browser-side harness of the close-out screens', function () {
    $node = (new ExecutableFinder)->find('node');
    if ($node === null) {
        $this->markTestSkipped('node is not installed; the harness is tests/js/phase-c-closeout.harness.mjs');
    }
    $process = new Process([$node, base_path('tests/js/phase-c-closeout.harness.mjs')], base_path(), null, null, 120);
    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput());
    expect($process->getOutput())->toContain('11/11 passed');
});
