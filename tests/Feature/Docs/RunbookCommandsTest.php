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

/*
 * P18 (phase R): the production plan and the documents the cutover follows name only scripts that are in the tree and switches the
 * code reads — a renamed script or an environment switch nobody reads would only show on the production host.
 */
it('names only scripts and environment switches that exist in the cutover documents', function () {
    $known = '';
    foreach (array_merge(glob(base_path('config/*.php')) ?: [], glob(base_path('infra/aapanel/*.sh')) ?: [], glob(base_path('infra/aapanel/*.php')) ?: [], [base_path('.env.example'), base_path('tests/WidthGuard.php')]) as $file) {
        $known .= is_file($file) ? (string) file_get_contents($file) : '';
    }
    $problems = [];
    $switches = 0;
    foreach (['production-plan-2026-10.md', 'go-live-checklist.md', 'first-day-production.md', 'release-and-rollback.md'] as $runbook) {
        $text = (string) file_get_contents(base_path('docs/runbooks/'.$runbook));
        preg_match_all('~\binfra/[A-Za-z0-9_./-]+\.(?:sh|php|yml|yaml|conf|json)\b~', $text, $paths);
        foreach (array_unique($paths[0]) as $path) {
            if (! file_exists(base_path($path))) {
                $problems[] = "{$runbook}: {$path} does not exist";
            }
        }
        preg_match_all('/\bONHOST_[A-Z0-9_]+\b/', $text, $names);
        foreach (array_unique($names[0]) as $name) {
            $switches++;
            if (! str_contains($known, $name)) {
                $problems[] = "{$runbook}: {$name} is read nowhere";
            }
        }
    }
    expect($switches)->toBeGreaterThan(5)->and($problems)->toBe([]);
    expect((string) file_get_contents(base_path('docs/runbooks/production-plan-2026-10.md')))
        ->toContain('restore-drill.sh')->toContain('ONHOST_PARTNERS_REFERRAL_COOKIE')->toContain('monitoring-setup.md')->toContain('aapanel-waf-log-privacy.md')->toContain('hsts.md');
});
