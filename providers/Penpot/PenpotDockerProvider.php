<?php

declare(strict_types=1);

namespace Onhost\Providers\Penpot;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\ActionPlan;
use Onhost\Providers\Contracts\ActualState;
use Onhost\Providers\Contracts\AsyncHandle;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\BackupCapable;
use Onhost\Providers\Contracts\FileTransport;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\NodeShell;
use Onhost\Providers\Contracts\ProviderHealth;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\ResourceSpec;
use Onhost\Providers\Contracts\ShellResult;
use Onhost\Providers\Contracts\Usage;
use Onhost\Providers\Shell\Q;
use Onhost\Providers\Shell\SftpTransport;
use Onhost\Providers\Shell\SshShell;

/**
 * Penpot executor (TASK-0123): one Docker Compose project per customer service on a dedicated Penpot node, reached over SSH
 * by the platform's own user (instance options `ssh_host`, `ssh_port`, `ssh_user`, `ssh_fingerprint`; credential
 * `ssh_private_key`). The node runs Docker Engine with the compose plugin and Caddy as the reverse proxy (docs/runbooks/penpot.md).
 *
 * What it does on the node, all synchronous (a call returns when the node is done):
 *  - provision: the project directory, its files (compose, `.env` 0600 with the secrets, SMTP env), `up -d`, the proxy site;
 *  - suspend / resume: `stop` / `up -d` — the data stays, nothing answers on the address while suspended;
 *  - terminate: `down --volumes`, the directory, the node's backups of the stack and the proxy site go;
 *  - backup: `pg_dump` of the stack's database + the assets volume (`docker cp … -`), kept under `backup_root/<project>/<stamp>`.
 *
 * Secrets never appear in a command line or in a result: they are written as files through the SFTP transport. Owner
 * passwords are the exception the Penpot CLI forces (`manage.py create-profile|update-profile --password`): they are part of
 * one `docker compose exec` on the node and of nothing the platform stores (the operation forgets them, OperationSecrets).
 */
final class PenpotDockerProvider implements BackupCapable, InfrastructureProvider
{
    /** Test seam: fn (ProviderInstance): NodeShell. Set on this class; reset in afterEach. */
    public static ?\Closure $shellFactory = null;

    /** Test seam: fn (ProviderInstance, string $root): FileTransport. */
    public static ?\Closure $transportFactory = null;

    public const STACK_PATTERN = '/^penpot-[a-z0-9]{4,32}$/';

    private const HOST_PATTERN = '/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/';

    private const STAMP_PATTERN = '/^\d{8}-\d{6}-[a-z0-9]{4}$/';

    private ?NodeShell $shell = null;

    /** @param array<string,string> $credentials */
    public function __construct(
        private readonly ProviderInstance $instance,
        private readonly array $credentials,
        private readonly CacheRepository $cache,
    ) {}

    public static function providerKey(): string
    {
        return 'penpot';
    }

    public static function adapterVersion(): string
    {
        return '1.0.0';
    }

    /** Docker Engine majors the shell commands were written against (`docker compose` v2 plugin syntax). */
    public static function supportedVendorVersions(): array
    {
        return ['docker-27', 'docker-28', 'docker-29'];
    }

    public function capabilities(): array
    {
        return ['penpot.stack' => true, 'backup' => true, 'restore' => true, 'suspend' => true, 'owner_account' => true, 'tls' => 'caddy-automatic'];
    }

    public function health(): ProviderHealth
    {
        $started = hrtime(true);
        try {
            $out = $this->run("docker version --format '{{.Server.Version}}' && docker compose version --short", 30, 'health');
            $lines = array_values(array_filter(array_map('trim', explode("\n", $out->stdout))));
            $version = $lines[0] ?? null;
            if ($version !== null) {
                $this->cache->put("onhost:penpot:version:{$this->instance->id}", $version, 3600);
            }

            return new ProviderHealth(true, $version, (int) ((hrtime(true) - $started) / 1_000_000), ['compose' => $lines[1] ?? null, 'shell' => $this->shell()->describe()]);
        } catch (ProviderException $e) {
            return ProviderHealth::down($e->getMessage(), (int) ((hrtime(true) - $started) / 1_000_000));
        }
    }

    public function vendorVersion(): ?string
    {
        $v = $this->cache->get("onhost:penpot:version:{$this->instance->id}");

        return is_string($v) && $v !== '' ? $v : null;
    }

