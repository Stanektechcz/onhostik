<?php

declare(strict_types=1);

// W3-7: scripts/ci/phpstan-baseline-ratchet.php caps both baseline entries and the sum of their counts.
function runBaselineRatchetW37(string $path): array
{
    $root = dirname(__DIR__, 2);
    $cmd = [PHP_BINARY, $root.'/scripts/ci/phpstan-baseline-ratchet.php', $path];
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($proc), $out];
}

function baselineFixtureW37(string $contents): string
{
    $file = tempnam(sys_get_temp_dir(), 'w37-baseline-');
    file_put_contents($file, $contents);

    return $file;
}

function realBaselineW37(): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/phpstan-baseline.neon');
}

it('passes on the committed phpstan baseline', function () {
    [$code, $out] = runBaselineRatchetW37(dirname(__DIR__, 2).'/phpstan-baseline.neon');

    expect($code)->toBe(0)->and($out)->toContain('phpstan baseline:');
});

it('fails on MAX_ERRORS when two entries are merged into one with a higher count', function () {
    $contents = realBaselineW37();
    preg_match_all('/^(\s*)count:\s*(\d+)\s*$/m', $contents, $m, PREG_OFFSET_CAPTURE);
    // Drop the last count line (one entry fewer) and fold its count plus one into the first entry.
    $last = count($m[0]) - 1;
    $merged = (int) $m[2][0][0] + (int) $m[2][$last][0] + 1;
    $contents = substr_replace($contents, '', $m[0][$last][1], strlen($m[0][$last][0]));
    $contents = substr_replace($contents, $m[1][0][0].'count: '.$merged, $m[0][0][1], strlen($m[0][0][0]));
    $file = baselineFixtureW37($contents);

    [$code, $out] = runBaselineRatchetW37($file);
    @unlink($file);

    expect($code)->toBe(1)
        ->and($out)->toContain('MAX_ERRORS exceeded')
        ->and($out)->not->toContain('MAX_ENTRIES exceeded');
});

it('fails on MAX_ENTRIES when an entry is added', function () {
    $file = baselineFixtureW37(realBaselineW37()."\n\t\t-\n\t\t\tmessage: '#^W3-7 fixture$#'\n\t\t\tcount: 0\n\t\t\tpath: app/W37.php\n");

    [$code, $out] = runBaselineRatchetW37($file);
    @unlink($file);

    expect($code)->toBe(1)->and($out)->toContain('MAX_ENTRIES exceeded');
});

it('exits with code 2 when the baseline file is missing', function () {
    [$code, $out] = runBaselineRatchetW37(sys_get_temp_dir().'/w37-missing-'.uniqid().'.neon');

    expect($code)->toBe(2)->and($out)->toContain('not found');
});
