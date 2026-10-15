<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/*
 * N1 (wave 2): every `artisan onhost:…` that docs/ or app/ promises (runbooks, guides, error messages, remedies, help texts)
 * must be a command this code base registers. RunbookCommandsTest covers docs/runbooks only; this one covers the whole of
 * docs/ and app/, so a remedy pointing at a renamed or never-built command fails here (the A1/D4 class of mistake).
 *
 * A command that is promised before it exists goes on the allowlist with the task that will build it and an expiry date;
 * an expired entry fails, so the allowlist cannot quietly become permanent.
 */

/** @return array<string, array{task: string, expires: string}> command => owning task and expiry (Y-m-d) */
function promisedCommandsAllowlist(): array
{
    return [
        // 'onhost:example:command' => ['task' => 'TASK-0000', 'expires' => '2026-12-31'],
    ];
}

/** @return array<string, list<string>> command => files (relative to base_path) that promise it */
function promisedCommandsUsed(): array
{
    $used = [];
    foreach (['docs', 'app'] as $root) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($root), FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! in_array($file->getExtension(), ['md', 'php', 'txt', 'yml', 'yaml', 'json'], true)) {
                continue;
            }
            preg_match_all('/\bartisan\s+(onhost:[a-z0-9][a-z0-9:-]*[a-z0-9])/', (string) file_get_contents($file->getPathname()), $m);
            foreach (array_unique($m[1]) as $command) {
                $used[$command][] = ltrim(str_replace([base_path(), '\\'], ['', '/'], $file->getPathname()), '/');
            }
        }
    }
    ksort($used);

    return $used;
}

it('promises only registered commands in docs and app', function () {
    $used = promisedCommandsUsed();
    $registered = array_keys(Artisan::all());
    $allowed = promisedCommandsAllowlist();
    expect(count($used))->toBeGreaterThan(50); // the scan reads the tree, it is not vacuous

    $missing = [];
    foreach ($used as $command => $files) {
        if (! in_array($command, $registered, true) && ! isset($allowed[$command])) {
            $missing[] = $command.' ('.implode(', ', array_slice($files, 0, 3)).')';
        }
    }
    expect($missing)->toBe([], 'promised commands that do not exist: '.implode('; ', $missing));
});

it('keeps the promised commands allowlist owned, unexpired and necessary', function () {
    $registered = array_keys(Artisan::all());
    $today = now()->toDateString();
    $problems = [];
    foreach (promisedCommandsAllowlist() as $command => $entry) {
        if (preg_match('/^TASK-\d{4}$/', $entry['task'] ?? '') !== 1) {
            $problems[] = "{$command}: no owning TASK-NNNN";
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry['expires'] ?? '') !== 1 || $entry['expires'] < $today) {
            $problems[] = "{$command}: expiry missing or passed";
        }
        if (in_array($command, $registered, true)) {
            $problems[] = "{$command}: now registered, remove it from the allowlist";
        }
    }
    expect($problems)->toBe([]);
});
