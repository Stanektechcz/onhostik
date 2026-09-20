<?php

declare(strict_types=1);

namespace Onhost\Providers\AaPanel;

use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\FileTransport;
use Onhost\Providers\Contracts\Naming;
use Onhost\Providers\Contracts\NodeShell;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Shell\Q;
use Onhost\Providers\Shell\SecurityRules;
use Onhost\Providers\Shell\SshShell;

/**
 * WebToolsProvider for aaPanel 8: the panel API where it has one (run path, limits, cron, database access and dumps,
 * backup archives, Node projects) and the node shell (`files?action=ExecShell`, or SSH when the instance says
 * `shell: ssh`) for the rest — `.user.ini` PHP settings, the managed security include in the site's nginx vhost,
 * HTTP/3, restores from site archives, downloads. Every site runs as `www` on aaPanel, so that is the site user.
 */
trait AaPanelTools
{
    private ?AaPanelShell $apiShell = null;

    /** Test seam: a factory returning the shell to use for a site (ScriptedShell in the suite); null = real shells. Set it on the adapter class (`AaPanelWebProvider::$shellFactory`), not on the trait. */
    public static $shellFactory = null;

    public function shell(ResourceRef $site): NodeShell
    {
        if (self::$shellFactory !== null) {
            $scripted = (self::$shellFactory)($site, $this->instance);
            if ($scripted instanceof NodeShell) {
                return $scripted;
            }
        }
        if ((string) $this->instance->option('shell', 'api') === 'ssh') {
            return new SshShell(
                (string) $this->instance->option('ssh_host', parse_url((string) $this->instance->base_url, PHP_URL_HOST) ?: ''), (int) $this->instance->option('ssh_port', 22), (string) $this->instance->option('ssh_user', 'root'),
                (string) ($this->credentials['ssh_private_key'] ?? ''), $this->credentials['ssh_key_password'] ?? null, $this->instance->option('ssh_fingerprint'), 10, 'aapanel',
            );
        }

        return $this->apiShell ??= new AaPanelShell(fn (string $path, array $params, string $action, bool $critical) => $this->post($path, $params, $action, $critical), $this->instance->key, (string) ($this->credentials['api_key'] ?? '') !== '');
    }

    public function transport(ResourceRef $site): FileTransport
    {
        return $this->transportAt($site, $this->sitePath($site));
    }

    public function shellAvailable(ResourceRef $site): bool
    {
        if (! $this->shell($site)->available()) {
            return false;
        }
        $key = "onhost:aapanel:agent:{$this->instance->id}:{$site->remoteId}";
        $cached = $this->cache->get($key);
        if (is_bool($cached)) {
            return $cached;
        }
        $ok = trim($this->shell($site)->run('id -u '.Q::arg($this->agentUser($site)).' 2>/dev/null', ['timeout' => 20])->stdout) !== '';
        $this->cache->put($key, $ok, 600);

        return $ok;
    }

    /**
     * A per-site agent user on the node: the panel's hardening (`libusranalyse` in ld.so.preload) blocks `www` from
     * executing any binary, so toolkit commands run as `<prefix>ag` — a member of `www` with ACLs on the site root that
     * keep PHP-FPM (`www`) able to read and write everything the agent creates. Idempotent; verified live on 8.0.6.
     */
    public function ensureAgent(ResourceRef $site): ProviderResult
    {
        $agent = $this->agentUser($site);
        $root = $this->sitePath($site);
        $acl = 'u:'.$agent.':rwx,d:u:'.$agent.':rwx,u:www:rwx,d:u:www:rwx';
        $script = 'if ! id -u '.Q::arg($agent).' >/dev/null 2>&1; then useradd -M -d '.Q::arg($root).' -s /bin/bash -G www '.Q::arg($agent).'; fi'
            .'; setfacl -R -m '.Q::arg($acl).' '.Q::arg($root).' >/dev/null 2>&1; setfacl -m '.Q::arg($acl).' '.Q::arg($root).' >/dev/null 2>&1' // immutable files (.user.ini) are skipped
            .'; test -d /www/server/onhost || mkdir -p /www/server/onhost; chmod 755 /www/server/onhost; id -u '.Q::arg($agent);
        $run = $this->shell($site)->run($script, ['timeout' => 300]);
        if (! $run->ok()) {
            throw new ProviderException('aapanel', ProviderErrorCode::TRANSIENT, 'The site agent user could not be prepared: '.mb_substr($run->output(), 0, 200));
        }
        $this->cache->put("onhost:aapanel:agent:{$this->instance->id}:{$site->remoteId}", true, 600);

        return ProviderResult::completed(new ResourceRef('shell_user', $agent, $this->instance->key, ['user' => $agent, 'agent' => true], $site->serviceId), ['agent' => $agent, 'created' => true]);
    }

    private function agentUser(ResourceRef $site): string
    {
        return Naming::prefix($site->serviceId).'ag';
    }

