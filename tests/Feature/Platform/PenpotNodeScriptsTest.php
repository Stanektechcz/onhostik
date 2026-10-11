<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
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

/*
 * TASK-0150: a full --dry-run against stand-ins for the host's tools (systemctl, docker, ss, dpkg, gpg) and a fake /etc
 * (ONHOST_NODE_SYSROOT, honoured only with --dry-run), so the decisions the script takes on a fresh node and on a host that
 * already runs Docker (a Wings game node) are proven without a server.
 *
 * @param  array{docker_active?:bool, docker_version?:string, listen?:list<int>, installed?:list<string>, files?:array<string,string>}  $host
 * @return array{rc:int, out:string}
 */
function penpotNodeDryRun(array $host, array $args): array
{
    $dir = storage_path('framework/testing/penpot-dry-'.bin2hex(random_bytes(4)));
    $bin = $dir.'/bin';
    $sysroot = $dir.'/root';
    mkdir($bin, 0777, true);
    mkdir($sysroot, 0777, true);
    $active = ($host['docker_active'] ?? false) ? 1 : 0;
    $installed = implode(' ', $host['installed'] ?? []);
    $version = $host['docker_version'] ?? '28.1.1';
    $listen = implode("\n", array_map(fn (int $p) => "LISTEN 0 511 0.0.0.0:{$p} 0.0.0.0:*", $host['listen'] ?? []));
    $stubs = [
        'systemctl' => "#!/bin/sh\ncase \"\$*\" in *is-active*docker*) exit \$(( 1 - {$active} ));; *is-active*) exit 3;; esac\nexit 0\n",
        'docker' => "#!/bin/sh\n[ {$active} = 1 ] || exit 1\ncase \"\$*\" in 'version --format'*) echo '{$version}';; 'compose version'*) echo 2.29.7;; 'ps -q'*) echo 3f2a9c1b;; esac\nexit 0\n",
        'ss' => "#!/bin/sh\ncat <<'L'\n{$listen}\nL\n",
        'dpkg' => "#!/bin/sh\ncase \"\$1\" in --print-architecture) echo amd64; exit 0;; -s) for p in {$installed}; do [ \"\$p\" = \"\$2\" ] && exit 0; done; exit 1;; esac\nexit 1\n",
        'dpkg-query' => "#!/bin/sh\necho '5:{$version}-1~debian.12~bookworm'\n",
        'gpg' => "#!/bin/sh\nexit 1\n",
    ];
    foreach ($stubs as $name => $body) {
        file_put_contents($bin.'/'.$name, $body);
        chmod($bin.'/'.$name, 0755);
    }
    foreach ($host['files'] ?? [] as $path => $content) {
        @mkdir(dirname($sysroot.$path), 0777, true);
        file_put_contents($sysroot.$path, $content);
    }
    $key = 'ssh-ed25519 '.base64_encode(pack('N', 11).'ssh-ed25519'.pack('N', 32).str_repeat("\x11", 32)).' onhost-test';
    file_put_contents($dir.'/deploy.pub', $key."\n");

    $path = PHP_OS_FAMILY === 'Windows' ? penpotNodePosix($bin).':/usr/bin:/bin:/mingw64/bin' : $bin.':'.(getenv('PATH') ?: '/usr/bin:/bin');
    $process = new Process([penpotNodeBash(), '-c', 'export PATH="$1"; shift; exec bash "$@"', 'penpot-dry', $path, 'provision-node.sh', '--dry-run', '--ssh-pubkey-file', penpotNodePosix($dir.'/deploy.pub'), '--ssh-allow', '203.0.113.10', ...$args],
        base_path('infra/penpot'), ['ONHOST_NODE_SYSROOT' => penpotNodePosix($sysroot), 'SSH_CLIENT' => false, 'SSH_CONNECTION' => false]);
    $process->setTimeout(60);
    $process->run();
    File::deleteDirectory($dir);

    return ['rc' => (int) $process->getExitCode(), 'out' => str_replace("\r", '', $process->getOutput().$process->getErrorOutput())];
}

function penpotNodeLine(string $out, string $needle): int
{
    foreach (explode("\n", $out) as $i => $line) {
        if (str_contains($line, $needle)) {
            return $i;
        }
    }

    return -1;
}

it('creates the platform user before anything is made its property', function () {
    if (penpotNodeBash() === null) {
        $this->markTestSkipped('no bash available');
    }
    $run = penpotNodeDryRun([], []);

    expect($run['rc'])->toBe(0, $run['out']);
    $user = penpotNodeLine($run['out'], 'useradd');
    expect($user)->toBeGreaterThan(-1, $run['out']);
    foreach (['directory /etc/caddy/onhost-penpot', '00-onhost-placeholder.caddy', 'directory /srv/onhost-penpot'] as $owned) {
        expect(penpotNodeLine($run['out'], $owned))->toBeGreaterThan($user, $owned." comes before the user exists:\n".$run['out']);
    }
    // statically too: nothing is made the deploy user's before the useradd line of the script
    $script = penpotNodeScript('provision-node.sh');
    expect(strpos($script, 'useradd'))->toBeLessThan(strpos($script, 'ensure_dir "$PROXY_SITES"'));
});

