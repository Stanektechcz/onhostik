<?php

declare(strict_types=1);

// W4-2: scripts/ci/gate-classify.php classifies JUnit failures against the baseline.
/**
 * @param  list<string>|null  $failedFiles  null = no JUnit report at all
 * @param  list<string>|null  $knownFailing  null = no baseline file
 * @return array{0: int, 1: string}
 */
function runGateClassifyW42(?array $failedFiles, ?array $knownFailing): array
{
    $root = dirname(__DIR__, 2);
    $junit = (string) tempnam(sys_get_temp_dir(), 'w42-junit-');
    $baseline = (string) tempnam(sys_get_temp_dir(), 'w42-baseline-');
    if ($failedFiles === null) {
        @unlink($junit);
    } else {
        $cases = '<testcase name="passes" file="tests/Unit/PassingW42Test.php"/>';
        foreach ($failedFiles as $i => $file) {
            $cases .= '<testcase name="fails'.$i.'" file="'.htmlspecialchars($file).'"><failure>boom</failure></testcase>';
        }
        file_put_contents($junit, '<?xml version="1.0"?><testsuites><testsuite name="w42">'.$cases.'</testsuite></testsuites>');
    }
    if ($knownFailing === null) {
        @unlink($baseline);
    } else {
        file_put_contents($baseline, (string) json_encode(['known_failing_tests' => $knownFailing]));
    }

    $process = proc_open(
        [PHP_BINARY, $root.'/scripts/ci/gate-classify.php', '--junit='.$junit, '--baseline='.$baseline],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );
    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    @unlink($junit);
    @unlink($baseline);

    return [$code, (string) $output];
}

it('returns 1 on a regression', function () {
    [$code, $out] = runGateClassifyW42(['tests/Unit/NewW42Test.php'], []);

    expect($code)->toBe(1)->and($out)->toContain('REGRESSION  tests/Unit/NewW42Test.php');
});

it('returns 0 when every failure is preexisting', function () {
    [$code, $out] = runGateClassifyW42(['tests/Unit/OldW42Test.php'], ['tests/Unit/OldW42Test.php']);

    expect($code)->toBe(0)->and($out)->toContain('PREEXISTING tests/Unit/OldW42Test.php');
});

it('returns 1 when the JUnit report is missing', function () {
    [$code, $out] = runGateClassifyW42(null, []);

    expect($code)->toBe(1)->and($out)->toContain('no JUnit report');
});

it('returns 2 when the baseline is missing', function () {
    [$code] = runGateClassifyW42([], null);

    expect($code)->toBe(2);
});

it('matches a backslash path with the slash key of the baseline', function () {
    [$code, $out] = runGateClassifyW42(['tests\\Unit\\OldW42Test.php '], ['tests/Unit/OldW42Test.php']);

    expect($code)->toBe(0)->and($out)->toContain('PREEXISTING tests/Unit/OldW42Test.php');
});
