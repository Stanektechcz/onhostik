<?php

declare(strict_types=1);

namespace Onhost\Providers\IspConfig;

use Illuminate\Support\Str;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;
use Onhost\Providers\Contracts\FileTransport;
use Onhost\Providers\Contracts\Naming;
use Onhost\Providers\Contracts\NodeShell;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Shell\ManagedDirectives;
use Onhost\Providers\Shell\Q;
use Onhost\Providers\Shell\SecurityRules;
use Onhost\Providers\Shell\SftpTransport;
use Onhost\Providers\Shell\SshShell;
use phpseclib3\Crypt\EC;

/**
 * WebToolsProvider for ISPConfig 3.2: the remote API for what it covers (php.ini, directives, cron, database
 * access, backups, quotas, the client's one-time panel login) and SSH as the site's own jailed agent user for the
 * rest. The agent user is an ISPConfig shell user created per site with the instance's Ed25519 key (kept in the
 * secret store); it shares the web user's uid, so files, dumps, git and WP-CLI run with exactly the site's rights.
 */
trait IspConfigTools
{
    /** @var array<string, SshShell> */
    private array $siteShells = [];

    /** Test seam: a factory returning the shell to use for a site (ScriptedShell in the suite); null = real SSH. Set it on the adapter class (`IspConfigWebProvider::$shellFactory`), not on the trait. */
    public static $shellFactory = null;

    public function shell(ResourceRef $site): NodeShell
    {
        if (self::$shellFactory !== null) {
            $scripted = (self::$shellFactory)($site, $this->instance);
            if ($scripted instanceof NodeShell) {
                return $scripted;
            }
        }
        $agent = $this->agentUser($site);
        if (isset($this->siteShells[$agent])) {
            return $this->siteShells[$agent];
        }
        $keys = $this->agentKeys(false);
        $shell = new SshShell($this->sshHost(), (int) $this->instance->option('ssh_port', 22), $agent, (string) ($keys['private'] ?? ''), null, $this->instance->option('ssh_fingerprint'), 10, 'ispconfig');
        $fresh = $this->cache->get("onhost:ispconfig:agent-fresh:{$this->instance->id}:{$site->remoteId}");
        $shell->preparingUntil = is_int($fresh) ? $fresh : null; // a refused login right after creation means the job queue has not applied the jailed user yet

        return $this->siteShells[$agent] = $shell;
    }

    public function transport(ResourceRef $site): FileTransport
    {
        $shell = $this->shell($site);
        if (! $shell instanceof SshShell) {
            throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, 'The file transport needs the SSH agent user');
        }

