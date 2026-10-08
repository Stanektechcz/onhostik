<?php

declare(strict_types=1);

use Onhost\Providers\Penpot\PenpotDockerProvider;
use Symfony\Component\Process\Process;

/*
 * infra/penpot/provision-node.sh and verify-node.sh (I-R7, TASK-0141): what the scripts build on a Penpot node must be what the
 * adapter expects (paths, Docker majors), neither may hold a secret, and the input checks must refuse before anything changes.
 * Nothing here touches a server: the scripts run with --dry-run or only their argument checks.
 */

function penpotNodeScript(string $name): string
{
    return (string) file_get_contents(base_path('infra/penpot/'.$name));
}

function penpotNodeBash(): ?string
{
    $configured = getenv('ONHOST_TEST_BASH');
    if (is_string($configured) && $configured !== '') {
        return $configured;
    }
    if (PHP_OS_FAMILY === 'Windows') {
        return is_file('C:\\Program Files\\Git\\bin\\bash.exe') ? 'C:\\Program Files\\Git\\bin\\bash.exe' : null;
    }
    foreach (['/usr/bin/bash', '/bin/bash'] as $candidate) {
        if (is_executable($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function penpotNodePosix(string $path): string
{
    $path = str_replace('\\', '/', $path);

    return PHP_OS_FAMILY === 'Windows' && preg_match('#^([A-Za-z]):/(.*)$#', $path, $m) === 1 ? '/'.strtolower($m[1]).'/'.$m[2] : $path;
}

/** @return array{rc:int, out:string} */
function penpotNodeRun(string $script, array $args): array
{
    $process = new Process([penpotNodeBash(), $script, ...$args], base_path('infra/penpot'));
    $process->setTimeout(60);
    $process->run();

    return ['rc' => (int) $process->getExitCode(), 'out' => $process->getOutput().$process->getErrorOutput()];
}

it('builds on the node exactly the paths the Penpot adapter reads from its configuration', function () {
    $provision = penpotNodeScript('provision-node.sh');
    $verify = penpotNodeScript('verify-node.sh');

    foreach (['root' => 'STACK_ROOT', 'backup_root' => 'BACKUP_ROOT', 'proxy_sites' => 'PROXY_SITES'] as $key => $variable) {
        expect($provision)->toContain($variable.'="'.config('penpot.'.$key).'"')
            ->and($verify)->toContain($variable.'="'.config('penpot.'.$key).'"');
    }
    expect($provision)->toContain('import ${PROXY_SITES}/*.caddy')
        ->and(config('penpot.proxy_reload'))->toBe('systemctl reload caddy')
        ->and($provision)->toContain('/usr/bin/systemctl reload caddy');
});

it('installs only a Docker major the adapter was written for', function () {
    preg_match('/^DOCKER_MAJOR="(\d+)"/m', penpotNodeScript('provision-node.sh'), $m);

    expect(PenpotDockerProvider::supportedVendorVersions())->toContain('docker-'.($m[1] ?? 'none'));
});

it('never holds a secret and refuses a private key as input', function () {
    foreach (['provision-node.sh', 'verify-node.sh'] as $name) {
        $script = penpotNodeScript($name);
        expect($script)->not->toMatch('/-----BEGIN [A-Z ]*PRIVATE KEY-----/')
            ->and($script)->not->toMatch('/(password|secret|token)\s*=\s*["\'][^"\'$]+["\']/i');
    }
    expect(penpotNodeScript('provision-node.sh'))->toContain('PRIVATE key')->toContain('--ssh-pubkey-file');
});

it('has valid shell syntax', function () {
    $bash = penpotNodeBash();
    if ($bash === null) {
        $this->markTestSkipped('no bash available');
    }
    foreach (['provision-node.sh', 'verify-node.sh'] as $name) {
        $process = new Process([$bash, '-n', $name], base_path('infra/penpot'));
        $process->run();
        expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    }
});

it('refuses bad input before anything changes', function () {
    if (penpotNodeBash() === null) {
        $this->markTestSkipped('no bash available');
    }
    $dir = storage_path('framework/testing/penpot-node-'.bin2hex(random_bytes(4)));
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/private', "-----BEGIN OPENSSH PRIVATE KEY-----\nAAAA\n-----END OPENSSH PRIVATE KEY-----\n");
    file_put_contents($dir.'/public.pub', "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOnhostTestKeyOnhostTestKeyOnhostTestKey1 test\n");

    $private = penpotNodeRun('provision-node.sh', ['--dry-run', '--ssh-allow', '203.0.113.10', '--ssh-pubkey-file', penpotNodePosix($dir.'/private')]);
    $noAllow = penpotNodeRun('provision-node.sh', ['--dry-run', '--ssh-pubkey-file', penpotNodePosix($dir.'/public.pub')]);
    $root = penpotNodeRun('provision-node.sh', ['--dry-run', '--ssh-allow', '203.0.113.10', '--deploy-user', 'root', '--ssh-pubkey-file', penpotNodePosix($dir.'/public.pub')]);
    $injection = penpotNodeRun('provision-node.sh', ['--dry-run', '--ssh-allow', 'x;rm -rf /', '--ssh-pubkey-file', penpotNodePosix($dir.'/public.pub')]);
    $verify = penpotNodeRun('verify-node.sh', ['--instances', '0']);

    @unlink($dir.'/private');
    @unlink($dir.'/public.pub');
    @rmdir($dir);

    expect($private['rc'])->toBe(1)->and($private['out'])->toContain('PRIVATE key')
        ->and($noAllow['rc'])->toBe(1)->and($noAllow['out'])->toContain('--ssh-allow')
        ->and($root['rc'])->toBe(1)->and($root['out'])->toContain('must not be root')
        ->and($injection['rc'])->toBe(1)->and($injection['out'])->toContain('address or CIDR')
        ->and($verify['rc'])->toBe(2);
});
