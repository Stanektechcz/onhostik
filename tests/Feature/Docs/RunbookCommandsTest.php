<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/*
 * P17 (phase Q): a runbook is only as good as its commands. Every `php artisan onhost:…` / `operator:…` (also written `$P artisan …`
 * or with the runbooks' `art …` shell helper) in docs/runbooks must be a command this code base registers — a renamed or removed
 * command fails here, not at three in the morning on the production host (deploy-aapanel.md still named onhost:provisioning:jobs).
 */

/** @return array<string, list<string>> command => runbooks that use it */
function runbookCommandsUsed(): array
{
    $used = [];
    foreach (glob(base_path('docs/runbooks/*.md')) ?: [] as $file) {
        preg_match_all('/(?:\bartisan|\bart)\s+((?:onhost|operator):[a-z0-9][a-z0-9:-]*[a-z0-9])/', (string) file_get_contents($file), $m);
        foreach (array_unique($m[1]) as $command) {
            $used[$command][] = basename($file);
        }
    }
    ksort($used);

    return $used;
}

it('names only commands that exist in every runbook', function () {
    $used = runbookCommandsUsed();
    $registered = array_keys(Artisan::all());
    expect(count($used))->toBeGreaterThan(50); // the scan reads the runbooks, it is not vacuous

    $missing = [];
    foreach ($used as $command => $files) {
        if (! in_array($command, $registered, true)) {
            $missing[] = $command.' ('.implode(', ', $files).')';
        }
    }
    expect($missing)->toBe([], 'runbook commands that do not exist: '.implode('; ', $missing));
});

it('covers the day-two runbook', function () {
    $used = runbookCommandsUsed();
    foreach (['onhost:doctor', 'onhost:outbox:dead-letters', 'onhost:platform:backup:verify', 'onhost:integrations:versions', 'onhost:metering:preview', 'operator:tokens:unbound'] as $command) {
        expect($used[$command] ?? [])->toContain('day-2-operations.md');
    }
});
