<?php

declare(strict_types=1);

/*
 * F6 (panel decision 2026-10-15): the Larastan baseline is typing debt that may only shrink.
 * Fails when phpstan-baseline.neon holds more `count:` entries than the recorded ceiling, or when
 * the sum of all `count: N` values (ignored errors) exceeds its ceiling (W3-7: merging entries
 * into one with a higher count must not hide new debt).
 * After removing entries or errors, lower MAX_ENTRIES and MAX_ERRORS in the same change.
 *
 * Usage: php scripts/ci/phpstan-baseline-ratchet.php [path-to-baseline]
 */

const MAX_ENTRIES = 636;
const MAX_ERRORS = 1007;

$path = $argv[1] ?? dirname(__DIR__, 2).'/phpstan-baseline.neon';
$contents = @file_get_contents($path);
if ($contents === false) {
    fwrite(STDERR, "phpstan baseline not found: {$path}\n");
    exit(2);
}

$entries = preg_match_all('/^\s*count:\s*(\d+)\s*$/m', $contents, $matches);
$errors = array_sum(array_map('intval', $matches[1]));

$failed = false;
if ($entries > MAX_ENTRIES) {
    fwrite(STDERR, sprintf(
        "MAX_ENTRIES exceeded: phpstan-baseline.neon has %d entries, the recorded ceiling is %d. New code must pass Larastan without new baseline entries.\n",
        $entries,
        MAX_ENTRIES,
    ));
    $failed = true;
}
if ($errors > MAX_ERRORS) {
    fwrite(STDERR, sprintf(
        "MAX_ERRORS exceeded: phpstan-baseline.neon ignores %d errors in total, the recorded ceiling is %d. New code must pass Larastan without raising baseline counts.\n",
        $errors,
        MAX_ERRORS,
    ));
    $failed = true;
}
if ($failed) {
    exit(1);
}

echo sprintf(
    "phpstan baseline: %d entries (ceiling %d), %d errors (ceiling %d)%s\n",
    $entries,
    MAX_ENTRIES,
    $errors,
    MAX_ERRORS,
    ($entries < MAX_ENTRIES || $errors < MAX_ERRORS)
        ? ' - lower both MAX_ENTRIES and MAX_ERRORS in scripts/ci/phpstan-baseline-ratchet.php in the same change'
        : '',
);
exit(0);