    // ── lifecycle ───────────────────────────────────────────────────────────────────────────────────────

    /**
     * spec: stack, hostname, entitlements (ram_mb, cpus), secrets {secret_key, db_password} (in memory only, never persisted),
     * service_id. Idempotent: a project directory that is already there keeps its port and is brought up again.
     */
    public function provision(ResourceSpec $spec): ProviderResult
    {
        $project = self::project((string) $spec->get('stack'));
        $hostname = strtolower(trim((string) $spec->get('hostname')));
        if (preg_match(self::HOST_PATTERN, $hostname) !== 1) {
            throw new ProviderException('penpot', ProviderErrorCode::VALIDATION, 'Invalid host name for the Penpot stack');
        }
        $secrets = (array) $spec->get('secrets', []);
        if (strlen((string) ($secrets['secret_key'] ?? '')) < 64 || strlen((string) ($secrets['db_password'] ?? '')) < 24) {
            throw new ProviderException('penpot', ProviderErrorCode::VALIDATION, 'The stack secrets are missing (vault)');
        }
        $dir = $this->dir($project);
        $existing = trim($this->run('test -f '.Q::arg($dir.'/docker-compose.yaml').' && cat '.Q::arg($dir.'/.port').' 2>/dev/null || true', 30, 'stack.lookup')->stdout);
        $port = ctype_digit($existing) ? (int) $existing : $this->freePort();
        $limits = $this->limits((array) $spec->get('entitlements', []));
        $this->writeStack($project, $port, $hostname, $limits, $secrets);
        $this->run($this->compose($project).' up -d --remove-orphans', 900, 'stack.up');
        $this->writeProxy((string) $spec->serviceId, $project, $hostname, $port);

        return ProviderResult::completed(
            new ResourceRef('stack', $project, $this->instance->option('node_name'), ['identifier' => $project, 'name' => $project, 'port' => $port, 'hostname' => $hostname, 'public_uri' => 'https://'.$hostname, 'limits' => $limits]),
            ['port' => $port, 'hostname' => $hostname, 'limits' => $limits],
            ctype_digit($existing),
        );
    }

    public function getActualState(ResourceRef $ref): ActualState
    {
        $project = self::project($ref->remoteId);
        $out = $this->run($this->compose($project).' ps --all --format json 2>/dev/null; echo "--onhost-dir:"; test -d '.Q::arg($this->dir($project)).' && echo yes || echo no', 60, 'stack.ps');
        [$json, $dir] = array_pad(explode('--onhost-dir:', $out->stdout, 2), 2, '');
        $containers = self::containers($json);
        if ($containers === []) {
            // the project directory is there without containers: a stack that was stopped down or never came up
            return trim($dir) === 'yes' ? new ActualState(true, ['identifier' => $project, 'name' => $project, 'containers' => []], 'stopped', now()->toISOString()) : ActualState::missing();
        }
        $states = array_values($containers);
        $running = count(array_filter($states, fn (string $s) => $s === 'running'));
        $status = match (true) {
            $running === count(PenpotCompose::SERVICES) => 'running',
            $running === 0 => 'stopped',
            default => 'degraded',
        };

        // the compose project the node reports is the identity a compensation or a deletion compares (ServiceIdentityCheck)
        return new ActualState(true, ['identifier' => self::projectOf($json) ?? $project, 'name' => $project, 'containers' => $containers, 'running' => $running], $status, now()->toISOString());
    }

    public function reconcile(ResourceSpec $spec, ActualState $actual): ActionPlan
    {
        if (! $actual->exists) {
            return new ActionPlan([ActionPlan::drift('existence', 'present', 'missing', 'ONHOST_MANAGED', 'SECURITY_SUSPICIOUS')]);
        }

        return $actual->status === 'degraded' ? new ActionPlan([ActionPlan::drift('containers', 'running', 'degraded', 'ONHOST_MANAGED', 'AUTO_REPAIRABLE')]) : ActionPlan::inSync();
    }

    /** New limits from a plan change: the compose file is written again and the stack recreated with them. */
    public function resize(ResourceRef $ref, ResourceSpec $spec): ProviderResult
    {
        $project = self::project($ref->remoteId);
        $limits = $this->limits((array) $spec->get('entitlements', []));
        $this->transport($this->root())->write($project.'/docker-compose.yaml', PenpotCompose::compose($project, $limits));
        $this->run($this->compose($project).' up -d --remove-orphans', 900, 'stack.resize');

        return ProviderResult::completed($ref->withMeta(['limits' => $limits]), ['limits' => $limits]);
    }

