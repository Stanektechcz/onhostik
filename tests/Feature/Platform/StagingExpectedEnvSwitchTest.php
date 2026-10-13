<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * Phase Q, Q4 (decision P-R78, staging protocol 2026-10-15 finding 1): staging.sh wrote ONHOST_TOKEN_ORGANIZATION_REQUIRED=false
 * into the deployer's expected-env, and `deploy.sh` refuses a deploy (rc 2) when app.env holds anything else — so rehearsing step 8
 * of the production plan on staging (ST-6, `true`) would have stopped every later deploy. The spec now accepts the switch on, off
 * or unset and nothing else. infra/aapanel/ci/expected-env-switches.sh runs the real `write_expected_env` against a staging-like
 * app.env (the deploy-scripts CI job runs it as well); this test runs the same script, with its own helpers (Pest helpers of another
 * file are not loaded when one file runs).
 */

function q4Bash(): ?string
{
    $configured = getenv('ONHOST_TEST_BASH');
    if (is_string($configured) && $configured !== '') {
        return $configured;
    }
    if (PHP_OS_FAMILY === 'Windows') { // never System32\bash.exe: that is WSL, with other paths and another PHP
        return is_file('C:\\Program Files\\Git\\bin\\bash.exe') ? 'C:\\Program Files\\Git\\bin\\bash.exe' : null;
    }

    return is_file('/bin/bash') ? '/bin/bash' : null;
}

function q4Posix(string $path): string
{
    if (PHP_OS_FAMILY !== 'Windows') {
        return $path;
    }
    $path = str_replace('\\', '/', $path);

    return preg_match('#^([A-Za-z]):/(.*)$#', $path, $m) === 1 ? '/'.strtolower($m[1]).'/'.$m[2] : $path;
}

it('lets the deployer spec accept the token switch on, off or unset and nothing else', function () {
    $bash = q4Bash();
    if ($bash === null) {
        getenv('CI') ? throw new RuntimeException('bash is required in CI') : test()->markTestSkipped('no bash');
    }
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'onhost-q4-'.uniqid();
    mkdir($dir);
    $process = new Process([$bash, q4Posix(base_path('infra/aapanel/ci/expected-env-switches.sh')), q4Posix($dir)], null, ['PHP_BIN' => q4Posix(PHP_BINARY)]);
    $process->setTimeout(120)->run();
    $out = $process->getOutput().$process->getErrorOutput();

    expect($process->getExitCode())->toBe(0, $out)
        ->and($out)->toContain('ok   token switch on (true, ST-6 / plan step 8) (rc 0)')
        ->toContain('ok   token switch off (false) (rc 0)')
        ->toContain('ok   token switch unset (code default off) (rc 0)')
        ->toContain('ok   token switch misspelt (yes) is refused (rc 13)')
        ->toContain('ok   token switch on, a pinned switch is still held (staff reach) (rc 13)')
        ->toContain('ok   token switch on, an outward key is still refused (rc 13)')
        ->not->toContain('lIvE-0123');
});

it('pins the token switch in the staging spec only to true or false', function () {
    $source = (string) file_get_contents(base_path('infra/aapanel/staging.sh'));
    expect(preg_match('/^write_expected_env\\(\\) \\{\\n.*?^EOF$/ms', $source, $m))->toBe(1)
        ->and($m[0])->toContain("\nONHOST_TOKEN_ORGANIZATION_REQUIRED~=^(true|false)?$\n")
        ->not->toContain("\nONHOST_TOKEN_ORGANIZATION_REQUIRED=false\n");
});