    public function siteUser(ResourceRef $site): string
    {
        return $this->agentUser($site);
    }

    public function documentRoot(ResourceRef $site): string
    {
        $run = $this->post('/site?action=GetSiteRunPath', ['id' => (int) $site->remoteId], 'site.runpath');
        $path = is_array($run) ? (string) ($run['runPath'] ?? '/') : '/';

        return rtrim($this->sitePath($site).'/'.trim($path, '/'), '/');
    }

    public function setDocumentRoot(ResourceRef $site, string $relative): ProviderResult
    {
        $relative = '/'.trim(str_replace('\\', '/', $relative), '/');
        if (str_contains($relative, '..')) {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'The run path must stay inside the site root');
        }
        $this->post('/site?action=SetSiteRunPath', ['id' => (int) $site->remoteId, 'runPath' => $relative], 'site.runpath.set', true);

        return ProviderResult::completed($site, ['run_path' => $relative]);
    }

    public function toolPaths(ResourceRef $site): array
    {
        $version = $this->sitePhpVersion($site);
        $php = $version !== null ? '/www/server/php/'.$version.'/bin/php' : null;
        $found = [];
        $run = $this->shell($site)->run('for t in composer git node npm mysql mysqldump redis-cli wp; do printf "%s=%s\n" "$t" "$(command -v $t 2>/dev/null)"; done; test -x /www/server/onhost/wp-cli.phar && echo wpphar=/www/server/onhost/wp-cli.phar', ['timeout' => 30]);
        foreach (preg_split('/\r?\n/', $run->stdout) ?: [] as $line) {
            if (str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $found[trim($k)] = trim($v) !== '' ? trim($v) : null;
            }
        }
        $wp = $found['wpphar'] ?? $found['wp'] ?? null;

        return ['php' => $php ?? ($found['php'] ?? null), 'wp' => $wp, 'composer' => $found['composer'] ?? null, 'git' => $found['git'] ?? null, 'node' => $found['node'] ?? null, 'npm' => $found['npm'] ?? null, 'mysql' => $found['mysql'] ?? null, 'mysqldump' => $found['mysqldump'] ?? null, 'redis-cli' => $found['redis-cli'] ?? null];
    }

    /** Ensure WP-CLI on the node (shared, read-only for sites); returns the command prefix to run it with the site's PHP. */
    public function wpCommand(ResourceRef $site): string
    {
        $shell = $this->shell($site);
        $shell->run('test -x /www/server/onhost/wp-cli.phar || (mkdir -p /www/server/onhost && curl -fsSL -o /www/server/onhost/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod 755 /www/server/onhost/wp-cli.phar)', ['timeout' => 120]);
        $php = $this->toolPaths($site)['php'] ?? 'php';

        return Q::arg($php).' /www/server/onhost/wp-cli.phar --allow-root';
    }

    public function phpSettings(ResourceRef $site): array
    {
        $ini = '';
        try {
            $ini = $this->transport($site)->read('.user.ini', 65536);
        } catch (ProviderException) {
            // no .user.ini yet
        }
        $settings = [];
        foreach (preg_split('/\r?\n/', $ini) ?: [] as $line) {
            if (preg_match('/^\s*([a-z_.]+)\s*=\s*(.*?)\s*$/i', $line, $m) && $m[1] !== 'open_basedir') {
                $settings[$m[1]] = trim($m[2], '"\'');
            }
        }
        $openBasedir = null;
        try {
            $state = $this->post('/site?action=GetDirUserINI', ['id' => (int) $site->remoteId, 'path' => $this->sitePath($site)], 'site.userini');
            $openBasedir = is_array($state) ? (bool) ($state['userini'] ?? false) : null;
        } catch (ProviderException) {
            // older panels
        }
        $extensions = [];
        $php = $this->toolPaths($site)['php'];
        if ($php !== null) {
            $run = $this->shell($site)->run(Q::arg($php).' -m 2>/dev/null', ['timeout' => 30]);
            $extensions = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $run->stdout) ?: []), fn ($l) => $l !== '' && ! str_starts_with($l, '[')));
        }

        return ['version' => $this->sitePhpVersionDotted($site), 'settings' => $settings, 'editable' => SecurityRules::PHP_EDITABLE, 'extensions' => $extensions, 'open_basedir' => $openBasedir];
    }

    public function setPhpSettings(ResourceRef $site, array $settings): ProviderResult
    {
        $transport = $this->transport($site);
        $current = '';
        try {
            $current = $transport->read('.user.ini', 65536);
        } catch (ProviderException) {
            // created below
        }
        $kept = [];
        foreach (preg_split('/\r?\n/', $current) ?: [] as $line) {
            if (preg_match('/^\s*([a-z_.]+)\s*=/i', $line, $m) && in_array($m[1], SecurityRules::PHP_EDITABLE, true)) {
                continue; // managed keys are rewritten below
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
        $content = implode("\n", $kept)."\n";
        $absolute = $this->sitePath($site).'/.user.ini';
        $shell = $this->shell($site);
        $locked = $shell->run('lsattr '.Q::arg($absolute).' 2>/dev/null | cut -d" " -f1 | grep -q i && echo locked', ['timeout' => 20])->stdout;
        $shell->run('chattr -i '.Q::arg($absolute).' 2>/dev/null; true', ['timeout' => 20]);
        $transport->write('.user.ini', $content);
        if (str_contains($locked, 'locked')) {
            $shell->run('chattr +i '.Q::arg($absolute).' 2>/dev/null; true', ['timeout' => 20]);
        }
        $this->reloadPhp($site);

        return ProviderResult::completed($site, ['settings' => array_intersect_key($settings, array_flip(SecurityRules::PHP_EDITABLE))]);
    }

    public function securityRules(ResourceRef $site): array
    {
        $rules = SecurityRules::defaults();
        try {
            $conf = $this->nodeTransport()->read($this->securityIncludePath($site), 262144);
            $rules = array_replace($rules, SecurityRules::parse($conf));
        } catch (ProviderException) {
            // no managed include yet
        }
        try {
            $limit = $this->post('/site?action=GetLimitNet', ['id' => (int) $site->remoteId], 'site.limit');
            $rules['rate'] = is_array($limit) ? ['perip' => (int) ($limit['perip'] ?? 0), 'perserver' => (int) ($limit['perserver'] ?? 0), 'limit_rate' => (int) ($limit['limit_rate'] ?? 0)] : null;
        } catch (ProviderException) {
            $rules['rate'] = null;
        }
        $rules['supports'] = ['deny', 'allow', 'bots', 'hotlink', 'hsts', 'headers', 'rate'];

        return $rules;
    }

    public function setSecurityRules(ResourceRef $site, array $rules): ProviderResult
    {
        $rules = SecurityRules::normalize($rules);
        $domain = (string) ($site->meta['name'] ?? '');
        $include = $this->securityIncludePath($site);
        $shell = $this->shell($site);
        $shell->run('mkdir -p '.Q::arg(dirname($include)), ['timeout' => 20]);
        $this->nodeTransport()->write($include, SecurityRules::nginx($rules, $domain));
        $vhost = $this->vhostPath($site);
        $conf = $this->nodeTransport()->read($vhost, 262144);
        if (! str_contains($conf, $include)) {
            $line = '    include '.$include.';';
            $patched = preg_match('/^(\s*)#REWRITE-END/m', $conf) ? preg_replace('/^(\s*#REWRITE-END.*)$/m', "$1\n{$line}", $conf, 1) : preg_replace('/\}\s*$/', $line."\n}\n", $conf, 1);
            $this->writeVhost($site, (string) $patched, $conf);
        }
        if ($rules['rate'] !== null) {
            $this->post('/site?action=SetLimitNet', ['id' => (int) $site->remoteId, 'perserver' => (int) $rules['rate']['perserver'], 'perip' => (int) $rules['rate']['perip'], 'limit_rate' => (int) $rules['rate']['limit_rate']], 'site.limit.set', true);
        }
        $this->reloadNginx();

        return ProviderResult::completed($site, ['rules' => $rules]);
    }

    public function httpVersions(ResourceRef $site): array
    {
        $conf = '';
        try {
            $conf = $this->nodeTransport()->read($this->vhostPath($site), 262144);
        } catch (ProviderException) {
            // no vhost yet
        }
        $available = str_contains($this->nginxBuild($site), 'http_v3_module');

        return ['http2' => (bool) preg_match('/listen\s+443\s+ssl\s+http2|http2\s+on/', $conf), 'http3' => (bool) preg_match('/listen\s+443\s+quic/', $conf), 'http3_available' => $available, 'server' => 'nginx'];
    }

    public function setHttp3(ResourceRef $site, bool $enabled): ProviderResult
    {
        if ($enabled && ! str_contains($this->nginxBuild($site), 'http_v3_module')) {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'The web server on this node is built without HTTP/3 (QUIC)');
        }
        $vhost = $this->vhostPath($site);
        $conf = $this->nodeTransport()->read($vhost, 262144);
        $stripped = (string) preg_replace('/^[ \t]*(listen\s+(\[::\]:)?443\s+quic[^\n]*|http3\s+on;|add_header\s+Alt-Svc[^\n]*)\n/m', '', $conf);
        if (! $enabled) {
            $this->writeVhost($site, $stripped, $conf);
            $this->reloadNginx();

            return ProviderResult::completed($site, ['http3' => false]);
        }
        if (! preg_match('/^([ \t]*)listen\s+443\s+ssl[^\n]*\n/m', $stripped, $m)) {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'HTTPS is not enabled on the site yet — issue a certificate first');
        }
        $others = $this->shell($site)->run('grep -l "quic reuseport" /www/server/panel/vhost/nginx/*.conf 2>/dev/null | head -1', ['timeout' => 20])->stdout;
        $reuse = trim($others) === '' ? ' reuseport' : '';
        $block = "{$m[1]}listen 443 quic{$reuse};\n{$m[1]}http3 on;\n{$m[1]}add_header Alt-Svc 'h3=\":443\"; ma=86400' always;\n";
        $patched = (string) preg_replace('/^([ \t]*listen\s+443\s+ssl[^\n]*\n)/m', '$1'.str_replace('$', '\$', $block), $stripped, 1);
        $this->writeVhost($site, $patched, $conf);
        $this->reloadNginx();

        return ProviderResult::completed($site, ['http3' => true]);
    }

    public function updateCron(ResourceRef $site, string $remoteId, array $job): ProviderResult
    {
        $current = collect($this->listCron($site))->firstWhere('remote_id', $remoteId);
        if ($current === null) {
            throw new ProviderException('aapanel', ProviderErrorCode::NOT_FOUND, 'The cron job does not belong to this site');
        }
        [$minute, $hour] = array_pad(explode(' ', trim((string) ($job['schedule'] ?? $current['schedule']))), 5, '*');
        $name = Naming::cronLabel($site->serviceId, $job['label'] ?? $current['label'] ?? null);
        $this->post('/crontab?action=modify_crond', [
            'id' => (int) $remoteId, 'name' => $name, 'type' => $hour === '*' ? 'hour-n' : 'day', 'where1' => $hour === '*' ? '1' : '', 'hour' => $hour === '*' ? 0 : (int) $hour, 'minute' => $minute === '*' ? 0 : (int) $minute,
            'week' => '', 'sType' => 'toShell', 'sName' => '', 'sBody' => (string) ($job['command'] ?? $current['command']), 'backupTo' => '', 'save' => '', 'urladdress' => '',
        ], 'cron.update', true);
        if (array_key_exists('active', $job) && (bool) $job['active'] !== (bool) ($current['active'] ?? true)) { // the panel's switch TOGGLES: saving a job with the state it already had used to turn it off
            $this->post('/crontab?action=set_cron_status', ['id' => (int) $remoteId], 'cron.status', true);
        }

        return ProviderResult::completed(new ResourceRef('cron', $remoteId, $this->instance->key, ['name' => $name], $site->serviceId), ['updated' => true]);
    }

    public function setCronActive(ResourceRef $site, string $remoteId, bool $active): ProviderResult
    {
        $current = collect($this->listCron($site))->firstWhere('remote_id', $remoteId);
        if ($current === null) {
            throw new ProviderException('aapanel', ProviderErrorCode::NOT_FOUND, 'The cron job does not belong to this site');
        }
        if ((bool) ($current['active'] ?? true) !== $active) { // the panel's switch toggles: it is pressed only when the state differs
            $this->post('/crontab?action=set_cron_status', ['id' => (int) $remoteId], 'cron.status', true);
        }

        return ProviderResult::completed(new ResourceRef('cron', $remoteId, $this->instance->key, [], $site->serviceId), ['active' => $active]);
    }

    public function runCron(ResourceRef $site, string $remoteId): ProviderResult
    {
        if (collect($this->listCron($site))->firstWhere('remote_id', $remoteId) === null) {
            throw new ProviderException('aapanel', ProviderErrorCode::NOT_FOUND, 'The cron job does not belong to this site');
        }
        $this->post('/crontab?action=StartTask', ['id' => (int) $remoteId], 'cron.run', true);

        return ProviderResult::completed(new ResourceRef('cron', $remoteId, $this->instance->key, [], $site->serviceId), ['started' => true]);
    }

    public function cronLogs(ResourceRef $site, string $remoteId, int $lines = 100): array
    {
        if (collect($this->listCron($site))->firstWhere('remote_id', $remoteId) === null) {
            throw new ProviderException('aapanel', ProviderErrorCode::NOT_FOUND, 'The cron job does not belong to this site');
        }
        $result = $this->post('/crontab?action=GetLogs', ['id' => (int) $remoteId], 'cron.logs');
        $text = is_array($result) ? (string) ($result['msg'] ?? '') : (string) $result;
        $all = preg_split('/\r?\n/', trim($text)) ?: [];

        return array_values(array_slice($all, -$lines));
    }

    public function databaseAccess(ResourceRef $site, string $remoteId): array
    {
        $db = $this->siteDatabase($site, $remoteId);
        $result = $this->post('/database?action=GetDatabaseAccess', ['name' => $db['name']], 'db.access');
        $permission = is_array($result) ? (string) (is_array($result['msg'] ?? null) ? ($result['msg']['permission'] ?? '') : ($result['permission'] ?? '')) : '';
        $hosts = array_values(array_filter(array_map('trim', explode(',', $permission)), fn ($h) => $h !== '' && $h !== '127.0.0.1' && $h !== 'localhost'));

        return ['remote' => $permission === '%' || $hosts !== [], 'hosts' => $permission === '%' ? [] : $hosts];
    }

    public function setDatabaseAccess(ResourceRef $site, string $remoteId, bool $remote, array $hosts = []): ProviderResult
    {
        $db = $this->siteDatabase($site, $remoteId);
        $access = ! $remote ? '127.0.0.1' : ($hosts === [] ? '%' : implode(',', $hosts));
        $this->post('/database?action=SetDatabaseAccess', ['name' => $db['name'], 'dataAccess' => $access], 'db.access.set', true);

        return ProviderResult::completed(new ResourceRef('database', $remoteId, $this->instance->key, ['name' => $db['name']], $site->serviceId), ['remote' => $remote, 'hosts' => $hosts]);
    }

    public function exportDatabase(ResourceRef $site, string $remoteId, string $localFile, array $credentials = []): ProviderResult
    {
        $db = $this->siteDatabase($site, $remoteId);
        $dumps = fn (): array => (array) ($this->post('/data?action=getData&table=backup', ['limit' => 20, 'p' => 1, 'search' => (int) $remoteId, 'type' => 1], 'db.backup.list')['data'] ?? []);
        $before = array_map(fn ($b) => (string) ($b['id'] ?? ''), $dumps());
        $this->post('/database?action=ToBackup', ['id' => (int) $remoteId], 'db.backup', true);
        // the dump of THIS call: "the newest one" was an older dump whenever the panel made none — exported as today's data and then deleted
        $latest = collect($dumps())->reject(fn ($b) => in_array((string) ($b['id'] ?? ''), $before, true))->sortByDesc('id')->first();
        $file = is_array($latest) ? (string) ($latest['filename'] ?? '') : '';
        if ($file === '') {
            throw new ProviderException('aapanel', ProviderErrorCode::PROVIDER_BUG, 'The panel did not produce a database dump');
        }
        $this->transportAt($site, dirname($file))->download(basename($file), $localFile);
        try {
            $this->post('/database?action=DelBackup', ['id' => (int) $latest['id']], 'db.backup.delete', false);
        } catch (ProviderException) {
            // the dump stays in the panel's backup folder; retention removes it
        }

        return ProviderResult::completed(new ResourceRef('database', $remoteId, $this->instance->key, ['name' => $db['name']], $site->serviceId), ['file' => basename($file), 'bytes' => @filesize($localFile) ?: null]);
    }

    public function importDatabase(ResourceRef $site, string $remoteId, string $localFile, array $credentials = []): ProviderResult
    {
        $db = $this->siteDatabase($site, $remoteId);
        $name = 'onhost-import-'.bin2hex(random_bytes(4)).(str_ends_with(strtolower($localFile), '.gz') ? '.sql.gz' : '.sql');
        $transport = $this->transportAt($site, '/www/backup/database');
        $this->shell($site)->run('mkdir -p /www/backup/database', ['timeout' => 20]);
        $transport->upload($name, $localFile);
        try {
            $this->post('/database?action=InputSql', ['file' => '/www/backup/database/'.$name, 'name' => $db['name']], 'db.import', true);
        } finally {
            $this->shell($site)->run('rm -f '.Q::arg('/www/backup/database/'.$name), ['timeout' => 20]);
        }

        return ProviderResult::completed(new ResourceRef('database', $remoteId, $this->instance->key, ['name' => $db['name']], $site->serviceId), ['imported' => true]);
    }

    public function deleteBackup(ResourceRef $site, string $backupRemoteId): ProviderResult
    {
        if (collect($this->listBackups($site))->firstWhere('remote_id', $backupRemoteId) === null) {
            return ProviderResult::completed(null, ['deleted' => false], alreadyExisted: true);
        }
        $this->post('/site?action=DelBackup', ['id' => (int) $backupRemoteId], 'backup.delete', true);

        return ProviderResult::completed(null, ['deleted' => true]);
    }

    public function downloadBackup(ResourceRef $site, string $backupRemoteId, string $localFile): void
    {
        $file = $this->backupFile($site, $backupRemoteId);
        $this->transportAt($site, dirname($file))->download(basename($file), $localFile);
    }

    /** Restore a site archive over the site root (rsync when present), optionally a database dump made by the panel. */
    public function restoreFromArchive(ResourceRef $site, string $backupRemoteId, array $options = []): ProviderResult
    {
        $file = $this->backupFile($site, $backupRemoteId);
        $root = $this->sitePath($site);
        $tmp = '/tmp/onhost-restore-'.bin2hex(random_bytes(5));
        $script = implode(' && ', [
            'rm -rf '.Q::arg($tmp), 'mkdir -p '.Q::arg($tmp), 'unzip -oq '.Q::arg($file).' -d '.Q::arg($tmp),
            'src='.Q::arg($tmp).'; if [ -d "$src/'.basename($root).'" ]; then src="$src/'.basename($root).'"; fi',
            'if command -v rsync >/dev/null; then rsync -a --delete --exclude ".user.ini" "$src/" '.Q::arg($root).'/; else cp -a "$src/." '.Q::arg($root).'/; fi',
            'chown -R www:www '.Q::arg($root), 'rm -rf '.Q::arg($tmp),
        ]);
        $run = $this->shell($site)->run($script, ['timeout' => 900]);
        if (! $run->ok()) {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'Restore failed on the node: '.$run->output());
        }
        $database = (string) ($options['database_backup_id'] ?? '');
        if ($database !== '' && ! empty($options['database_id'])) {
            $list = $this->post('/data?action=getData&table=backup', ['limit' => 50, 'p' => 1, 'search' => (int) $options['database_id'], 'type' => 1], 'db.backup.list');
            $dump = collect((array) ($list['data'] ?? []))->first(fn ($b) => (string) ($b['id'] ?? '') === $database);
            if (is_array($dump)) {
                $this->post('/database?action=InputSql', ['file' => (string) $dump['filename'], 'name' => $this->siteDatabase($site, (string) $options['database_id'])['name']], 'db.import', true);
            }
        }

        return ProviderResult::completed($site, ['restored' => true, 'archive' => basename($file)]);
    }

    public function quotas(ResourceRef $site): array
    {
        $key = "onhost:aapanel:quota:{$this->instance->id}:{$site->remoteId}";
        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }
        $run = $this->shell($site)->run('du -sb '.Q::arg($this->sitePath($site)).' 2>/dev/null | cut -f1; find '.Q::arg($this->sitePath($site)).' 2>/dev/null | wc -l', ['timeout' => 120]);
        $lines = preg_split('/\r?\n/', trim($run->stdout)) ?: [];
        $out = ['disk_used_bytes' => isset($lines[0]) && is_numeric(trim($lines[0])) ? (int) trim($lines[0]) : null, 'disk_limit_bytes' => null, 'traffic_used_bytes' => null, 'traffic_limit_bytes' => null, 'inodes_used' => isset($lines[1]) && is_numeric(trim($lines[1])) ? (int) trim($lines[1]) : null, 'measured_at' => now()->toIso8601String()];
        $this->cache->put($key, $out, 600);

        return $out;
    }

    public function nodeProjects(ResourceRef $site): array
    {
        $result = $this->post('/project/nodejs/get_project_list', ['p' => 1, 'limit' => 100], 'node.list');
        $out = [];
        $root = $this->sitePath($site);
        foreach ((array) ($result['data'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $path = (string) ($row['path'] ?? ($row['project_cwd'] ?? ''));
            if (! str_starts_with($path, $root)) {
                continue; // projects of other sites on the shared node are never listed
            }
            $cfg = is_array($row['project_config'] ?? null) ? $row['project_config'] : (is_string($row['project_config'] ?? null) ? (array) json_decode((string) $row['project_config'], true) : []);
            $out[] = ['remote_id' => (string) ($row['name'] ?? ($row['project_name'] ?? '')), 'name' => (string) ($row['name'] ?? ($row['project_name'] ?? '')), 'path' => $path, 'port' => isset($cfg['port']) ? (int) $cfg['port'] : (isset($row['port']) ? (int) $row['port'] : null), 'state' => ! empty($row['run']) || ($row['status'] ?? '') === 'running' ? 'running' : 'stopped', 'version' => $cfg['project_version'] ?? ($row['version'] ?? null), 'domains' => array_values((array) ($cfg['domains'] ?? []))];
        }

        return $out;
    }

    public function createNodeProject(ResourceRef $site, array $spec): ProviderResult
    {
        $root = $this->sitePath($site);
        $path = rtrim($root.'/'.trim(str_replace('\\', '/', (string) ($spec['path'] ?? '')), '/'), '/');
        if (str_contains($path, '..')) {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'The project path must stay inside the site root');
        }
        $name = Naming::scoped($site->serviceId, (string) $spec['name'], 40);
        $this->post('/project/nodejs/create_project', [
            'project_cwd' => $path, 'project_name' => $name, 'project_script' => (string) $spec['script'], 'run_user' => 'www', 'port' => (int) $spec['port'], 'project_version' => (string) ($spec['version'] ?? ''),
            'bind_extranet' => 0, 'domains' => json_encode(array_values((array) ($spec['domains'] ?? []))), 'env' => json_encode((array) ($spec['env'] ?? [])), 'is_power_on' => 1, 'run_type' => 'script',
        ], 'node.create', true);

        return ProviderResult::completed(new ResourceRef('node_project', $name, $this->instance->key, ['path' => $path], $site->serviceId), ['created' => true, 'name' => $name]);
    }

    public function nodeProjectAction(ResourceRef $site, string $remoteId, string $action): ProviderResult
    {
        if (collect($this->nodeProjects($site))->firstWhere('remote_id', $remoteId) === null) {
            throw new ProviderException('aapanel', ProviderErrorCode::NOT_FOUND, 'The Node project does not belong to this site');
        }
        $endpoint = match ($action) {
            'start' => 'start_project', 'stop' => 'stop_project', 'restart' => 'restart_project', 'delete' => 'remove_project',
            default => throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, "Unknown project action {$action}"),
        };
        $this->post('/project/nodejs/'.$endpoint, ['project_name' => $remoteId], 'node.'.$action, true);

        return ProviderResult::completed($action === 'delete' ? null : new ResourceRef('node_project', $remoteId, $this->instance->key, [], $site->serviceId), ['action' => $action]);
    }

    /** Reverse proxies through the panel (GetProxyList / CreateProxy / RemoveProxy); the customer sees name, path and upstream, never the vhost file. */
    public function listProxies(ResourceRef $site): array
    {
        $rows = $this->post('/site?action=GetProxyList', ['sitename' => (string) ($site->meta['name'] ?? '')], 'site.proxy.list');
        $out = [];
        foreach ((array) (is_array($rows) && isset($rows['data']) ? $rows['data'] : $rows) as $row) {
            if (! is_array($row) || ! isset($row['proxyname'])) {
                continue;
            }
            $out[] = ['remote_id' => (string) $row['proxyname'], 'name' => (string) $row['proxyname'], 'path' => (string) ($row['proxydir'] ?? '/'), 'target' => (string) ($row['proxysite'] ?? ''), 'enabled' => (int) ($row['type'] ?? 1) === 1, 'cache' => (int) ($row['cache'] ?? 0) === 1];
        }

        return $out;
    }

    public function createProxy(ResourceRef $site, array $spec): ProviderResult
    {
        $name = (string) $spec['name'];
        if (collect($this->listProxies($site))->firstWhere('name', $name) !== null) {
            return ProviderResult::completed(new ResourceRef('proxy', $name, $this->instance->key, ['name' => $name], $site->serviceId), alreadyExisted: true);
        }
        $this->post('/site?action=CreateProxy', [
            'cache' => ! empty($spec['cache']) ? 1 : 0, 'proxyname' => $name, 'cachetime' => 1, 'proxydir' => (string) ($spec['path'] ?? '/'), 'proxysite' => (string) $spec['target'], 'todomain' => (string) ($spec['host'] ?? '$host'),
            'type' => 1, 'sitename' => (string) ($site->meta['name'] ?? ''), 'subfilter' => '[{"sub1":"","sub2":""},{"sub1":"","sub2":""},{"sub1":"","sub2":""}]', 'advanced' => 0,
        ], 'site.proxy.create', true);

        return ProviderResult::completed(new ResourceRef('proxy', $name, $this->instance->key, ['name' => $name, 'path' => (string) ($spec['path'] ?? '/'), 'target' => (string) $spec['target']], $site->serviceId), ['created' => true]);
    }

    /** The whole list at once: aaPanel has no bulk call, so the difference is applied (remove what is gone, recreate what changed, add what is new). */
    public function setProxies(ResourceRef $site, array $items): ProviderResult
    {
        $wanted = [];
        foreach ($items as $spec) {
            $wanted[(string) $spec['name']] = ['name' => (string) $spec['name'], 'path' => (string) ($spec['path'] ?? '/'), 'target' => (string) $spec['target'], 'cache' => ! empty($spec['cache']), 'host' => $spec['host'] ?? null];
        }
        $current = collect($this->listProxies($site))->keyBy('name');
        $removed = $created = 0;
        foreach ($current as $name => $row) {
            $want = $wanted[$name] ?? null;
            if ($want === null || $want['path'] !== $row['path'] || rtrim($want['target'], '/') !== rtrim((string) $row['target'], '/') || $want['cache'] !== (bool) $row['cache']) {
                $this->deleteProxy($site, (string) $row['remote_id']);
                $removed++;
                if ($want !== null) {
                    $this->createProxy($site, $want);
                    $created++;
                }
                unset($wanted[$name]);
            } else {
                unset($wanted[$name]); // unchanged
            }
        }
        foreach ($wanted as $want) {
            $this->createProxy($site, $want);
            $created++;
        }

        return ProviderResult::completed($site, ['created' => $created, 'removed' => $removed, 'proxies' => array_values($items)]);
    }

    public function deleteProxy(ResourceRef $site, string $remoteId): ProviderResult
    {
        if (collect($this->listProxies($site))->firstWhere('remote_id', $remoteId) === null) {
            return ProviderResult::completed(new ResourceRef('proxy', $remoteId, $this->instance->key, [], $site->serviceId), alreadyExisted: true);
        }
        $this->post('/site?action=RemoveProxy', ['proxyname' => $remoteId, 'sitename' => (string) ($site->meta['name'] ?? '')], 'site.proxy.delete', true);

        return ProviderResult::completed(new ResourceRef('proxy', $remoteId, $this->instance->key, [], $site->serviceId), ['deleted' => true]);
    }

    public function defaultDocuments(ResourceRef $site): array
    {
        $r = $this->post('/site?action=GetIndex', ['id' => (int) $site->remoteId], 'site.index');
        $raw = trim(is_array($r) ? (string) ($r['msg'] ?? ($r['data'] ?? '')) : (string) $r, " 	
\"'"); // the live panel answers with a JSON-quoted string

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function setDefaultDocuments(ResourceRef $site, array $names): ProviderResult
    {
        $names = array_values($names) ?: ['index.php', 'index.html', 'index.htm', 'default.php', 'default.htm']; // an empty list = the panel's default order
        $this->post('/site?action=SetIndex', ['id' => (int) $site->remoteId, 'Index' => implode(',', $names)], 'site.index.set', true);

        return ProviderResult::completed($site, ['names' => $names]);
    }

    public function panelLoginUrl(ResourceRef $site): ?string
    {
        return null; // aaPanel has no API for temporary logins
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function transportAt(ResourceRef $site, string $root): FileTransport
    {
        return new AaPanelTransport(fn (string $path, array $params, string $action, bool $critical, array $files) => $this->post($path, $params, $action, $critical, $files), $this->shell($site), rtrim($root, '/'), 'www');
    }

    /** Node-level transport (backup folders, vhost configs) — never handed to customers. */
    private function nodeTransport(): FileTransport
    {
        $ref = new ResourceRef('node', $this->instance->key, $this->instance->key, [], null);

        return new AaPanelTransport(fn (string $path, array $params, string $action, bool $critical, array $files) => $this->post($path, $params, $action, $critical, $files), $this->shell($ref), '', 'www');
    }

    private function vhostPath(ResourceRef $site): string
    {
        return '/www/server/panel/vhost/nginx/'.($site->meta['name'] ?? '').'.conf';
    }

    private function securityIncludePath(ResourceRef $site): string
    {
        return '/www/server/panel/vhost/nginx/onhost/'.($site->meta['name'] ?? '').'.security.conf';
    }

    /** Write a vhost, test the configuration, roll back on failure — a broken vhost would take every site on the node down. */
    private function writeVhost(ResourceRef $site, string $content, string $previous): void
    {
        $path = $this->vhostPath($site);
        $transport = $this->nodeTransport();
        $transport->write($path, $content);
        $test = $this->shell($site)->run('nginx -t 2>&1', ['timeout' => 30]);
        if (! $test->ok()) {
            $transport->write($path, $previous);
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'The web server refused the configuration: '.trim($test->output()));
        }
    }

    private function reloadNginx(): void
    {
        try {
            $this->post('/system?action=ServiceAdmin', ['name' => 'nginx', 'type' => 'reload'], 'system.reload', true);
        } catch (ProviderException) {
            // the panel reloads on its next site change
        }
    }

    private function reloadPhp(ResourceRef $site): void
    {
        $version = $this->sitePhpVersion($site);
        if ($version === null) {
            return;
        }
        try {
            $this->post('/system?action=ServiceAdmin', ['name' => 'php-fpm-'.$version, 'type' => 'reload'], 'system.reload', false);
        } catch (ProviderException) {
            // .user.ini is re-read by PHP-FPM every user_ini.cache_ttl (5 min) anyway
        }
    }

    private function nginxBuild(ResourceRef $site): string
    {
        $key = "onhost:aapanel:nginx-build:{$this->instance->id}";
        $cached = $this->cache->get($key);
        if (is_string($cached)) {
            return $cached;
        }
        $build = $this->shell($site)->run('nginx -V 2>&1', ['timeout' => 20])->output();
        $this->cache->put($key, $build, 3600);

        return $build;
    }

    /** aaPanel's compact version ("83") of the site's PHP. */
    private function sitePhpVersion(ResourceRef $site): ?string
    {
        try {
            $result = $this->post('/site?action=GetSitePHPVersion', ['siteName' => (string) ($site->meta['name'] ?? '')], 'site.php.get');
            $v = is_array($result) ? (string) ($result['phpversion'] ?? '') : '';

            return preg_match('/^[1-9]\d{1,2}$/', $v) ? $v : null;
        } catch (ProviderException) {
            return null;
        }
    }

    private function sitePhpVersionDotted(ResourceRef $site): ?string
    {
        $v = $this->sitePhpVersion($site);

        return $v === null ? null : $v[0].'.'.substr($v, 1);
    }

    /** @return array{remote_id:string, name:string} */
    private function siteDatabase(ResourceRef $site, string $remoteId): array
    {
        $db = collect($this->listDatabases($site))->firstWhere('remote_id', $remoteId);
        if ($db === null) {
            throw new ProviderException('aapanel', ProviderErrorCode::NOT_FOUND, 'The database does not belong to this site');
        }

        return $db;
    }

    private function backupFile(ResourceRef $site, string $backupRemoteId): string
    {
        $backup = collect($this->listBackups($site))->firstWhere('remote_id', $backupRemoteId);
        $file = is_array($backup) ? (string) ($backup['meta']['filename'] ?? '') : '';
        if ($file === '') {
            throw new ProviderException('aapanel', ProviderErrorCode::NOT_FOUND, 'The backup does not belong to this site');
        }

        return $file;
    }
}