    public function suspend(ResourceRef $ref): ProviderResult
    {
        $this->run($this->compose(self::project($ref->remoteId)).' stop', 300, 'stack.stop');

        return ProviderResult::completed($ref, ['status' => 'stopped']);
    }

    public function resume(ResourceRef $ref): ProviderResult
    {
        if (($ref->meta['start'] ?? true) === false) {
            return ProviderResult::completed($ref, ['status' => 'stopped']);
        }
        $this->run($this->compose(self::project($ref->remoteId)).' up -d', 900, 'stack.start');

        return ProviderResult::completed($ref, ['status' => 'running']);
    }

    /** The stack, its volumes, its directory, its node backups and its proxy site. A stack that is not there is already gone. */
    public function terminate(ResourceRef $ref): ProviderResult
    {
        $project = self::project($ref->remoteId);
        $dir = $this->dir($project);
        $this->run('if [ -f '.Q::arg($dir.'/docker-compose.yaml').' ]; then '.$this->compose($project).' down --volumes --remove-orphans; fi', 600, 'stack.down');
        $site = $this->proxyRoot().'/'.$project.'.caddy';
        $this->run('rm -f '.Q::arg($site).' && ('.$this->proxyReload().') && rm -rf -- '.Q::arg($dir).' '.Q::arg($this->backupRoot().'/'.$project), 120, 'stack.remove');

        return ProviderResult::completed($ref, ['removed' => true]);
    }

    public function usage(ResourceRef $ref, ?string $periodStart = null, ?string $periodEnd = null): Usage
    {
        $project = self::project($ref->remoteId);
        $volumes = Q::arg($project.'_penpot_assets').' '.Q::arg($project.'_penpot_postgres_v15');
        $out = $this->run('for v in '.$volumes.'; do p=$(docker volume inspect -f \'{{.Mountpoint}}\' "$v" 2>/dev/null) && du -sb "$p" | cut -f1 || echo 0; done', 120, 'stack.usage');
        $sizes = array_map('intval', array_values(array_filter(array_map('trim', explode("\n", $out->stdout)), fn ($l) => $l !== '')));

        return new Usage(['assets_bytes' => $sizes[0] ?? 0, 'database_bytes' => $sizes[1] ?? 0, 'disk_bytes' => array_sum($sizes)], now()->toISOString(), $periodStart, $periodEnd);
    }

    /** Every call answers when the node is done; there is nothing to poll. */
    public function awaitStatus(AsyncHandle $handle): AsyncStatus
    {
        return AsyncStatus::succeeded();
    }

    // ── backups ─────────────────────────────────────────────────────────────────────────────────────────

    /** pg_dump of the stack's database and the assets volume, into `backup_root/<project>/<stamp>`; older ones beyond `keep_backups` go. */
    public function backup(ResourceRef $ref, array $policy): ProviderResult
    {
        $project = self::project($ref->remoteId);
        $stamp = now()->utc()->format('Ymd-His').'-'.strtolower(substr(bin2hex(random_bytes(2)), 0, 4));
        $dir = $this->backupRoot().'/'.$project.'/'.$stamp;
        $c = $this->compose($project);
        $keep = max(1, (int) $this->option('keep_backups'));
        $script = 'set -o pipefail; B='.Q::arg($dir).'; install -d -m 0700 "$B"'
            .' && was=$('.$c.' ps --status running -q penpot-postgres)'
            .' && { [ -n "$was" ] || '.$c.' up -d --wait penpot-postgres; }'
            .' && '.$c.' exec -T penpot-postgres pg_dump -U penpot -d penpot --no-owner | gzip -c > "$B/db.sql.gz"'
            .' && docker cp "$('.$c.' ps --all -q penpot-backend)":/opt/data/assets - | gzip -c > "$B/assets.tar.gz"'
            .' && { [ -n "$was" ] || '.$c.' stop penpot-postgres; }'
            .' && du -sb "$B" | cut -f1 > "$B/.size" && date -u +%Y-%m-%dT%H:%M:%SZ > "$B/.done"'
            .' && ls -1d '.Q::arg($this->backupRoot().'/'.$project).'/*/ | sort | head -n -'.$keep.' | xargs -r rm -rf --'
            .' && cat "$B/.size"';
        $out = $this->run($script, 900, 'backup.create');

        return ProviderResult::completed($ref, ['backup_uuid' => $stamp, 'size_bytes' => (int) trim($out->stdout)]);
    }

