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

// W5-3: --format=json prints one verdict object with the same exit codes as the text format.
/**
 * @param  string|null  $junitXml  null = no report file, '' = a report of 0 bytes
 * @param  list<string>  $knownFailing
 * @param  list<string>  $extraArgs
 * @return array{0: int, 1: string, 2: string}
 */
function gc5Run(?string $junitXml, array $knownFailing, array $extraArgs): array
{
    $root = dirname(__DIR__, 2);
    $junit = (string) tempnam(sys_get_temp_dir(), 'w53-junit-');
    $baseline = (string) tempnam(sys_get_temp_dir(), 'w53-baseline-');
    if ($junitXml === null) {
        @unlink($junit);
    } else {
        file_put_contents($junit, $junitXml);
    }
    file_put_contents($baseline, (string) json_encode(['known_failing_tests' => $knownFailing]));

    $process = proc_open(
        array_merge([PHP_BINARY, $root.'/scripts/ci/gate-classify.php', '--junit='.$junit, '--baseline='.$baseline], $extraArgs),
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    @unlink($junit);
    @unlink($baseline);

    return [$code, $stdout, $stderr];
}

/**
 * @param  list<string>  $failedFiles
 */
function gc5Junit(array $failedFiles): string
{
    $cases = '<testcase name="passes" file="tests/Unit/PassingW53Test.php"/>';
    foreach ($failedFiles as $i => $file) {
        $cases .= '<testcase name="fails'.$i.'" file="'.htmlspecialchars($file).'"><failure>boom</failure></testcase>';
    }

    return '<?xml version="1.0"?><testsuites><testsuite name="w53">'.$cases.'</testsuite></testsuites>';
}

/**
 * @return array<string, mixed>
 */
function gc5Decode(string $stdout): array
{
    $decoded = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    expect($decoded)->toBeArray()->toHaveKeys(['verdict', 'regression', 'preexisting', 'evidence', 'pest_exit']);

    return (array) $decoded;
}

it('prints a JSON PASS when every failure is preexisting', function () {
    [$code, $stdout] = gc5Run(gc5Junit(['tests/Unit/OldW53Test.php']), ['tests/Unit/OldW53Test.php'], ['--format=json']);
    $json = gc5Decode($stdout);

    expect($code)->toBe(0)
        ->and($json['verdict'])->toBe('PASS')
        ->and($json['regression'])->toBe([])
        ->and($json['preexisting'])->toBe(['tests/Unit/OldW53Test.php'])
        ->and($json['evidence'])->toBe('junit')
        ->and($json['pest_exit'])->toBe(0);
});

it('prints a JSON FAIL on a regression', function () {
    [$code, $stdout] = gc5Run(gc5Junit(['tests/Unit/NewW53Test.php']), [], ['--format=json', '--pest-exit=1']);
    $json = gc5Decode($stdout);

    expect($code)->toBe(1)
        ->and($json['verdict'])->toBe('FAIL')
        ->and($json['regression'])->toBe(['tests/Unit/NewW53Test.php'])
        ->and($json['pest_exit'])->toBe(1);
});

it('fails a JUnit report of 0 bytes in JSON', function () {
    [$code, $stdout] = gc5Run('', [], ['--format=json']);
    $json = gc5Decode($stdout);

    expect($code)->toBe(1)
        ->and($json['verdict'])->toBe('FAIL')
        ->and($json['evidence'])->toBe('missing-junit');
});

it('fails pest exit 1 with a report that names no failure', function () {
    [$code, $stdout] = gc5Run(gc5Junit([]), [], ['--format=json', '--pest-exit=1']);
    $json = gc5Decode($stdout);

    expect($code)->toBe(1)
        ->and($json['verdict'])->toBe('FAIL')
        ->and($json['evidence'])->toBe('no-failure-named');
});

it('normalises a backslash path to a slash in JSON', function () {
    [$code, $stdout] = gc5Run(gc5Junit(['tests\\Unit\\NewW53Test.php']), [], ['--format=json']);
    $json = gc5Decode($stdout);

    expect($code)->toBe(1)
        ->and($json['regression'])->toBe(['tests/Unit/NewW53Test.php'])
        ->and($stdout)->not->toContain('\\');
});

it('keeps the text format byte-identical by default and with --format=text', function () {
    $expected = "REGRESSION  tests/Unit/NewW53Test.php\nPREEXISTING tests/Unit/OldW53Test.php\ngate-classify: 1 regression(s), 1 preexisting\n";
    $xml = gc5Junit(['tests/Unit/NewW53Test.php', 'tests/Unit/OldW53Test.php']);

    [$code, $stdout] = gc5Run($xml, ['tests/Unit/OldW53Test.php'], []);
    [$textCode, $textStdout] = gc5Run($xml, ['tests/Unit/OldW53Test.php'], ['--format=text']);

    expect($code)->toBe(1)->and($stdout)->toBe($expected)
        ->and($textCode)->toBe(1)->and($textStdout)->toBe($expected);
});

it('keeps stdout empty and exits 2 on a bad format', function () {
    [$code, $stdout, $stderr] = gc5Run(gc5Junit([]), [], ['--format=xml']);

    expect($code)->toBe(2)->and($stdout)->toBe('')->and($stderr)->toContain('usage:');
});
