<?php

declare(strict_types=1);

/*
 * F6 (panel decision 2026-10-15): the Larastan baseline is typing debt that may only shrink.
 * Fails when phpstan-baseline.neon holds more `count:` entries than the recorded ceiling.
 * After removing entries, lower MAX_ENTRIES in the same change so the ratchet keeps up.
 *
 * Usage: php scripts/ci/phpstan-baseline-ratchet.php [path-to-baseline]
 */

const MAX_ENTRIES = 636;

$path = $argv[1] ?? dirname(__DIR__, 2).'/phpstan-baseline.neon';
$contents = @file_get_contents($path);
if ($contents === false) {
    fwrite(STDERR, "phpstan baseline not found: {$path}\n");
    exit(2);
}

$entries = preg_match_all('/^\s*count:\s*\d+\s*$/m', $contents);

if ($entries > MAX_ENTRIES) {
    fwrite(STDERR, sprintf(
        "phpstan-baseline.neon has %d entries, the recorded ceiling is %d. New code must pass Larastan without new baseline entries.\n",
        $entries,
        MAX_ENTRIES,
    ));
    exit(1);
}

echo sprintf(
    "phpstan baseline: %d entries (ceiling %d)%s\n",
    $entries,
    MAX_ENTRIES,
    $entries < MAX_ENTRIES ? ' - lower MAX_ENTRIES in scripts/ci/phpstan-baseline-ratchet.php' : '',
);
exit(0);