    public function listBackups(ResourceRef $ref): array
    {
        $root = $this->backupRoot().'/'.self::project($ref->remoteId);
        $out = $this->run('for d in '.Q::arg($root).'/*/; do [ -f "$d.done" ] && printf \'%s %s %s\n\' "$(basename "$d")" "$(cat "$d.size" 2>/dev/null || echo 0)" "$(cat "$d.done")"; done; true', 60, 'backup.list');
        $rows = [];
        foreach (explode("\n", $out->stdout) as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];
            if (count($parts) === 3 && preg_match(self::STAMP_PATTERN, $parts[0]) === 1) {
                $rows[] = ['remote_id' => $parts[0], 'created_at' => $parts[2], 'size_bytes' => (int) $parts[1], 'verified' => true, 'protected' => false, 'meta' => ['path' => $root.'/'.$parts[0]]];
            }
        }

        return $rows;
    }

    /** The database and the assets of one backup replace what the stack holds now; the stack runs again afterwards. */
    public function restore(ResourceRef $ref, string $backupRemoteId, array $options = []): ProviderResult
    {
        if (preg_match(self::STAMP_PATTERN, $backupRemoteId) !== 1) {
            throw new ProviderException('penpot', ProviderErrorCode::VALIDATION, 'Unknown Penpot backup');
        }
        $project = self::project($ref->remoteId);
        $c = $this->compose($project);
        $b = $this->backupRoot().'/'.$project.'/'.$backupRemoteId;
        $script = 'set -o pipefail; B='.Q::arg($b).'; [ -f "$B/.done" ]'
            .' && '.$c.' stop penpot-frontend penpot-exporter penpot-backend'
            .' && '.$c.' up -d --wait penpot-postgres'
            .' && '.$c.' exec -T penpot-postgres psql -U penpot -d penpot -v ON_ERROR_STOP=1 -c \'DROP SCHEMA public CASCADE; CREATE SCHEMA public;\''
            .' && gunzip -c "$B/db.sql.gz" | '.$c.' exec -T penpot-postgres psql -U penpot -d penpot -v ON_ERROR_STOP=1 -q'
            .' && gunzip -c "$B/assets.tar.gz" | docker cp - "$('.$c.' ps --all -q penpot-backend)":/opt/data'
            .' && '.$c.' up -d';
        $this->run($script, 900, 'backup.restore');

        return ProviderResult::completed($ref, ['restored' => $backupRemoteId]);
    }

    /** A copy of one backup's files for the final archive (FinalArchive pulls them off the node before the stack goes). */
    public function downloadBackup(ResourceRef $ref, string $backupRemoteId, string $file, string $localPath): void
    {
        if (preg_match(self::STAMP_PATTERN, $backupRemoteId) !== 1 || ! in_array($file, ['db.sql.gz', 'assets.tar.gz'], true)) {
            throw new ProviderException('penpot', ProviderErrorCode::VALIDATION, 'Unknown Penpot backup file');
        }
        $this->transport($this->backupRoot())->download(self::project($ref->remoteId).'/'.$backupRemoteId.'/'.$file, $localPath);
    }

    // ── the owner's Penpot account and the address ───────────────────────────────────────────────────

    /**
     * The first Penpot profile of the instance (registration is off): `manage.py create-profile` inside the backend, which
     * needs the PREPL server flag. A profile that is already there (a retried step) gets the password instead.
     *
     * @return array{created:bool}
     */
    public function ensureOwner(ResourceRef $ref, string $email, string $fullname, string $password): array
    {
        $this->assertOwner($email, $password);
        $c = $this->compose(self::project($ref->remoteId));
        $created = $this->shell()->run($c.' exec -T penpot-backend python3 manage.py create-profile --email '.Q::arg($email).' --fullname '.Q::arg(mb_substr(trim($fullname) ?: $email, 0, 120)).' --password '.Q::arg($password).' --skip-tutorial --skip-walkthrough', ['timeout' => 180]);
        if ($created->ok()) {
            return ['created' => true];
        }
        $this->setOwnerPassword($ref, $email, $password);

        return ['created' => false];
    }

    public function setOwnerPassword(ResourceRef $ref, string $email, string $password): ProviderResult
    {
        $this->assertOwner($email, $password);
        $c = $this->compose(self::project($ref->remoteId));
        $result = $this->shell()->run($c.' exec -T penpot-backend python3 manage.py update-profile --email '.Q::arg($email).' --password '.Q::arg($password), ['timeout' => 180]);
        if (! $result->ok()) {
            // the CLI's own words may quote the command: they are not repeated, only that it failed
            throw new ProviderException('penpot', $result->timedOut ? ProviderErrorCode::TRANSIENT : ProviderErrorCode::UNKNOWN, 'Penpot refused the owner password change (exit '.$result->exitCode.')');
        }

        return ProviderResult::completed($ref, ['owner' => $email]);
    }

    /** What the stack's frontend answers on the node itself (HTTP status code; 0 = nothing answered). */
    public function probe(ResourceRef $ref): int
    {
        $port = (int) ($ref->meta['port'] ?? 0);
        if ($port <= 0) {
            $port = (int) trim($this->run('cat '.Q::arg($this->dir(self::project($ref->remoteId)).'/.port').' 2>/dev/null || echo 0', 30, 'stack.port')->stdout);
        }
        $result = $this->shell()->run('curl -sS -o /dev/null -w \'%{http_code}\' --max-time 10 '.Q::arg('http://127.0.0.1:'.$port.'/'), ['timeout' => 20]);

        return (int) trim($result->stdout);
    }

    // ── internals ───────────────────────────────────────────────────────────────────────────────────────

    public function shell(): NodeShell
    {
        if ($this->shell !== null) {
            return $this->shell;
        }
        if (self::$shellFactory !== null && app()->runningUnitTests()) {
            $scripted = (self::$shellFactory)($this->instance);
            if ($scripted instanceof NodeShell) {
                return $this->shell = $scripted;
            }
        }

        return $this->shell = new SshShell(
            (string) $this->instance->option('ssh_host', parse_url((string) $this->instance->base_url, PHP_URL_HOST) ?: ''), (int) $this->instance->option('ssh_port', 22), (string) $this->instance->option('ssh_user', 'onhost'),
            (string) ($this->credentials['ssh_private_key'] ?? ''), $this->credentials['ssh_key_password'] ?? null, $this->instance->option('ssh_fingerprint'), 10, 'penpot',
        );
    }

    public function transport(string $root): FileTransport
    {
        if (self::$transportFactory !== null && app()->runningUnitTests()) {
            $fake = (self::$transportFactory)($this->instance, $root);
            if ($fake instanceof FileTransport) {
                return $fake;
            }
        }
        $shell = $this->shell();
        if (! $shell instanceof SshShell) {
            throw new ProviderException('penpot', ProviderErrorCode::VALIDATION, 'The Penpot node needs an SSH shell for file transfer');
        }

        return new SftpTransport($shell, $root, 'penpot');
    }

    /** @param array<string,mixed> $limits @param array<string,mixed> $secrets */
    private function writeStack(string $project, int $port, string $hostname, array $limits, array $secrets): void
    {
        $smtp = (array) $this->instance->option('smtp', []);
        $flags = array_values(array_unique(array_merge((array) $this->option('flags'), trim((string) ($smtp['host'] ?? '')) === '' ? (array) $this->option('flags_without_smtp') : ['enable-smtp'])));
        $this->run('install -d -m 0750 '.Q::arg($this->dir($project)), 30, 'stack.dir');
        $files = $this->transport($this->root());
        $files->write($project.'/.env', PenpotCompose::env([
            'version' => (string) $this->option('version'), 'port' => $port, 'public_uri' => 'https://'.$hostname, 'flags' => $flags,
            'secret_key' => (string) $secrets['secret_key'], 'db_password' => (string) $secrets['db_password'],
            'postgres_image' => (string) $this->option('postgres_image'), 'valkey_image' => (string) $this->option('valkey_image'),
            'valkey_maxmemory' => (string) $this->option('valkey_maxmemory'), 'max_body_size' => (int) $this->option('max_body_size'),
        ]));
        $files->chmod($project.'/.env', 0600);
        $files->write($project.'/.env.smtp', PenpotCompose::smtpEnv($smtp, $this->credentials['smtp_password'] ?? null));
        $files->chmod($project.'/.env.smtp', 0600);
        $files->write($project.'/docker-compose.yaml', PenpotCompose::compose($project, $limits));
        $files->write($project.'/.port', $port."\n");
    }

    private function writeProxy(string $serviceId, string $project, string $hostname, int $port): void
    {
        $this->transport($this->proxyRoot())->write($project.'.caddy', PenpotCompose::proxySite($serviceId, $hostname, $port, (int) $this->option('max_body_size')));
        $this->run($this->proxyReload(), 60, 'proxy.reload');
    }

    /** The lowest port of the range no stack of this node holds (`.port` of every project directory). */
    private function freePort(): int
    {
        $range = (array) $this->option('ports');
        $from = (int) ($range['from'] ?? 19001);
        $to = (int) ($range['to'] ?? 19999);
        $taken = array_map('intval', array_filter(array_map('trim', explode("\n", $this->run('cat '.Q::arg($this->root()).'/*/.port 2>/dev/null; true', 30, 'stack.ports')->stdout)), 'ctype_digit'));
        for ($port = $from; $port <= $to; $port++) {
            if (! in_array($port, $taken, true)) {
                return $port;
            }
        }

        throw new ProviderException('penpot', ProviderErrorCode::CAPACITY, 'No free port for another Penpot stack on this node');
    }

    /** @param array<string,mixed> $entitlements @return array{ram_mb:int, cpus:float, storage_gb:int} */
    private function limits(array $entitlements): array
    {
        $defaults = (array) $this->option('defaults');

        return [
            'ram_mb' => max(1024, (int) ($entitlements['ram_mb'] ?? $defaults['ram_mb'] ?? 4096)),
            'cpus' => max(0.5, (float) ($entitlements['cpus'] ?? $defaults['cpus'] ?? 2)),
            'storage_gb' => max(1, (int) ($entitlements['storage_gb'] ?? $defaults['storage_gb'] ?? 20)),
        ];
    }

    private function compose(string $project): string
    {
        return 'docker compose --project-directory '.Q::arg($this->dir($project)).' -p '.Q::arg($project);
    }

    private function run(string $command, int $timeout, string $action): ShellResult
    {
        $result = $this->shell()->run($command, ['timeout' => $timeout]);
        if (! $result->ok()) {
            $tail = mb_substr(trim($result->stderr !== '' ? $result->stderr : $result->stdout), -400);
            throw new ProviderException('penpot', $result->timedOut || $result->exitCode === 255 ? ProviderErrorCode::TRANSIENT : ProviderErrorCode::UNKNOWN, "{$action} failed (exit {$result->exitCode}): {$tail}");
        }

        return $result;
    }

    /** @return array<string,string> service => state, from `docker compose ps --format json` (JSON lines, or one array on older compose) */
    private static function containers(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }
        $rows = str_starts_with($json, '[') ? (array) json_decode($json, true) : array_map(fn (string $l) => json_decode($l, true), array_filter(array_map('trim', explode("\n", $json))));
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['Service'])) {
                $out[(string) $row['Service']] = strtolower((string) ($row['State'] ?? 'unknown'));
            }
        }

        return $out;
    }

    /** The compose project name the node itself reports (`Project` of `docker compose ps --format json`), when it says one. */
    private static function projectOf(string $json): ?string
    {
        foreach (preg_split('/\R/', trim($json)) ?: [] as $line) {
            $row = json_decode(trim($line), true);
            $row = is_array($row) && array_is_list($row) ? ($row[0] ?? null) : $row;
            if (is_array($row) && is_string($row['Project'] ?? null) && $row['Project'] !== '') {
                return $row['Project'];
            }
        }

        return null;
    }

    private function assertOwner(string $email, string $password): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($password) < 12 || strlen($password) > 128) {
            throw new ProviderException('penpot', ProviderErrorCode::VALIDATION, 'The owner needs an e-mail address and a password of 12–128 characters');
        }
    }

    private static function project(string $name): string
    {
        if (preg_match(self::STACK_PATTERN, $name) !== 1) {
            throw new ProviderException('penpot', ProviderErrorCode::VALIDATION, 'Invalid Penpot stack name');
        }

        return $name;
    }

    private function option(string $key): mixed
    {
        return $this->instance->option($key, config('penpot.'.$key));
    }

    private function root(): string
    {
        return rtrim((string) $this->option('root'), '/');
    }

    private function dir(string $project): string
    {
        return $this->root().'/'.$project;
    }

    private function backupRoot(): string
    {
        return rtrim((string) $this->option('backup_root'), '/');
    }

    private function proxyRoot(): string
    {
        return rtrim((string) $this->option('proxy_sites'), '/');
    }

    private function proxyReload(): string
    {
        return (string) $this->option('proxy_reload');
    }
}