it('on a fresh node writes the Docker settings before Docker first starts, never restarts it, and gives Caddy its configuration before it is installed', function () {
    if (penpotNodeBash() === null) {
        $this->markTestSkipped('no bash available');
    }
    $run = penpotNodeDryRun([], []);

    expect($run['rc'])->toBe(0, $run['out'])
        ->and($run['out'])->not->toContain('restart docker')
        ->and(penpotNodeLine($run['out'], 'etc/docker/daemon.json'))->toBeGreaterThan(-1)->toBeLessThan(penpotNodeLine($run['out'], '--no-install-recommends docker-ce docker-ce-cli'))
        // the Caddyfile is the platform's (no package default `:80` file server) and is in place before caddy is installed
        ->and(penpotNodeLine($run['out'], 'etc/caddy/Caddyfile'))->toBeGreaterThan(-1)->toBeLessThan(penpotNodeLine($run['out'], 'install caddy'))
        ->and($run['out'])->toContain('import /etc/caddy/onhost-penpot/*.caddy')
        ->and($run['out'])->not->toContain('/usr/share/caddy')
        ->and($run['out'])->toContain('policy-rc.d');
});

it('on a host that already runs Docker (a game node) leaves Docker and its firewall alone unless a restart is allowed', function () {
    if (penpotNodeBash() === null) {
        $this->markTestSkipped('no bash available');
    }
    $shared = penpotNodeDryRun(['docker_active' => true, 'installed' => ['docker-ce', 'docker-compose-plugin']], ['--caddy-bind', '127.0.0.1', '--http-port', '8080', '--https-port', '8443']);

    expect($shared['rc'])->toBe(0, $shared['out'])
        ->and($shared['out'])->not->toContain('restart docker')
        ->and($shared['out'])->not->toContain('write /etc/docker/daemon.json')
        ->and($shared['out'])->not->toContain('/etc/apt/preferences.d/onhost-docker')
        ->and($shared['out'])->toContain('--allow-docker-restart')
        // the firewall of a shared host is not taken over: no default policy change, no enable
        ->and($shared['out'])->not->toContain('ufw default deny incoming')
        // Caddy listens where it was told, on loopback, with the redirect server bound there too
        ->and($shared['out'])->toContain('default_bind 127.0.0.1')
        ->and($shared['out'])->toContain('http_port 8080')
        ->and($shared['out'])->toContain('https_port 8443');

    $allowed = penpotNodeDryRun(['docker_active' => true, 'installed' => ['docker-ce', 'docker-compose-plugin']], ['--allow-docker-restart', '--caddy-bind', '127.0.0.1', '--http-port', '8080', '--https-port', '8443']);
    expect($allowed['rc'])->toBe(0, $allowed['out'])->and($allowed['out'])->toContain('[dry-run] systemctl restart docker');
});

it('refuses to install Caddy on ports another web server already holds', function () {
    if (penpotNodeBash() === null) {
        $this->markTestSkipped('no bash available');
    }
    $busy = penpotNodeDryRun(['listen' => [22, 80, 443]], []);
    $moved = penpotNodeDryRun(['listen' => [22, 80, 443]], ['--caddy-bind', '127.0.0.1', '--http-port', '8080', '--https-port', '8443']);
    $badBind = penpotNodeDryRun([], ['--caddy-bind', '127.0.0.1; reboot']);
    $badPort = penpotNodeDryRun([], ['--http-port', '99999']);

    expect($busy['rc'])->toBe(1)->and($busy['out'])->toContain('--caddy-bind')->and($busy['out'])->not->toContain('install caddy')
        ->and($moved['rc'])->toBe(0, $moved['out'])
        ->and($badBind['rc'])->toBe(1)->and($badBind['out'])->toContain('--caddy-bind')
        ->and($badPort['rc'])->toBe(1)->and($badPort['out'])->toContain('--http-port');
});

it('verifies a moved Caddy, a shared host and a rootless Docker only with well-formed options', function () {
    if (penpotNodeBash() === null) {
        $this->markTestSkipped('no bash available');
    }
    foreach ([['--docker-host', 'tcp://203.0.113.9:2375'], ['--docker-host', 'unix:///run/user/1001/docker.sock;reboot'], ['--caddy-bind', '127.0.0.1;reboot'], ['--http-port', 'eighty']] as $bad) {
        expect(penpotNodeRun('verify-node.sh', $bad)['rc'])->toBe(2, implode(' ', $bad));
    }
    $verify = penpotNodeScript('verify-node.sh');
    expect($verify)->toContain('--shared-host')->toContain('default_bind')->toContain('DOCKER_HOST=');
});

it('replaces only the package default Caddyfile, never an operator\'s own', function () {
    if (penpotNodeBash() === null) {
        $this->markTestSkipped('no bash available');
    }
    $stock = penpotNodeDryRun(['installed' => ['caddy'], 'files' => ['/etc/caddy/Caddyfile' => ":80 {\n\troot * /usr/share/caddy\n\tfile_server\n}\n"]], []);
    $own = penpotNodeDryRun(['installed' => ['caddy'], 'files' => ['/etc/caddy/Caddyfile' => "shop.example.test {\n\treverse_proxy 127.0.0.1:3000\n}\n"]], []);

    expect($stock['rc'])->toBe(0, $stock['out'])->and($stock['out'])->toContain('Managed by ONhost provision-node.sh')
        ->and($own['rc'])->toBe(0, $own['out'])->and($own['out'])->not->toContain('Managed by ONhost provision-node.sh')
        ->and($own['out'])->toContain("append 'import /etc/caddy/onhost-penpot/*.caddy'");
});
