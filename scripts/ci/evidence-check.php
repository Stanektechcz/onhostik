<?php

declare(strict_types=1);

/*
 * N2 (decision wave 2, 2026-10-15): checks that the `path:line` evidence cited in proposals and reports exists.
 * Every reference is resolved against one mandatory git revision, never against the working tree, so a rebase
 * after the report was written cannot raise false alarms.
 *
 * Usage: php scripts/ci/evidence-check.php --sha=<commit> <report.md> [<report.md> ...]
 * Exit:  0 all references exist, 1 a file or line is missing, 2 usage error or unknown revision.
 */

$sha = null;
$reports = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--sha=')) {
        $sha = substr($arg, 6);
    } else {
        $reports[] = $arg;
    }
}

if ($sha === null || ! preg_match('/^[0-9a-f]{7,40}$/i', $sha) || $reports === []) {
    fwrite(STDERR, "usage: php scripts/ci/evidence-check.php --sha=<commit> <report.md> [...]\n");
    exit(2);
}

$root = dirname(__DIR__, 2);
$git = static function (array $args) use ($root): array {
    $out = [];
    exec('git -C '.escapeshellarg($root).' '.implode(' ', array_map('escapeshellarg', $args)).' 2>&1', $out, $code);

    return [$code, $out];
};

[$code, $out] = $git(['rev-parse', '--verify', '--quiet', $sha.'^{commit}']);
if ($code !== 0 || $out === []) {
    fwrite(STDERR, "unknown revision: {$sha}\n");
    exit(2);
}
$commit = trim($out[0]);

// `path/to/File.php:12` or `File.php:12-34`; only source/doc extensions count, so `host.tld:443` is not evidence.
$extensions = 'php|md|yml|yaml|json|neon|xml|ps1|sh|js|mjs|ts|vue|css|html|sql|txt|toml|ini|env\.example';
$pattern = '~(?<![\w/.:-])((?:[\w.-]+/)*[\w.-]+\.(?:'.$extensions.')):(\d+)(?:-(\d+))?(?!\w)~';
$lineCounts = [];
$checked = 0;
$failures = [];

foreach ($reports as $report) {
    $text = @file_get_contents($report);
    if ($text === false) {
        fwrite(STDERR, "report not readable: {$report}\n");
        exit(2);
    }
    preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $path = preg_replace('~^\./~', '', $m[1]);
        $line = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : (int) $m[2];
        if (! array_key_exists($path, $lineCounts)) {
            [$c, $blob] = $git(['cat-file', '-p', $commit.':'.$path]);
            $lineCounts[$path] = $c === 0 ? count($blob) : null;
        }
        $checked++;
        if ($lineCounts[$path] === null) {
            $failures[] = "{$report}: {$m[0]} - file does not exist at {$sha}";
        } elseif ($line < 1 || $line > $lineCounts[$path]) {
            $failures[] = "{$report}: {$m[0]} - {$path} has {$lineCounts[$path]} lines at {$sha}";
        }
    }
}

$failures = array_values(array_unique($failures));
foreach ($failures as $failure) {
    fwrite(STDERR, $failure."\n");
}
echo sprintf("evidence-check: %d references, %d missing (revision %s)\n", $checked, count($failures), $sha);
exit($failures === [] ? 0 : 1);
