<?php

declare(strict_types=1);

/*
 * W4-2: classify failing tests of a Pest JUnit report against .ai/baseline/baseline.json.
 *
 *   php scripts/ci/gate-classify.php --junit=<file> --baseline=.ai/baseline/baseline.json
 *
 * Exit 0 = no regression, 1 = regression (a missing or empty JUnit report counts as one, like
 * scripts/ai/gate.ps1), 2 = the baseline is missing or invalid, or the arguments are wrong.
 */

function gateClassifyKey(string $value): string
{
    // Same as ConvertTo-TestKey in scripts/ai/gate.ps1.
    return trim(str_replace('\\', '/', $value));
}

/**
 * @param  list<string>  $failedKeys
 * @param  array<string, mixed>  $baseline
 * @return array{regression: list<string>, preexisting: list<string>}
 */
function gateClassify(array $failedKeys, array $baseline): array
{
    $known = [];
    foreach ((array) ($baseline['known_failing_tests'] ?? []) as $test) {
        if (is_string($test)) {
            $known[] = gateClassifyKey($test);
        }
    }

    $result = ['regression' => [], 'preexisting' => []];
    foreach ($failedKeys as $failed) {
        $key = gateClassifyKey($failed);
        $result[in_array($key, $known, true) ? 'preexisting' : 'regression'][] = $key;
    }

    return $result;
}

/**
 * @return list<string>|null null when the report is missing, empty or not XML
 */
function gateClassifyJunitFailures(string $path): ?array
{
    if (! is_file($path) || filesize($path) === 0) {
        return null;
    }
    $previous = libxml_use_internal_errors(true);
    $xml = simplexml_load_file($path);
    libxml_use_internal_errors($previous);
    if ($xml === false) {
        return null;
    }
    $failed = [];
    foreach ($xml->xpath('//testcase[failure or error]') ?: [] as $case) {
        $failed[] = gateClassifyKey((string) $case['file']);
    }

    return $failed;
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $options = getopt('', ['junit:', 'baseline:', 'pest-exit:']);
    $junit = is_string($options['junit'] ?? null) ? $options['junit'] : '';
    $baselinePath = is_string($options['baseline'] ?? null) ? $options['baseline'] : '';
    $pestExit = is_string($options['pest-exit'] ?? null) ? (int) $options['pest-exit'] : 0;
    if ($junit === '' || $baselinePath === '') {
        fwrite(STDERR, "usage: php scripts/ci/gate-classify.php --junit=<file> --baseline=<baseline.json> [--pest-exit=<code>]\n");
        exit(2);
    }

    $baseline = is_file($baselinePath) ? json_decode((string) file_get_contents($baselinePath), true) : null;
    if (! is_array($baseline)) {
        fwrite(STDERR, "gate-classify: baseline {$baselinePath} is missing or not valid JSON\n");
        exit(2);
    }

    $failed = gateClassifyJunitFailures($junit);
    if ($failed === null) {
        // No report means no evidence, whatever the exit code said: never let that pass.
        $failed = ["<pest produced no JUnit report (exit {$pestExit}); no test evidence>"];
    } elseif ($pestExit !== 0 && $failed === []) {
        $failed = ["<pest exited {$pestExit} but the report names no failing test>"];
    }

    $result = gateClassify(array_values(array_unique($failed)), $baseline);
    foreach ($result['regression'] as $key) {
        echo "REGRESSION  {$key}\n";
    }
    foreach ($result['preexisting'] as $key) {
        echo "PREEXISTING {$key}\n";
    }
    printf("gate-classify: %d regression(s), %d preexisting\n", count($result['regression']), count($result['preexisting']));
    exit($result['regression'] === [] ? 0 : 1);
}