        return new SftpTransport($shell, $this->agentDocroot($site), 'ispconfig');
    }

    public function shellAvailable(ResourceRef $site): bool
    {
        $keys = $this->agentKeys(false);
        if (($keys['private'] ?? '') === '') {
            return false;
        }
        $key = "onhost:ispconfig:agent-ok:{$this->instance->id}:{$site->remoteId}";
        $cached = $this->cache->get($key);
        if (is_bool($cached)) {
            return $cached;
        }
        $ok = collect($this->listShellUsers($site))->firstWhere('user', $this->agentUser($site)) !== null;
        $this->cache->put($key, $ok, 300);

        return $ok;
    }

    public function ensureAgent(ResourceRef $site): ProviderResult
    {
        $keys = $this->agentKeys(true);
        $agent = $this->agentUser($site);
        $existing = collect($this->listShellUsers($site))->firstWhere('user', $agent);
        if ($existing !== null) {
            if (empty($existing['has_key'])) {
                $this->setShellKey($site, (string) $existing['remote_id'], (string) $keys['public']);
            }
            $this->cache->forget("onhost:ispconfig:agent-ok:{$this->instance->id}:{$site->remoteId}");

            return ProviderResult::completed(new ResourceRef('shell_user', (string) $existing['remote_id'], $site->node, ['user' => $agent, 'agent' => true], $site->serviceId), ['agent' => $agent], alreadyExisted: true);
        }
        $row = $this->getSite((int) $site->remoteId) ?? [];
        $id = (int) $this->api->call('sites_shell_user_add', ['client_id' => (int) ($site->meta['client_id'] ?? 0), 'params' => [
            'server_id' => (int) $site->node, 'parent_domain_id' => (int) $site->remoteId, 'username' => $agent, 'password' => Str::password(28), 'quota_size' => -1, 'active' => 'y',
            'puser' => (string) ($row['system_user'] ?? ($site->meta['system_user'] ?? '')), 'pgroup' => (string) ($row['system_group'] ?? ''), 'shell' => '/bin/bash', 'dir' => (string) ($row['document_root'] ?? ($site->meta['document_root'] ?? '')),
            'chroot' => (string) $this->instance->option('agent_chroot', 'jailkit'), 'ssh_rsa' => (string) $keys['public'],
        ]], true);
        $this->cache->forget("onhost:ispconfig:agent-ok:{$this->instance->id}:{$site->remoteId}");
        $this->cache->put("onhost:ispconfig:agent-fresh:{$this->instance->id}:{$site->remoteId}", time() + 900, 900);
        unset($this->siteShells[$agent]);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), new ResourceRef('shell_user', (string) $id, $site->node, ['user' => $agent, 'agent' => true], $site->serviceId), ['agent' => $agent, 'created' => true]);
    }

    public function siteUser(ResourceRef $site): string
    {
        $user = (string) ($site->meta['system_user'] ?? '');
        if ($user === '') {
            $user = (string) (($this->getSite((int) $site->remoteId) ?? [])['system_user'] ?? '');
        }

        return $user;
    }

    public function documentRoot(ResourceRef $site): string
    {
        // the toolkit's shell and transport live inside the site's jail: paths are the ones the agent user sees
        if ($this->shellAvailable($site)) {
            try {
                return $this->agentDocroot($site);
            } catch (ProviderException) {
                // fall through to the panel's absolute path
            }
        }

        return rtrim($this->siteDir($site), '/').'/web';
    }

    public function setDocumentRoot(ResourceRef $site, string $relative): ProviderResult
    {
        throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, 'ISPConfig serves the site from web/; releases are switched by the deploy symlink instead');
    }

    public function toolPaths(ResourceRef $site): array
    {
        $run = $this->shell($site)->run('for t in php composer git node npm mysql mysqldump redis-cli wp; do printf "%s=%s\n" "$t" "$(command -v $t 2>/dev/null)"; done; test -f "$HOME/private/wp-cli.phar" && echo wpphar=$HOME/private/wp-cli.phar', ['timeout' => 30]);
        $found = [];
        foreach (preg_split('/\r?\n/', $run->stdout) ?: [] as $line) {
            if (str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $found[trim($k)] = trim($v) !== '' ? trim($v) : null;
            }
        }

        return ['php' => $found['php'] ?? null, 'wp' => $found['wpphar'] ?? ($found['wp'] ?? null), 'composer' => $found['composer'] ?? null, 'git' => $found['git'] ?? null, 'node' => $found['node'] ?? null, 'npm' => $found['npm'] ?? null, 'mysql' => $found['mysql'] ?? null, 'mysqldump' => $found['mysqldump'] ?? null, 'redis-cli' => $found['redis-cli'] ?? null];
    }

    /** WP-CLI in the site's private folder (downloaded once by the agent), run with the jail's PHP. */
    public function wpCommand(ResourceRef $site): string
    {
        $paths = $this->toolPaths($site);
        if (($paths['php'] ?? null) === null) {
            throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, 'PHP CLI is not available in the site jail (add /usr/bin/php to jailkit_chroot_app_programs)');
        }
        $this->shell($site)->run('test -f "$HOME/private/wp-cli.phar" || (mkdir -p "$HOME/private" && (curl -fsSL -o "$HOME/private/wp-cli.phar" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar || wget -qO "$HOME/private/wp-cli.phar" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar))', ['timeout' => 120]);

        return Q::arg((string) $paths['php']).' "$HOME/private/wp-cli.phar"';
    }

    public function phpSettings(ResourceRef $site): array
    {
        $row = $this->getSite((int) $site->remoteId) ?? [];
        $settings = [];
        foreach (preg_split('/\r?\n/', (string) ($row['custom_php_ini'] ?? '')) ?: [] as $line) {
            if (preg_match('/^\s*([a-z_.]+)\s*=\s*(.*?)\s*$/i', $line, $m)) {
                $settings[$m[1]] = trim($m[2], '"\'');
            }
        }
        $version = (string) ($row['fastcgi_php_version'] ?? '');
        $extensions = [];
        if ($this->shellAvailable($site)) {
            try {
                $run = $this->shell($site)->run('php -m 2>/dev/null', ['timeout' => 30]);
                $extensions = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $run->stdout) ?: []), fn ($l) => $l !== '' && ! str_starts_with($l, '[')));
            } catch (ProviderException) {
                // the jail has no php; extensions stay unknown
            }
        }

        return ['version' => $version !== '' ? (preg_match('/(\d\.\d)/', $version, $m) ? $m[1] : $version) : null, 'settings' => $settings, 'editable' => SecurityRules::PHP_EDITABLE, 'extensions' => $extensions, 'open_basedir' => trim((string) ($row['php_open_basedir'] ?? '')) !== ''];
    }

    public function setPhpSettings(ResourceRef $site, array $settings): ProviderResult
    {
        $row = $this->getSite((int) $site->remoteId) ?? [];
        $kept = [];
        foreach (preg_split('/\r?\n/', (string) ($row['custom_php_ini'] ?? '')) ?: [] as $line) {
            if (preg_match('/^\s*([a-z_.]+)\s*=/i', $line, $m) && in_array($m[1], SecurityRules::PHP_EDITABLE, true)) {
                continue;
            }
            if (trim($line) !== '') {
                $kept[] = $line;
            }
        }
        foreach ($settings as $key => $value) {
            if (in_array($key, SecurityRules::PHP_EDITABLE, true) && $value !== '' && $value !== null) {
                $kept[] = $key.' = '.SecurityRules::iniValue($key, (string) $value);
            }
        }
        $this->updateSite($site, ['custom_php_ini' => implode("\n", $kept)]);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), $site, ['settings' => array_intersect_key($settings, array_flip(SecurityRules::PHP_EDITABLE))]);
    }

    public function securityRules(ResourceRef $site): array
    {
        $row = $this->getSite((int) $site->remoteId) ?? [];
        $field = $this->webServerType() === 'nginx' ? 'nginx_directives' : 'apache_directives';
        $rules = SecurityRules::parse((string) ($row[$field] ?? ''));
        $rules['rate'] = null;
        $rules['supports'] = ['deny', 'allow', 'bots', 'hotlink', 'hsts', 'headers'];

        return $rules;
    }

    public function setSecurityRules(ResourceRef $site, array $rules): ProviderResult
    {
        $rules = SecurityRules::normalize($rules);
        $row = $this->getSite((int) $site->remoteId) ?? [];
        $domain = (string) ($row['domain'] ?? ($site->meta['domain'] ?? ''));
        $nginx = $this->webServerType() === 'nginx';
        $field = $nginx ? 'nginx_directives' : 'apache_directives';
        $block = $nginx ? SecurityRules::nginx($rules, $domain) : SecurityRules::apache($rules, $domain);
        $this->updateSite($site, [$field => SecurityRules::splice((string) ($row[$field] ?? ''), $block)]);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), $site, ['rules' => $rules]);
    }

    public function httpVersions(ResourceRef $site): array
    {
        $row = $this->getSite((int) $site->remoteId) ?? [];
        $server = $this->webServerType();

        return ['http2' => in_array((string) ($row['http2'] ?? ($server === 'nginx' ? 'y' : 'n')), ['y', '1'], true) || $server === 'nginx', 'http3' => false, 'http3_available' => false, 'server' => $server];
    }

    public function setHttp3(ResourceRef $site, bool $enabled): ProviderResult
    {
        throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, 'HTTP/3 is not offered on ISPConfig servers (the web server template has no QUIC listener)');
    }

    public function updateCron(ResourceRef $site, string $remoteId, array $job): ProviderResult
    {
        $current = collect($this->listCron($site))->firstWhere('remote_id', $remoteId);
        if ($current === null) {
            throw new ProviderException('ispconfig', ProviderErrorCode::NOT_FOUND, 'The cron job does not belong to this site');
        }
        [$minute, $hour, $dom, $month, $dow] = array_pad(explode(' ', trim((string) ($job['schedule'] ?? $current['schedule']))), 5, '*');
        $params = ['run_min' => $minute, 'run_hour' => $hour, 'run_mday' => $dom, 'run_month' => $month, 'run_wday' => $dow, 'command' => (string) ($job['command'] ?? $current['command'])];
        if (array_key_exists('active', $job)) {
            $params['active'] = $job['active'] ? 'y' : 'n';
        }
        $this->api->call('sites_cron_update', ['client_id' => (int) ($site->meta['client_id'] ?? 0), 'primary_id' => (int) $remoteId, 'params' => $params], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), new ResourceRef('cron', $remoteId, $site->node, [], $site->serviceId), ['updated' => true]);
    }

    public function runCron(ResourceRef $site, string $remoteId): ProviderResult
    {
        $current = collect($this->listCron($site))->firstWhere('remote_id', $remoteId);
        if ($current === null) {
            throw new ProviderException('ispconfig', ProviderErrorCode::NOT_FOUND, 'The cron job does not belong to this site');
        }
        // the session starts in the site's home; `cd '$HOME/web'` is a directory literally called $HOME/web (quoting is ours), so the
        // command after `&&` never ran and the answer still said "started"
        $run = $this->shell($site)->run((string) $current['command'], ['timeout' => 300, 'cwd' => 'web']);

        return ProviderResult::completed(new ResourceRef('cron', $remoteId, $site->node, [], $site->serviceId), ['started' => $run->exitCode === 0, 'exit_code' => $run->exitCode, 'output' => mb_substr($run->output(), 0, 20000)]);
    }

    public function cronLogs(ResourceRef $site, string $remoteId, int $lines = 100): array
    {
        return []; // ISPConfig keeps no per-job log; "run now" returns the output directly
    }

    public function databaseAccess(ResourceRef $site, string $remoteId): array
    {
        $row = $this->siteDatabaseRow($site, $remoteId);
        $remote = (($row['remote_access'] ?? 'n') === 'y');
        $hosts = array_values(array_filter(array_map('trim', explode(',', (string) ($row['remote_ips'] ?? ''))), fn ($h) => $h !== ''));

        return ['remote' => $remote, 'hosts' => $remote ? $hosts : []];
    }

    public function setDatabaseAccess(ResourceRef $site, string $remoteId, bool $remote, array $hosts = []): ProviderResult
    {
        $this->siteDatabaseRow($site, $remoteId);
        $this->api->call('sites_database_update', ['client_id' => (int) ($site->meta['client_id'] ?? 0), 'primary_id' => (int) $remoteId, 'params' => ['remote_access' => $remote ? 'y' : 'n', 'remote_ips' => $remote ? implode(',', $hosts) : '']], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), new ResourceRef('database', $remoteId, $site->node, [], $site->serviceId), ['remote' => $remote, 'hosts' => $hosts]);
    }

    public function exportDatabase(ResourceRef $site, string $remoteId, string $localFile, array $credentials = []): ProviderResult
    {
        $row = $this->siteDatabaseRow($site, $remoteId);
        [$user, $password, $host] = $this->databaseCredentials($credentials);
        $name = (string) $row['database_name'];
        $remote = 'private/onhost-export-'.bin2hex(random_bytes(4)).'.sql.gz';
        $cmd = sprintf('mkdir -p "$HOME/private" && mysqldump --single-transaction --quick --routines --triggers -h %s -u %s -p%s %s | gzip > "$HOME/%s"', Q::arg($host), Q::arg($user), Q::arg($password), Q::arg($name), $remote);
        $run = $this->shell($site)->run($cmd, ['timeout' => 900]);
        if (! $run->ok()) {
            throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, 'The dump failed: '.$run->output());
        }
        $transport = new SftpTransport($this->shell($site), $this->siteDirInShell($site), 'ispconfig');
        $transport->download($remote, $localFile);
        $this->shell($site)->run('rm -f "$HOME/'.$remote.'"', ['timeout' => 20]);

        return ProviderResult::completed(new ResourceRef('database', $remoteId, $site->node, ['name' => $name], $site->serviceId), ['bytes' => @filesize($localFile) ?: null]);
    }

    public function importDatabase(ResourceRef $site, string $remoteId, string $localFile, array $credentials = []): ProviderResult
    {
        $row = $this->siteDatabaseRow($site, $remoteId);
        [$user, $password, $host] = $this->databaseCredentials($credentials);
        $name = (string) $row['database_name'];
        $gz = str_ends_with(strtolower($localFile), '.gz');
        $remote = 'private/onhost-import-'.bin2hex(random_bytes(4)).($gz ? '.sql.gz' : '.sql');
        $transport = new SftpTransport($this->shell($site), $this->siteDirInShell($site), 'ispconfig');
        $this->shell($site)->run('mkdir -p "$HOME/private"', ['timeout' => 20]);
        $transport->upload($remote, $localFile);
        $cmd = sprintf('%s "$HOME/%s" | mysql -h %s -u %s -p%s %s', $gz ? 'gunzip -c' : 'cat', $remote, Q::arg($host), Q::arg($user), Q::arg($password), Q::arg($name));
        $run = $this->shell($site)->run($cmd, ['timeout' => 900]);
        $this->shell($site)->run('rm -f "$HOME/'.$remote.'"', ['timeout' => 20]);
        if (! $run->ok()) {
            throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, 'The import failed: '.$run->output());
        }

        return ProviderResult::completed(new ResourceRef('database', $remoteId, $site->node, ['name' => $name], $site->serviceId), ['imported' => true]);
    }

    public function deleteBackup(ResourceRef $site, string $backupRemoteId): ProviderResult
    {
        throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, 'ISPConfig prunes site backups itself by the number of copies; single archives cannot be removed through the API');
    }

    public function downloadBackup(ResourceRef $site, string $backupRemoteId, string $localFile): void
    {
        $backup = collect($this->listBackups($site))->firstWhere('remote_id', $backupRemoteId);
        $filename = is_array($backup) ? basename((string) ($backup['meta']['filename'] ?? '')) : '';
        if ($filename === '') {
            throw new ProviderException('ispconfig', ProviderErrorCode::NOT_FOUND, 'The backup does not belong to this site');
        }
        // `backup_download` copies the archive from the server's backup store into the site's backup/ folder (applied by the job
        // queue). The remote function takes the BACKUP's id as `primary_id`; the list above proved it is this site's.
        $this->api->call('sites_web_domain_backup', ['primary_id' => (int) $backupRemoteId, 'action_type' => 'backup_download'], true);
        $transport = new SftpTransport($this->shell($site), $this->siteDirInShell($site), 'ispconfig');
        $deadline = microtime(true) + 600;
        while (! $transport->exists('backup/'.$filename)) {
            if (microtime(true) > $deadline) {
                throw new ProviderException('ispconfig', ProviderErrorCode::TRANSIENT, 'The server did not stage the backup for download in time');
            }
            sleep(5);
        }
        $transport->download('backup/'.$filename, $localFile);
    }

    public function quotas(ResourceRef $site): array
    {
        $clientId = (int) ($site->meta['client_id'] ?? 0);
        $out = ['disk_used_bytes' => null, 'disk_limit_bytes' => null, 'traffic_used_bytes' => null, 'traffic_limit_bytes' => null, 'inodes_used' => null, 'measured_at' => now()->toIso8601String()];
        try {
            foreach ((array) $this->api->call('quota_get_by_user', ['client_id' => $clientId]) as $row) {
                if (is_array($row) && (int) ($row['domain_id'] ?? 0) === (int) $site->remoteId) {
                    $out['disk_used_bytes'] = isset($row['used']) ? (int) $row['used'] * 1024 : null;
                    $out['disk_limit_bytes'] = isset($row['hard']) && (int) $row['hard'] > 0 ? (int) $row['hard'] * 1024 : (isset($row['soft']) && (int) $row['soft'] > 0 ? (int) $row['soft'] * 1024 : null);
                    $out['inodes_used'] = isset($row['files']) ? (int) $row['files'] : null;
                }
            }
        } catch (ProviderException) {
            // the remote user may lack the quota function group
        }
        try {
            foreach ((array) $this->api->call('trafficquota_get_by_user', ['client_id' => $clientId]) as $row) {
                if (is_array($row) && (int) ($row['domain_id'] ?? 0) === (int) $site->remoteId) {
                    $out['traffic_used_bytes'] = isset($row['this_month']) ? (int) $row['this_month'] : (isset($row['traffic']) ? (int) $row['traffic'] : null);
                    $out['traffic_limit_bytes'] = isset($row['traffic_quota']) && (int) $row['traffic_quota'] > 0 ? (int) $row['traffic_quota'] * 1048576 : null;
                }
            }
        } catch (ProviderException) {
            // traffic quota not enabled on the server
        }

        return $out;
    }

    public function nodeProjects(ResourceRef $site): array
    {
        return [];
    }

    public function createNodeProject(ResourceRef $site, array $spec): ProviderResult
    {
        throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, 'Node projects are offered on managed hosting nodes, not on ISPConfig servers');
    }

    public function nodeProjectAction(ResourceRef $site, string $remoteId, string $action): ProviderResult
    {
        throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, 'Node projects are offered on managed hosting nodes, not on ISPConfig servers');
    }

    /*
     * Reverse proxies and default documents: ISPConfig has no API for either, so the platform keeps them as a marked
     * block inside the site's Apache/nginx directives (ManagedDirectives) — the customer's own directives and the
     * security block stay untouched, and the state is read back from the marker line. Apache nodes need mod_proxy
     * (a2enmod proxy proxy_http) for the ProxyPass lines to take effect; ISPConfig writes the vhost on its next job run.
     */
    public function listProxies(ResourceRef $site): array
    {
        return array_map(fn (array $p) => ['remote_id' => $p['name'], 'name' => $p['name'], 'path' => $p['path'], 'target' => $p['target'], 'enabled' => true, 'cache' => $p['cache']], $this->managedDirectives($site)['state']['proxies']);
    }

    public function createProxy(ResourceRef $site, array $spec): ProviderResult
    {
        $name = strtolower(trim((string) ($spec['name'] ?? '')));
        $target = rtrim(trim((string) ($spec['target'] ?? '')), '/');
        if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,39}$/', $name) || ! preg_match('~^https?://[A-Za-z0-9.\[\]:_-]+(?::\d{1,5})?(?:/[^\s]*)?$~', $target)) {
            throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, 'A reverse proxy needs a short name and an http(s) upstream URL');
        }
        $m = $this->managedDirectives($site);
        $entry = ['name' => $name, 'path' => ManagedDirectives::path((string) ($spec['path'] ?? '/')), 'target' => $target, 'cache' => ! empty($spec['cache'])];
        $existing = collect($m['state']['proxies'])->firstWhere('name', $name);
        if ($existing !== null && $existing === $entry) {
            return ProviderResult::completed(new ResourceRef('proxy', $name, $this->instance->key, $entry, $site->serviceId), alreadyExisted: true);
        }
        $proxies = array_values(array_filter($m['state']['proxies'], fn (array $p) => $p['name'] !== $name));
        $proxies[] = $entry;
        $this->writeManagedDirectives($site, $m, ['proxies' => $proxies, 'index' => $m['state']['index']]);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), new ResourceRef('proxy', $name, $this->instance->key, $entry, $site->serviceId), ['created' => true]);
    }

    public function deleteProxy(ResourceRef $site, string $remoteId): ProviderResult
    {
        $m = $this->managedDirectives($site);
        $left = array_values(array_filter($m['state']['proxies'], fn (array $p) => $p['name'] !== $remoteId));
        if (count($left) === count($m['state']['proxies'])) {
            return ProviderResult::completed(new ResourceRef('proxy', $remoteId, $this->instance->key, [], $site->serviceId), alreadyExisted: true);
        }
        $this->writeManagedDirectives($site, $m, ['proxies' => $left, 'index' => $m['state']['index']]);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), new ResourceRef('proxy', $remoteId, $this->instance->key, [], $site->serviceId), ['deleted' => true]);
    }

    /** The whole list at once: one vhost write whatever the number of proxies (the same block, the same rules as createProxy). */
    public function setProxies(ResourceRef $site, array $items): ProviderResult
    {
        $proxies = [];
        foreach ($items as $spec) {
            $name = strtolower(trim((string) ($spec['name'] ?? '')));
            $target = rtrim(trim((string) ($spec['target'] ?? '')), '/');
            if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,39}$/', $name) || ! preg_match('~^https?://[A-Za-z0-9.\[\]:_-]+(?::\d{1,5})?(?:/[^\s]*)?$~', $target)) {
                throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, 'Every reverse proxy needs a short name and an http(s) upstream URL');
            }
            $proxies[$name] = ['name' => $name, 'path' => ManagedDirectives::path((string) ($spec['path'] ?? '/')), 'target' => $target, 'cache' => ! empty($spec['cache'])];
        }
        $m = $this->managedDirectives($site);
        $next = array_values($proxies);
        if ($next === $m['state']['proxies']) {
            return ProviderResult::completed($site, ['proxies' => $next, 'unchanged' => true]);
        }
        $this->writeManagedDirectives($site, $m, ['proxies' => $next, 'index' => $m['state']['index']]);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), $site, ['proxies' => $next]);
    }

    /** The managed DirectoryIndex / index list; empty means the web server's default (index.php, index.html) applies. */
    public function defaultDocuments(ResourceRef $site): array
    {
        return $this->managedDirectives($site)['state']['index'];
    }

    public function setDefaultDocuments(ResourceRef $site, array $names): ProviderResult
    {
        $names = array_values(array_unique(array_filter(array_map(fn ($n) => trim((string) $n), $names), fn ($n) => $n !== '' && preg_match('/^[A-Za-z0-9._-]{1,60}$/', $n))));
        $m = $this->managedDirectives($site);
        if ($names === $m['state']['index']) {
            return ProviderResult::completed($site, ['names' => $names, 'unchanged' => true]);
        }
        $this->writeManagedDirectives($site, $m, ['proxies' => $m['state']['proxies'], 'index' => $names]);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), $site, ['names' => $names]);
    }

    /** @return array{row:array<string,mixed>, field:string, server:string, state:array{proxies:list<array{name:string,path:string,target:string,cache:bool}>, index:list<string>}} */
    private function managedDirectives(ResourceRef $site): array
    {
        $row = $this->getSite((int) $site->remoteId) ?? [];
        $server = $this->webServerType();
        $field = $server === 'nginx' ? 'nginx_directives' : 'apache_directives';

        return ['row' => $row, 'field' => $field, 'server' => $server, 'state' => ManagedDirectives::parse((string) ($row[$field] ?? ''))];
    }

    /** @param  array{row:array<string,mixed>, field:string, server:string}  $m  @param  array{proxies:list<array<string,mixed>>, index:list<string>}  $state */
    private function writeManagedDirectives(ResourceRef $site, array $m, array $state): void
    {
        $this->updateSite($site, [$m['field'] => ManagedDirectives::splice((string) ($m['row'][$m['field']] ?? ''), ManagedDirectives::render($state, $m['server']))]);
    }

    public function panelLoginUrl(ResourceRef $site): ?string
    {
        $clientId = (int) ($site->meta['client_id'] ?? 0);
        $client = $clientId > 0 ? $this->api->call('client_get', ['client_id' => $clientId]) : null;
        $username = is_array($client) ? (string) ($client['username'] ?? '') : '';
        if ($username === '') {
            return null;
        }
        $result = $this->api->call('client_login_get', ['username' => $username], true);
        if (is_string($result) && $result !== '') {
            return str_starts_with($result, 'http') ? $result : rtrim((string) $this->instance->base_url, '/').'/'.ltrim($result, '/');
        }

        return null;
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function agentUser(ResourceRef $site): string
    {
        return Naming::prefix($site->serviceId).'ag';
    }

    /** @return array{public:string, private:string} the instance's agent key pair (generated once, kept in the secret store) */
    private function agentKeys(bool $create): array
    {
        $key = "onhost:ispconfig:agent-keys:{$this->instance->id}";
        $cached = $this->cache->get($key);
        if (is_array($cached) && ($cached['private'] ?? '') !== '') {
            return $cached;
        }
        $store = app(SecretStore::class);
        $ref = SecretRef::parse("db://provider_instances/{$this->instance->key}/node-shell");
        $values = [];
        try {
            $values = $store->exists($ref) ? $store->read($ref) : [];
        } catch (\Throwable) {
            $values = [];
        }
        if (($values['private'] ?? '') === '' && $create) {
            $private = EC::createKey('Ed25519');
            $values = ['private' => $private->toString('OpenSSH'), 'public' => trim($private->getPublicKey()->toString('OpenSSH', ['comment' => 'onhost-agent-'.$this->instance->key]))];
            $store->write($ref, $values);
        }
        $out = ['public' => (string) ($values['public'] ?? ''), 'private' => (string) ($values['private'] ?? '')];
        if ($out['private'] !== '') {
            $this->cache->put($key, $out, 3600);
        }

        return $out;
    }

    private function sshHost(): string
    {
        return (string) $this->instance->option('ssh_host', parse_url((string) $this->instance->base_url, PHP_URL_HOST) ?: '');
    }

    private function siteDir(ResourceRef $site): string
    {
        $dir = (string) ($site->meta['document_root'] ?? '');
        if ($dir === '') {
            $dir = (string) (($this->getSite((int) $site->remoteId) ?? [])['document_root'] ?? '');
        }

        return rtrim($dir, '/');
    }

    /** The site directory as the agent sees it: `/` inside a jail, the real path otherwise (detected once per site). */
    private function siteDirInShell(ResourceRef $site): string
    {
        $key = "onhost:ispconfig:agent-home:{$this->instance->id}:{$site->remoteId}";
        $cached = $this->cache->get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        $home = trim($this->shell($site)->run('cd ~ && pwd', ['timeout' => 20])->stdout);
        $home = $home === '' ? $this->siteDir($site) : $home;
        $this->cache->put($key, $home, 3600);

        return $home;
    }

    private function agentDocroot(ResourceRef $site): string
    {
        return rtrim($this->siteDirInShell($site), '/').'/web';
    }

    private function webServerType(): string
    {
        $key = "onhost:ispconfig:webserver:{$this->instance->id}";
        $cached = $this->cache->get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        $type = 'apache';
        try {
            $config = $this->api->call('server_get', ['server_id' => $this->serverId(), 'section' => 'web']);
            $web = is_array($config) ? ($config['web'] ?? ($config[$this->serverId()]['web'] ?? $config)) : [];
            $type = (string) ($web['server_type'] ?? 'apache') === 'nginx' ? 'nginx' : 'apache';
        } catch (ProviderException) {
            // keep apache
        }
        $this->cache->put($key, $type, 3600);

        return $type;
    }

    /** @return array<string,mixed> */
    private function siteDatabaseRow(ResourceRef $site, string $remoteId): array
    {
        $row = $this->api->call('sites_database_get', ['primary_id' => (int) $remoteId]);
        if (! is_array($row) || empty($row['database_id']) || (int) ($row['parent_domain_id'] ?? 0) !== (int) $site->remoteId) {
            throw new ProviderException('ispconfig', ProviderErrorCode::NOT_FOUND, 'The database does not belong to this site');
        }

        return $row;
    }

    /** @return array{0:string,1:string,2:string} */
    private function databaseCredentials(array $credentials): array
    {
        $user = (string) ($credentials['user'] ?? '');
        $password = (string) ($credentials['password'] ?? '');
        if ($user === '' || $password === '') {
            throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, 'Database credentials are needed for dumps and imports — set a new database password in the panel first');
        }

        return [$user, $password, (string) ($credentials['host'] ?? 'localhost')];
    }
}
