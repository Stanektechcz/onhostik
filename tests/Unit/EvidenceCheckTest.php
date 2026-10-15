<?php

declare(strict_types=1);

// N2: scripts/ci/evidence-check.php verifies `path:line` evidence against a mandatory revision.
function runEvidenceCheckN2(string $markdown, ?string $sha = 'HEAD'): array
{
    $root = dirname(__DIR__, 2);
    $report = tempnam(sys_get_temp_dir(), 'n2-evidence-').'.md';
    file_put_contents($report, $markdown);
    if ($sha === 'HEAD') {
        $sha = trim((string) shell_exec('git -C '.escapeshellarg($root).' rev-parse HEAD'));
    }
    $args = [PHP_BINARY, $root.'/scripts/ci/evidence-check.php'];
    if ($sha !== null) {
        $args[] = '--sha='.$sha;
    }
    $args[] = $report;
    $out = [];
    exec(implode(' ', array_map('escapeshellarg', $args)).' 2>&1', $out, $code);
    @unlink($report);

    return [$code, implode("\n", $out)];
}

it('passes when every cited file and line exists at the revision', function () {
    [$code, $out] = runEvidenceCheckN2("See `composer.json:1` and scripts/ci/evidence-check.php:2-3.\nhttps://example.com:443 is not evidence.");

    expect($code)->toBe(0)->and($out)->toContain('2 references, 0 missing');
});

it('fails on a missing file and on a line past the end of the file', function () {
    [$code, $out] = runEvidenceCheckN2("app/DoesNotExistN2.php:10 and composer.json:999999");

    expect($code)->toBe(1)
        ->and($out)->toContain('app/DoesNotExistN2.php:10 - file does not exist')
        ->and($out)->toContain('composer.json:999999 - composer.json has');
});

it('refuses to run without a revision or with an unknown one', function () {
    expect(runEvidenceCheckN2('composer.json:1', null)[0])->toBe(2)
        ->and(runEvidenceCheckN2('composer.json:1', 'deadbeefdeadbeef')[0])->toBe(2);
});
