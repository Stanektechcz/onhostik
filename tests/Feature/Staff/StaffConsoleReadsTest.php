<?php

declare(strict_types=1);

use App\Http\Navigation\StaffNavigation;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * K7-7 (L5, found by the K7 browser check): the staff console of a finance member of staff fired reads the role may not make —
 * domains, integrations, provisioning jobs, resource mappings, capacity — and the server answered 403 each time. The console's
 * own views already asked the navigation (`OnhostAdmin.apiAllowed`), but the shared modules `onhost-domains.api.js` and
 * `onhost-integrations.api.js` hydrate on load, before the console runs, without asking. `OnhostApi.reads()` (session bridge)
 * now answers from the server's navigation (`ONHOST.user.nav`, already narrowed to the person's permissions) and both modules ask
 * it before reading. tests/js/staff-console-reads.harness.mjs runs the real files in a vm with the navigation of each role.
 */

it('makes no read a finance member of staff is refused, and every read an owner may make', function () {
    $node = (new ExecutableFinder)->find('node');
    if ($node === null) {
        $this->markTestSkipped('node is not installed; the harness is tests/js/staff-console-reads.harness.mjs');
    }
    $navigation = app(StaffNavigation::class);
    $navs = [];
    foreach (['billing_finance_admin', 'platform_owner'] as $role) {
        $navs[$role] = $navigation->for($this->staff($role));
    }
    // the premise: finance may not read what K7 saw refused; the owner may
    $listed = fn (array $nav, string $path) => collect($nav)->flatMap(fn ($item) => $item['api'] ?? [])->contains(fn ($a) => ltrim((string) $a['path'], '/') === ltrim($path, '/') || ltrim((string) $a['path'], '/') === 'v1/'.ltrim($path, '/'));
    expect($listed($navs['billing_finance_admin'], 'staff/integrations'))->toBeFalse()
        ->and($listed($navs['billing_finance_admin'], 'staff/resource-mappings'))->toBeFalse()
        ->and($listed($navs['platform_owner'], 'staff/integrations'))->toBeTrue();

    $file = tempnam(sys_get_temp_dir(), 'navs');
    file_put_contents($file, json_encode($navs, JSON_UNESCAPED_SLASHES));
    try {
        $process = new Process([$node, base_path('tests/js/staff-console-reads.harness.mjs'), $file], base_path(), null, null, 120);
        $process->run();
    } finally {
        @unlink($file);
    }

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput());
    expect($process->getOutput())->toContain('7/7 passed');
});
