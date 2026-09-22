<?php

declare(strict_types=1);

namespace Onhost\Providers\AaPanel;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Platform\ProviderHttp\ProviderRequest;
use Onhost\Platform\ProviderHttp\ProviderResponse;
use Onhost\Providers\Contracts\ActionPlan;
use Onhost\Providers\Contracts\ActualState;
use Onhost\Providers\Contracts\AsyncHandle;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\Naming;
use Onhost\Providers\Contracts\ProviderHealth;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\ResourceSpec;
use Onhost\Providers\Contracts\SelfProbing;
use Onhost\Providers\Contracts\TlsOptions;
use Onhost\Providers\Contracts\Usage;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;

/**
 * aaPanel Lifetime as the isolated Managed executor (blueprint §8). Legacy,
 * high-impact API: `request_token = md5(request_time . md5(api_key))`, IP allow-list,
 * responses that are sometimes `{status,msg}`, sometimes a bare string or a list.
 * Reachable only from the provisioning subnet; every response is redacted.
 */
final class AaPanelWebProvider implements SelfProbing, WebHostingProvider, WebToolsProvider
{
    use AaPanelTools;

    public function __construct(
        private readonly ProviderInstance $instance,
        private readonly array $credentials,
        private readonly ProviderHttpClient $http,
        private readonly CacheRepository $cache,
    ) {
        $this->http->configureBucket($instance->key, (int) ($instance->rate_limits['per_minute'] ?? 600), 60, 0.1);
    }

    public static function providerKey(): string
    {
        return 'aapanel';
    }

    public static function adapterVersion(): string
    {
        return '1.0.0';
    }

    public static function supportedVendorVersions(): array
    {
        return ['7.0.x', '7.1.x'];
    }

    public function capabilities(): array
    {
        return ['web.php' => true, 'web.database' => true, 'web.ssl_letsencrypt' => true, 'web.cron' => true, 'web.node' => 'managed_only', 'node.shared' => false, 'backup.restore' => 'panel_backup', 'multi_server' => false];
    }

    public function health(): ProviderHealth
    {
        $started = hrtime(true);
        try {
            $total = $this->post('/system?action=GetSystemTotal', [], 'system.total');
            $ms = (int) ((hrtime(true) - $started) / 1_000_000);
            $this->cache->put("onhost:aapanel:version:{$this->instance->id}", (string) ($total['version'] ?? ''), 3600);

            return new ProviderHealth(true, (string) ($total['version'] ?? null), $ms, ['load' => $total['load'] ?? null, 'mem_realused' => $total['memRealUsed'] ?? null, 'mem_total' => $total['memTotal'] ?? null]);
        } catch (ProviderException $e) {
            return ProviderHealth::down($e->getMessage(), (int) ((hrtime(true) - $started) / 1_000_000));
        }
    }

    public function vendorVersion(): ?string
    {
        $v = $this->cache->get("onhost:aapanel:version:{$this->instance->id}");

        return is_string($v) && $v !== '' ? $v : $this->instance->vendor_version;
    }

    public function provision(ResourceSpec $spec): ProviderResult
    {
        $domain = (string) $spec->get('domain');
        $existing = $this->findSite($domain);
        if ($existing !== null) {
            return ProviderResult::completed(new ResourceRef('site', (string) $existing['id'], $this->instance->key, ['name' => $existing['name'], 'path' => $existing['path']], $spec->serviceId), $existing, alreadyExisted: true);
        }
        $php = str_replace('.', '', (string) $spec->get('php_version', '8.3'));
        $path = "/www/wwwroot/{$domain}";
        $response = $this->post('/site?action=AddSite', [
            'webname' => json_encode(['domain' => $domain, 'domainlist' => (array) $spec->get('aliases', []), 'count' => 0]),
            'path' => $path, 'type_id' => 0, 'type' => 'PHP', 'version' => $php, 'port' => 80, 'ps' => "onhost:{$spec->serviceId}",
            'ftp' => 'false', 'sql' => 'false', 'codeing' => 'utf8',
        ], 'site.add', true);
        if (! isset($response['siteId'])) {
            throw new ProviderException('aapanel', ProviderErrorCode::PROVIDER_BUG, 'AddSite returned no siteId', context: ['response' => $response]);
        }
        $siteId = (int) $response['siteId'];

        return ProviderResult::completed(new ResourceRef('site', (string) $siteId, $this->instance->key, ['name' => $domain, 'path' => $path], $spec->serviceId), ['site_id' => $siteId, 'path' => $path]);
    }

    public function getActualState(ResourceRef $ref): ActualState
    {
        $site = $this->getSite((int) $ref->remoteId, isset($ref->meta['name']) ? (string) $ref->meta['name'] : null);
        if ($site === null) {
            return ActualState::missing();
        }
        $php = null;
        try {
            $versions = $this->post('/site?action=GetSitePHPVersion', ['siteName' => $site['name']], 'site.php.get');
            $php = is_array($versions) ? ($versions['phpversion'] ?? null) : null;
        } catch (ProviderException) {
            // optional
        }

        return new ActualState(true, [
            'domain' => $site['name'], 'path' => $site['path'], 'status' => $site['status'] ?? null, 'php_version' => $php === null ? null : (strlen((string) $php) === 2 ? $php[0].'.'.$php[1] : (string) $php),
            'ssl' => isset($site['ssl']) && $site['ssl'] !== -1, 'edate' => $site['edate'] ?? null,
        ], ((string) ($site['status'] ?? '1')) === '1' ? 'active' : 'suspended', now()->toISOString());
    }

    public function reconcile(ResourceSpec $spec, ActualState $actual): ActionPlan
    {
        if (! $actual->exists) {
            return new ActionPlan([ActionPlan::drift('existence', 'present', 'missing', 'ONHOST_MANAGED', 'SECURITY_SUSPICIOUS')]);
        }
        $drifts = [];
        $php = $spec->get('php_version');
        if ($php !== null && $actual->get('php_version') !== null && (string) $actual->get('php_version') !== (string) $php) {
            $drifts[] = ActionPlan::drift('php_version', $php, $actual->get('php_version'), 'CUSTOMER_MUTABLE', 'EXPECTED');
        }
        if ($spec->get('active', true) && $actual->status !== 'active') {
            $drifts[] = ActionPlan::drift('active', true, false, 'ONHOST_MANAGED', 'REQUIRES_APPROVAL');
        }

        return new ActionPlan($drifts);
    }

    public function resize(ResourceRef $ref, ResourceSpec $spec): ProviderResult
    {
        // aaPanel runs ONE PHP-FPM pool per PHP version for the whole node. `SetPHPMaxChildren` takes a version, not a site: a plan
        // change of one customer used to set `pm.max_children` for every site of every customer on that PHP version — a downgrade
        // to a two-worker plan throttled the node. The pool is the operator's (node sizing); a plan change touches nothing on the
        // node here. The plan's limits live in the service's entitlements, where the platform checks them before every action.
        return ProviderResult::completed($ref, ['php_workers' => $spec->get('entitlements.php_workers'), 'applied' => false, 'reason' => 'aapanel has one PHP-FPM pool per PHP version for the whole node; it is not resized per site']);
    }

    public function suspend(ResourceRef $ref): ProviderResult
    {
        $this->post('/site?action=SiteStop', ['id' => (int) $ref->remoteId, 'name' => $ref->meta['name'] ?? ''], 'site.stop', true);

        return ProviderResult::completed($ref, ['suspended' => true]);
    }

    public function resume(ResourceRef $ref): ProviderResult
    {
        $this->post('/site?action=SiteStart', ['id' => (int) $ref->remoteId, 'name' => $ref->meta['name'] ?? ''], 'site.start', true);

        return ProviderResult::completed($ref, ['resumed' => true]);
    }

    public function terminate(ResourceRef $ref): ProviderResult
    {
        $cron = $this->dropCron($ref); // first: the jobs are ours to remove whether the site is still there or not
        $apps = $this->dropNodeProjects($ref); // and the site's apps, which listen on a port and answer for its domains
        if ($this->getSite((int) $ref->remoteId, isset($ref->meta['name']) ? (string) $ref->meta['name'] : null) === null) {
            return ProviderResult::completed(null, ['already_deleted' => true, 'cron_removed' => $cron, 'apps_removed' => $apps], alreadyExisted: true);
        }
        $this->post('/site?action=DeleteSite', ['id' => (int) $ref->remoteId, 'webname' => $ref->meta['name'] ?? '', 'path' => 1, 'database' => 1, 'ftp' => 1], 'site.delete', true);

        return ProviderResult::completed(null, ['deleted' => true, 'cron_removed' => $cron, 'apps_removed' => $apps]);
    }

    /**
     * A site's Node.js apps belong to the panel, not to the site: `DeleteSite` takes the files and leaves the project.
     * It is started at every boot (`is_power_on`), it keeps its PORT reserved, and the panel keeps routing the domains
     * it was given to that port — so the next customer whose app is given the same port would receive this customer's
     * traffic. A running process does not even notice its files were deleted; it goes on answering from memory.
     *
     * Stopped first, then removed (H505: the listener is released only once it has stopped). Found by the site root,
     * so this also cleans up after a site that was deleted in the panel by hand.
     *
     * @return int how many apps were removed
     */
    private function dropNodeProjects(ResourceRef $ref): int
    {
        $removed = 0;
        foreach ($this->nodeProjects($ref) as $project) {
            $name = (string) ($project['remote_id'] ?? '');
            if ($name === '') {
                continue;
            }
            if (($project['state'] ?? '') === 'running') {
                $this->post('/project/nodejs/stop_project', ['project_name' => $name], 'node.stop', true);
            }
            $this->post('/project/nodejs/remove_project', ['project_name' => $name], 'node.delete', true);
            $removed++;
        }

        return $removed;
    }

    /**
     * The minute and the hour aaPanel will really use — or a refusal.
     *
     * aaPanel's scheduler does not take a cron line: it takes a type (`day`, `hour-n`, `week`, …) with a single hour
     * and minute. The adapter read the first two fields and threw the rest away, so `0 3 * * 1` (Mondays) was created
     * as every DAY at 03:00, and a step like "every fifth minute" as every hour at :00 — the site's own panel then
     * showed the customer the schedule aaPanel had, not the one they had asked for, and the difference was never
     * mentioned anywhere. Everything the panel cannot express is refused now instead of quietly becoming something
     * else; a step or a weekday belongs on a panel that can run it.
     *
     * @return array{0:string,1:string} minute, hour
     */
    private function cronFields(string $schedule): array
    {
        [$minute, $hour, $dom, $month, $dow] = array_pad(preg_split('/\s+/', trim($schedule)) ?: [], 5, '*');
        $every = fn (string $f) => $f === '*';
        $fixed = fn (string $f) => preg_match('/^\d{1,2}$/', $f) === 1;
        if ($every($dom) && $every($month) && $every($dow) && $fixed($minute) && ($every($hour) || $fixed($hour))) {
            return [$minute, $hour];
        }
        throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'This panel runs a job once an hour ("M * * * *") or once a day ("M H * * *"); it cannot express '.mb_substr($schedule, 0, 40));
    }

    /**
     * aaPanel's crontab belongs to the node, not to the site: `DeleteSite` takes the files, the databases and the FTP
     * users, and leaves every scheduled job behind. A cancelled site's jobs went on firing on the node for ever — and,
     * before the body was confined, as root. They are recognised by their name (`onhost:<service>:…`), so this cleans
     * up whether the site is still there or was deleted in the panel by hand.
     *
     * @return int how many jobs were removed
     */
    private function dropCron(ResourceRef $ref): int
    {
        $removed = 0;
        foreach ($this->listCron($ref) as $job) {
            $this->post('/crontab?action=DelCrontab', ['id' => (int) $job['remote_id']], 'cron.delete', true);
            $removed++;
        }

        return $removed;
    }

    /**
     * The filesystem the sites live on, out of what `GetDiskInfo` lists. The panel answers human sizes („1.8T“,
     * „500G“, „12%“), one entry per mount; `/www` is the one that matters when the node has it, otherwise the root.
     *
     * @param  mixed  $disks  the panel's answer
     * @return array{pct:?float, total_gb:?float, used_gb:?float}
     */
    public static function rootDisk(mixed $disks): array
    {
        $rows = is_array($disks) ? array_values(array_filter($disks, 'is_array')) : [];
        $pick = null;
        foreach ($rows as $row) {
            $path = rtrim((string) ($row['path'] ?? ''), '/');
            if ($path === '/www') {
                $pick = $row;
                break;
            }
            if ($pick === null || $path === '') {
                $pick ??= $row;
            }
        }
        $size = is_array($pick['size'] ?? null) ? array_values($pick['size']) : [];
        $bytes = static function (mixed $value): ?float {
            if (! preg_match('/^\s*([\d.,]+)\s*([KMGTP]?)/i', (string) $value, $m)) {
                return null;
            }
            $number = (float) str_replace(',', '.', $m[1]);
            $factor = ['' => 1 / 1024 ** 3, 'K' => 1 / 1024 ** 2, 'M' => 1 / 1024, 'G' => 1, 'T' => 1024, 'P' => 1024 ** 2];

            return $number * ($factor[strtoupper($m[2])] ?? 1);
        };

        return [
            'pct' => isset($size[3]) && preg_match('/([\d.]+)\s*%/', (string) $size[3], $m) ? (float) $m[1] : null,
            'total_gb' => isset($size[0]) ? $bytes($size[0]) : null,
            'used_gb' => isset($size[1]) ? $bytes($size[1]) : null,
        ];
    }

    public function usage(ResourceRef $ref, ?string $periodStart = null, ?string $periodEnd = null): Usage
    {
        $total = $this->post('/system?action=GetSystemTotal', [], 'system.total');
        $disk = $this->post('/system?action=GetDiskInfo', [], 'system.disk');

        return new Usage([
            'node_cpu_pct' => isset($total['cpuRealUsed']) ? (float) $total['cpuRealUsed'] : null,
            'node_mem_pct' => isset($total['memRealUsed'], $total['memTotal']) && (float) $total['memTotal'] > 0 ? round((float) $total['memRealUsed'] / (float) $total['memTotal'] * 100, 2) : null,
            'node_disk_pct' => is_array($disk) && isset($disk[0]['size'][3]) ? (float) rtrim((string) $disk[0]['size'][3], '%') : null,
        ], now()->toISOString());
    }

    public function createDatabase(ResourceRef $site, array $spec): ProviderResult
    {
        $name = (string) $spec['name'];
        $list = $this->post('/data?action=getData&table=databases', ['limit' => 50, 'p' => 1, 'search' => $name], 'db.list');
        $existing = collect((array) ($list['data'] ?? []))->firstWhere('name', $name);
        if (is_array($existing)) {
            return ProviderResult::completed(new ResourceRef('database', (string) $existing['id'], $this->instance->key, ['name' => $name], $site->serviceId), alreadyExisted: true);
        }
        $this->post('/database?action=AddDatabase', ['name' => $name, 'codeing' => $spec['charset'] ?? 'utf8mb4', 'db_user' => $spec['user'], 'password' => $spec['password'], 'dtype' => 'MySQL', 'dataAccess' => '127.0.0.1', 'address' => '127.0.0.1', 'ps' => "onhost:{$site->serviceId}"], 'db.add', true);
        $list = $this->post('/data?action=getData&table=databases', ['limit' => 50, 'p' => 1, 'search' => $name], 'db.list');
        $created = collect((array) ($list['data'] ?? []))->firstWhere('name', $name);

        return ProviderResult::completed(new ResourceRef('database', (string) ($created['id'] ?? $name), $this->instance->key, ['name' => $name], $site->serviceId), ['created' => true]);
    }

    public function setPhpVersion(ResourceRef $site, string $version): ProviderResult
    {
        $this->post('/site?action=SetPHPVersion', ['siteName' => $site->meta['name'] ?? '', 'version' => str_replace('.', '', $version)], 'site.php.set', true);

        return ProviderResult::completed($site, ['php_version' => $version]);
    }

    public function issueCertificate(ResourceRef $site, array $domains): ProviderResult
    {
        try {
            $result = $this->acmeApply($site, $domains);
        } catch (ProviderException $e) {
            if (in_array($e->errorCode, [ProviderErrorCode::TRANSIENT, ProviderErrorCode::NOT_FOUND], true)) {
                throw $e;
            }
            // the CA refused the challenge (DNS not pointing here yet, HTTP challenge unreachable): the site works over HTTP, the certificate is retried later
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'Certificate verification failed: '.mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 300), previous: $e);
        }
        if (! isset($result['cert'], $result['private'])) {
            throw new ProviderException('aapanel', ProviderErrorCode::TRANSIENT, 'ACME issuance did not return a certificate yet; retry after DNS propagation', context: ['response' => is_array($result) ? array_keys($result) : $result]);
        }
        $this->post('/site?action=SetSSL', ['type' => 1, 'siteName' => $site->meta['name'] ?? '', 'key' => $result['private'], 'csr' => $result['cert']], 'site.ssl.set', true);

        return ProviderResult::completed($site, ['domains' => $domains, 'issued' => true]);
    }

    /** @return array<string,mixed>|string */
    private function acmeApply(ResourceRef $site, array $domains): array|string
    {
        return $this->post('/acme?action=apply_cert_api', ['domains' => json_encode(array_values($domains)), 'auth_type' => 'http', 'auth_to' => (int) $site->remoteId, 'auto_wildcard' => 0, 'id' => (int) $site->remoteId], 'acme.apply', true);
    }

    public function forceHttps(ResourceRef $site, bool $enabled): ProviderResult
    {
        $this->post($enabled ? '/site?action=HttpToHttps' : '/site?action=CloseToHttps', ['siteName' => $site->meta['name'] ?? ''], 'site.https', true);

        return ProviderResult::completed($site, ['https_forced' => $enabled]);
    }

    public function createCron(ResourceRef $site, array $job): ProviderResult
    {
        [$minute, $hour] = $this->cronFields((string) $job['schedule']);
        $name = Naming::cronLabel($site->serviceId, $job['label'] ?? null);
        // aaPanel's scheduler is host-wide: hourly jobs run every hour at :minute, daily ones at hour:minute
        $this->post('/crontab?action=AddCrontab', [
            'name' => $name, 'type' => $hour === '*' ? 'hour-n' : 'day', 'where1' => $hour === '*' ? '1' : '', 'hour' => $hour === '*' ? 0 : (int) $hour, 'minute' => $minute === '*' ? 0 : (int) $minute,
            'week' => '', 'sType' => 'toShell', 'sName' => '', 'sBody' => $this->cronBody($site, (string) $job['command']), 'backupTo' => '', 'save' => '', 'urladdress' => '',
        ], 'cron.add', true);
        $created = collect($this->listCron($site))->firstWhere('label', substr($name, strlen('onhost:'.$site->serviceId.':')));

        return ProviderResult::completed(new ResourceRef('cron', (string) ($created['remote_id'] ?? $name), $this->instance->key, ['name' => $name], $site->serviceId), ['created' => true]);
    }

    public function listCron(ResourceRef $site): array
    {
        $out = [];
        foreach ((array) $this->post('/crontab?action=GetCrontab', [], 'cron.list') as $row) {
            $name = (string) ($row['name'] ?? '');
            if (! Naming::ownsCron($site->serviceId, $name)) {
                continue;
            }
            $hour = (string) ($row['where_hour'] ?? '*');
            $minute = (string) ($row['where_minute'] ?? '0');
            $daily = ($row['type'] ?? 'day') === 'day' || preg_match('/day/i', (string) ($row['type'] ?? '')) === 1; // the panel lists the type as a phrase ("Every Day", "Every 1 Hours")
            $body = (string) ($row['sBody'] ?? '');
            $out[] = ['remote_id' => (string) $row['id'], 'schedule' => ($daily ? "{$minute} {$hour} * * *" : "{$minute} * * * *"), 'command' => self::cronCommandOf($body), 'label' => substr($name, strlen('onhost:'.$site->serviceId.':')), 'active' => (int) ($row['status'] ?? 1) === 1, 'confined' => self::cronCommandOf($body) !== $body];
        }

        return $out;
    }

    public function deleteCron(ResourceRef $site, string $remoteId): ProviderResult
    {
        if (collect($this->listCron($site))->firstWhere('remote_id', $remoteId) === null) {
            return ProviderResult::completed(null, ['deleted' => false], alreadyExisted: true);
        }
        $this->post('/crontab?action=DelCrontab', ['id' => (int) $remoteId], 'cron.delete', true);

        return ProviderResult::completed(null, ['deleted' => true]);
    }

    public function siteFeatures(): array
    {
        return [
            'php' => true, 'databases' => true, 'ftp' => true, 'ssl' => true, 'https' => true, 'cron' => true, 'logs' => true, 'backups' => true, 'restore' => true,
            'subdomains' => true, 'redirects' => true, 'ssh' => false, 'mail' => false, 'file_manager' => true, 'usage' => true,
            // extended tabs: file manager, rewrite rules, site password and one-click apps come from the panel API; no per-site DB users or statistics
            'errpages' => false, 'directives' => true, 'protected' => true, 'db_users' => false, 'stats' => false, 'ssl_upload' => true, 'files' => true, 'apps' => true, 'db_admin' => (bool) $this->instance->option('phpmyadmin_url'),
            // tools (WebToolsProvider): the panel API plus the node shell — terminal/WP-CLI instead of SSH keys, restore, exports, security rules, HTTP/3, Node projects
            'terminal' => true, 'php_settings' => true, 'security' => true, 'rate_limit' => true, 'http3' => true, 'cron_edit' => true, 'cron_logs' => true, 'db_export' => true, 'db_access' => true,
            'backup_download' => true, 'backup_delete' => true, 'backup_on_demand' => true, 'files_advanced' => true, 'quotas' => true, 'node_projects' => true, 'staging' => true, 'deploy' => true, 'wordpress' => true, 'hsts' => true, 'panel_login' => false, 'proxy' => true, 'default_docs' => true,
        ];
    }

    /** What this panel really answers (SelfProbing): the backup table behind restores and database exports, and the file API behind "pack the whole site". */
    public function probes(?ResourceRef $anyResource = null): array
    {
        $site = $anyResource !== null && $anyResource->remoteType === 'site' ? $anyResource : null;
        if ($site === null) {
            return ['backup_api' => 'skipped: no site on this instance yet', 'files_api' => 'skipped: no site on this instance yet'];
        }

        return [
            'backup_api' => $this->probe(fn () => $this->listBackups($site)),
            'files_api' => $this->probe(fn () => $this->transport($site)->list('')),
        ];
    }

    /** One read-only probe: `ok`, or what the panel said. */
    private function probe(callable $ask): string
    {
        try {
            $ask();

            return 'ok';
        } catch (ProviderException $e) {
            return (in_array($e->errorCode, [ProviderErrorCode::AUTH, ProviderErrorCode::VALIDATION], true) ? 'refused: ' : 'missing: ').mb_substr($e->getMessage(), 0, 160);
        }
    }

    public function phpVersions(): array
    {
        $key = "onhost:aapanel:php:{$this->instance->id}";
        $cached = $this->cache->get($key);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }
        $versions = [];
        foreach ((array) $this->post('/site?action=GetPHPVersion', [], 'site.php.versions') as $row) {
            $v = (string) ($row['version'] ?? '');
            if (! preg_match('/^[1-9]\d{1,2}$/', $v)) { // "00" = static site, "other" = not PHP
                continue;
            }
            $versions[] = $v[0].'.'.substr($v, 1);
        }
        $versions = array_values(array_unique($versions));
        natsort($versions);
        $versions = array_values($versions);
        if ($versions !== []) {
            $this->cache->put($key, $versions, 3600);
        }

        return $versions;
    }

    public function listDatabases(ResourceRef $site): array
    {
        $prefix = Naming::prefix($site->serviceId);
        $out = [];
        foreach ((array) ($this->post('/data?action=getData&table=databases', ['limit' => 200, 'p' => 1, 'search' => $prefix], 'db.list')['data'] ?? []) as $row) {
            if (! str_starts_with((string) ($row['name'] ?? ''), $prefix.'_')) { // the prefix and its separator: `oh1yz8n6_…`
                continue;
            }
            $out[] = ['remote_id' => (string) $row['id'], 'name' => (string) $row['name'], 'user' => $row['username'] ?? null, 'charset' => $row['codeing'] ?? null, 'size_bytes' => null];
        }

        return $out;
    }

    public function deleteDatabase(ResourceRef $site, string $remoteId): ProviderResult
    {
        $row = collect($this->listDatabases($site))->firstWhere('remote_id', $remoteId);
        if ($row === null) {
            return ProviderResult::completed(null, ['deleted' => false], alreadyExisted: true);
        }
        $this->post('/database?action=DeleteDatabase', ['id' => (int) $remoteId, 'name' => $row['name']], 'db.delete', true);

        return ProviderResult::completed(null, ['deleted' => true]);
    }

    public function certificate(ResourceRef $site): array
    {
        try {
            $ssl = $this->post('/site?action=GetSSL', ['siteName' => $site->meta['name'] ?? ''], 'site.ssl.get');
        } catch (ProviderException $e) {
            if ($e->errorCode === ProviderErrorCode::AUTH) {
                throw $e;
            }
            $ssl = []; // the panel answers "unknown error" for a site without any certificate yet
        }
        $ssl = is_array($ssl) ? $ssl : [];
        $cert = (array) ($ssl['cert_data'] ?? []);
        $issuer = (string) ($cert['issuer'] ?? '');

        return [
            'issued' => ! empty($ssl['status']), 'letsencrypt' => (int) ($ssl['type'] ?? 0) === 1 || str_contains(strtolower($issuer), "let's encrypt"),
            'expires_at' => $cert['notAfter'] ?? null, 'issuer' => $issuer !== '' ? $issuer : null, 'domains' => array_values((array) ($cert['dns'] ?? [])), 'https_forced' => ! empty($ssl['httpTohttps']),
        ];
    }

    public function createFtpAccount(ResourceRef $site, array $account): ProviderResult
    {
        $existing = collect($this->listFtpAccounts($site))->firstWhere('user', $account['user']);
        if ($existing !== null) {
            return ProviderResult::completed(new ResourceRef('ftp', $existing['remote_id'], $this->instance->key, ['user' => $account['user']], $site->serviceId), alreadyExisted: true);
        }
        $this->post('/ftp?action=AddUser', [
            'ftp_username' => $account['user'], 'ftp_password' => $account['password'], 'path' => $account['path'] ?? ($site->meta['path'] ?? '/www/wwwroot/'.($site->meta['name'] ?? '')), 'ps' => "onhost:{$site->serviceId}",
        ], 'ftp.add', true);
        $created = collect($this->listFtpAccounts($site))->firstWhere('user', $account['user']);

        return ProviderResult::completed(new ResourceRef('ftp', (string) ($created['remote_id'] ?? $account['user']), $this->instance->key, ['user' => $account['user']], $site->serviceId), ['created' => true]);
    }

    public function listFtpAccounts(ResourceRef $site): array
    {
        $prefix = Naming::prefix($site->serviceId);
        $out = [];
        foreach ((array) ($this->post('/data?action=getData&table=ftps', ['limit' => 200, 'p' => 1, 'search' => $prefix], 'ftp.list')['data'] ?? []) as $row) {
            if (! str_starts_with((string) ($row['name'] ?? ''), $prefix.'_')) { // the prefix and its separator: `oh1yz8n6_…`
                continue;
            }
            $out[] = ['remote_id' => (string) $row['id'], 'user' => (string) $row['name'], 'path' => $row['path'] ?? null, 'active' => (int) ($row['status'] ?? 1) === 1];
        }

        return $out;
    }

    public function deleteFtpAccount(ResourceRef $site, string $remoteId): ProviderResult
    {
        $row = collect($this->listFtpAccounts($site))->firstWhere('remote_id', $remoteId);
        if ($row === null) {
            return ProviderResult::completed(null, ['deleted' => false], alreadyExisted: true);
        }
        $this->post('/ftp?action=DeleteUser', ['id' => (int) $remoteId, 'username' => $row['user']], 'ftp.delete', true);

        return ProviderResult::completed(null, ['deleted' => true]);
    }

    public function setFtpPassword(ResourceRef $site, string $remoteId, string $password): ProviderResult
    {
        $row = collect($this->listFtpAccounts($site))->firstWhere('remote_id', $remoteId);
        if ($row === null) {
            throw new ProviderException('aapanel', ProviderErrorCode::NOT_FOUND, 'FTP account not found on this site');
        }
        $this->post('/ftp?action=SetUserPassword', ['id' => (int) $remoteId, 'ftp_username' => $row['user'], 'new_password' => $password], 'ftp.password', true);

        return ProviderResult::completed(new ResourceRef('ftp', $remoteId, $this->instance->key, ['user' => $row['user']], $site->serviceId), ['password_changed' => true]);
    }

    public function setFtpAccountActive(ResourceRef $site, string $remoteId, bool $active): ProviderResult
    {
        $row = collect($this->listFtpAccounts($site))->firstWhere('remote_id', $remoteId);
        if ($row === null) {
            throw new ProviderException('aapanel', ProviderErrorCode::NOT_FOUND, 'FTP account not found on this site');
        }
        if ((bool) $row['active'] !== $active) {
            $this->post('/ftp?action=SetStatus', ['id' => (int) $remoteId, 'username' => $row['user'], 'status' => $active ? 1 : 0], 'ftp.status', true);
        }

        return ProviderResult::completed(new ResourceRef('ftp', $remoteId, $this->instance->key, ['user' => $row['user']], $site->serviceId), ['active' => $active]);
    }

    public function addSubdomain(ResourceRef $site, array $subdomain): ProviderResult
    {
        $domain = strtolower((string) $subdomain['domain']);
        if (collect($this->listSubdomains($site))->firstWhere('domain', $domain) !== null) {
            return ProviderResult::completed(new ResourceRef('site_domain', $domain, $this->instance->key, [], $site->serviceId), alreadyExisted: true);
        }
        $this->post('/site?action=AddDomain', ['id' => (int) $site->remoteId, 'webname' => $site->meta['name'] ?? '', 'domain' => $domain], 'site.domain.add', true);
        $created = collect($this->listSubdomains($site))->firstWhere('domain', $domain);

        return ProviderResult::completed(new ResourceRef('site_domain', (string) ($created['remote_id'] ?? $domain), $this->instance->key, ['domain' => $domain], $site->serviceId), ['added' => true]);
    }

    public function listSubdomains(ResourceRef $site): array
    {
        $primary = strtolower((string) ($site->meta['name'] ?? ''));
        $out = [];
        foreach ((array) ($this->post('/data?action=getData&table=domain', ['limit' => 200, 'p' => 1, 'search' => (int) $site->remoteId], 'site.domain.list')['data'] ?? []) as $row) {
            if ((int) ($row['pid'] ?? 0) !== (int) $site->remoteId || strtolower((string) ($row['name'] ?? '')) === $primary) {
                continue;
            }
            $out[] = ['remote_id' => (string) $row['id'], 'domain' => (string) $row['name'], 'path' => null, 'port' => (int) ($row['port'] ?? 80)];
        }

        return $out;
    }

    public function removeSubdomain(ResourceRef $site, string $remoteId): ProviderResult
    {
        $row = collect($this->listSubdomains($site))->firstWhere('remote_id', $remoteId);
        if ($row === null) {
            return ProviderResult::completed(null, ['removed' => false], alreadyExisted: true);
        }
        $this->post('/site?action=DelDomain', ['id' => (int) $site->remoteId, 'webname' => $site->meta['name'] ?? '', 'domain' => $row['domain'], 'port' => $row['port'] ?? 80], 'site.domain.delete', true);

        return ProviderResult::completed(null, ['removed' => true]);
    }

    public function setRedirect(ResourceRef $site, array $redirect): ProviderResult
    {
        $name = (string) ($site->meta['name'] ?? '');
        $current = $this->redirect($site);
        if ($current['target'] !== null) {
            $this->post('/site?action=DeleteRedirect', ['sitename' => $name, 'redirectname' => 'onhost'], 'site.redirect.delete', true);
        }
        $target = trim((string) ($redirect['target'] ?? ''));
        if ($target === '') {
            return ProviderResult::completed($site, ['redirect' => null]);
        }
        $this->post('/site?action=CreateNewRedirect', [
            'sitename' => $name, 'redirectname' => 'onhost', 'tourl' => $target, 'domainorpath' => 'domain', 'redirectdomain' => json_encode([$name]), 'redirectpath' => '',
            'holdpath' => 1, 'redirecttype' => (string) ($redirect['type'] ?? '301'), 'type' => 1,
        ], 'site.redirect.add', true);

        return ProviderResult::completed($site, ['redirect' => $target, 'type' => (string) ($redirect['type'] ?? '301')]);
    }

    public function redirect(ResourceRef $site): array
    {
        foreach ((array) $this->post('/site?action=GetRedirectList', ['sitename' => $site->meta['name'] ?? ''], 'site.redirect.list') as $row) {
            if (is_array($row) && ($row['redirectname'] ?? '') === 'onhost') {
                return ['target' => (string) ($row['tourl'] ?? ''), 'type' => (string) ($row['redirecttype'] ?? '301')];
            }
        }

        return ['target' => null, 'type' => null];
    }

    public function awaitStatus(AsyncHandle $handle): AsyncStatus
    {
        return AsyncStatus::succeeded(['meta' => $handle->meta]);
    }

    public function tailLog(ResourceRef $site, string $log = 'access', int $lines = 200): array
    {
        $name = $site->meta['name'] ?? '';
        $path = $log === 'error' ? "/www/wwwlogs/{$name}.error.log" : "/www/wwwlogs/{$name}.log";
        $result = $this->post('/files?action=GetFileBody', ['path' => $path], 'files.body');
        $body = is_array($result) ? (string) ($result['data'] ?? '') : (string) $result;
        $all = preg_split('/\r?\n/', trim($body)) ?: [];

        return array_slice($all, -$lines);
    }

    public function nodeLoad(?string $node = null): array
    {
        $total = $this->post('/system?action=GetSystemTotal', [], 'system.total');
        $sites = $this->post('/data?action=getData&table=sites', ['limit' => 1, 'p' => 1], 'sites.count');
        // how full the node's own disk is: the placement rule that keeps a shared node from filling up needs a number,
        // and this one used to be null, so the rule never fired and a nearly full node went on taking new sites
        $disk = self::rootDisk($this->post('/system?action=GetDiskInfo', [], 'system.disk'));

        return [
            'cpu_pct' => isset($total['cpuRealUsed']) ? (float) $total['cpuRealUsed'] : null,
            'mem_pct' => isset($total['memRealUsed'], $total['memTotal']) && (float) $total['memTotal'] > 0 ? round((float) $total['memRealUsed'] / (float) $total['memTotal'] * 100, 2) : null,
            'disk_pct' => $disk['pct'], 'disk_total_gb' => $disk['total_gb'], 'disk_used_gb' => $disk['used_gb'],
            'load' => isset($total['load']['one']) ? (float) $total['load']['one'] : null,
            'sites' => isset($sites['page']) && preg_match('/共(\d+)|of (\d+)|(\d+) /', (string) $sites['page'], $m) ? (int) ($m[1] ?: ($m[2] ?? ($m[3] ?? 0))) : (is_array($sites['data'] ?? null) ? count($sites['data']) : null),
        ];
    }

    public function backup(ResourceRef $ref, array $policy): ProviderResult
    {
        $this->post('/site?action=ToBackup', ['id' => (int) $ref->remoteId], 'site.backup', true);

        return ProviderResult::completed($ref, ['requested' => true]);
    }

    public function listBackups(ResourceRef $ref): array
    {
        $list = $this->post('/data?action=getData&table=backup', ['limit' => 50, 'p' => 1, 'search' => (int) $ref->remoteId, 'type' => 0], 'backup.list');
        $out = [];
        foreach ((array) ($list['data'] ?? []) as $b) {
            $out[] = ['remote_id' => (string) $b['id'], 'created_at' => (string) ($b['addtime'] ?? ''), 'size_bytes' => isset($b['size']) ? (int) $b['size'] : null, 'verified' => null, 'protected' => null, 'meta' => ['filename' => $b['filename'] ?? null]];
        }

        return $out;
    }

    public function restore(ResourceRef $ref, string $backupRemoteId, array $options = []): ProviderResult
    {
        // aaPanel has no restore endpoint for site archives: the archive is unpacked over the site root by the node shell (AaPanelTools)
        return $this->restoreFromArchive($ref, $backupRemoteId, $options);
    }

    // ── transport ────────────────────────────────────────────────────────────

    /** @return array<string,mixed>|string|null */
    /** @param array<string, array{contents:string|resource, filename:string}> $files multipart parts (file manager uploads) */
    private function post(string $pathWithAction, array $params, string $action, bool $critical = false, array $files = []): mixed
    {
        $apiKey = (string) ($this->credentials['api_key'] ?? '');
        if ($apiKey === '') {
            throw new ProviderException('aapanel', ProviderErrorCode::AUTH, 'aaPanel API key is not configured');
        }
        $time = time();
        $body = array_merge($params, ['request_time' => $time, 'request_token' => md5($time.md5($apiKey))]);
        $options = TlsOptions::verify($this->instance, 'aapanel');
        $response = $this->http->send(new ProviderRequest(
            provider: 'aapanel', instanceKey: $this->instance->key, method: 'POST', url: rtrim((string) $this->instance->base_url, '/').$pathWithAction, action: $action,
            headers: ['Accept' => 'application/json'], body: $body, bodyType: $files === [] ? 'form' : 'multipart', timeoutSeconds: $files !== [] ? 300 : ($critical ? 120 : 30), critical: $critical, options: $options, files: $files, // site creation and other writes take the panel a minute on a busy node
        ));

        return $this->normalize($response, $action);
    }

    private function normalize(ProviderResponse $response, string $action): mixed
    {
        if ($response->status >= 500) {
            throw new ProviderException('aapanel', ProviderErrorCode::TRANSIENT, "aaPanel {$action} HTTP {$response->status}", (string) $response->status);
        }
        if (in_array($response->status, [401, 403], true)) {
            throw new ProviderException('aapanel', ProviderErrorCode::AUTH, "aaPanel {$action} rejected (HTTP {$response->status}) — IP not allow-listed or token invalid", (string) $response->status);
        }
        $json = $response->json();
        if (! is_array($json)) {
            $text = trim($response->rawBody);
            if ($text === '' || str_contains(strtolower($text), 'error')) {
                throw new ProviderException('aapanel', ProviderErrorCode::PROVIDER_BUG, "aaPanel {$action} returned an unparseable body");
            }

            return $text; // bare string responses (e.g. GetFileBody variants)
        }
        if (array_key_exists('status', $json) && $json['status'] === false) {
            $raw = $json['msg'] ?? 'unknown error';
            $msg = is_array($raw) ? (string) json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $raw; // the ACME endpoints answer with a dict
            $lower = strtolower($msg);
            $code = match (true) {
                str_contains($lower, 'token') || str_contains($lower, 'ip') && str_contains($lower, 'whitelist') => ProviderErrorCode::AUTH,
                str_contains($lower, 'not exist') || str_contains($lower, 'not found') || str_contains($lower, 'does not exist') => ProviderErrorCode::NOT_FOUND,
                str_contains($lower, 'already') || str_contains($lower, 'exist') => ProviderErrorCode::CONFLICT,
                default => ProviderErrorCode::VALIDATION,
            };
            throw new ProviderException('aapanel', $code, "aaPanel {$action}: {$msg}", 'status:false');
        }
        $this->http->recordSuccess($this->instance->key);

        return $json;
    }

    private function findSite(string $domain): ?array
    {
        $list = $this->post('/data?action=getData&table=sites', ['limit' => 20, 'p' => 1, 'search' => $domain], 'sites.search');
        $row = collect((array) ($list['data'] ?? []))->firstWhere('name', $domain);

        return is_array($row) ? $row : null;
    }

    /** aaPanel's table search matches the site name, never the id: search by the remembered name, otherwise page through the list. */
    private function getSite(int $id, ?string $name = null): ?array
    {
        $queries = $name !== null && $name !== '' ? [['limit' => 20, 'p' => 1, 'search' => $name]] : [['limit' => 100, 'p' => 1], ['limit' => 100, 'p' => 2], ['limit' => 100, 'p' => 3]];
        foreach ($queries as $query) {
            $list = $this->post('/data?action=getData&table=sites', $query, 'sites.get');
            $rows = (array) ($list['data'] ?? []);
            $row = collect($rows)->first(fn ($r) => (int) ($r['id'] ?? 0) === $id);
            if (is_array($row)) {
                return $row;
            }
            if ($name !== null || count($rows) < 100) {
                break;
            }
        }

        return null;
    }

    // ── extended site management (file manager, rewrite rules, site password, custom certificate, one-click apps) ──

    public function siteSettings(ResourceRef $site): array
    {
        $rewrite = '';
        try {
            $body = $this->post('/files?action=GetFileBody', ['path' => $this->rewritePath($site)], 'files.body');
            $rewrite = is_array($body) ? (string) ($body['data'] ?? '') : (string) $body;
        } catch (ProviderException) {
            // no rewrite file yet
        }

        return ['errordocs' => null, 'directives' => ['rewrite' => $rewrite], 'stats' => null, 'db_admin_url' => $this->instance->option('phpmyadmin_url') ?: null, 'document_root' => $this->sitePath($site), 'site_password' => null];
    }

    public function setErrorDocs(ResourceRef $site, bool $enabled): ProviderResult
    {
        throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'Custom error pages are served from the site root on this panel (upload 404.html / 50x.html)');
    }

    public function setDirectives(ResourceRef $site, string $kind, string $content): ProviderResult
    {
        if ($kind !== 'rewrite') {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, "Directive kind {$kind} is not offered by this panel (rewrite rules only)");
        }
        $this->post('/files?action=SaveFileBody', ['path' => $this->rewritePath($site), 'data' => $content, 'encoding' => 'utf-8'], 'files.save', true);
        try {
            $this->post('/system?action=ServiceAdmin', ['name' => 'nginx', 'type' => 'reload'], 'system.reload', true);
        } catch (ProviderException) {
            // the panel reloads the web server itself on the next site change; the rule file is saved
        }

        return ProviderResult::completed($site, ['kind' => 'rewrite', 'bytes' => strlen($content)]);
    }

    public function listProtectedFolders(ResourceRef $site): array
    {
        return []; // site-wide password only; the platform remembers the user it set (desired_spec)
    }

    public function protectFolder(ResourceRef $site, array $spec): ProviderResult
    {
        $this->post('/site?action=SetHasPwd', ['id' => (int) $site->remoteId, 'username' => (string) $spec['user'], 'password' => (string) $spec['password']], 'site.password', true);

        return ProviderResult::completed(new ResourceRef('site_password', 'site', $this->instance->key, ['path' => '/'], $site->serviceId), ['path' => '/', 'user' => $spec['user']]);
    }

    public function unprotectFolder(ResourceRef $site, string $remoteId): ProviderResult
    {
        $this->post('/site?action=CloseHasPwd', ['id' => (int) $site->remoteId], 'site.password.close', true);

        return ProviderResult::completed(null, ['deleted' => true]);
    }

    public function listDbUsers(ResourceRef $site): array
    {
        return [];
    }

    public function createDbUser(ResourceRef $site, array $spec): ProviderResult
    {
        throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'Database users are managed together with their database on this panel');
    }

    public function setDbUserPassword(ResourceRef $site, string $remoteId, string $password): ProviderResult
    {
        $row = collect($this->listDatabases($site))->firstWhere('remote_id', $remoteId);
        if ($row === null) {
            throw new ProviderException('aapanel', ProviderErrorCode::NOT_FOUND, 'Database not found');
        }
        $this->post('/database?action=ResDatabasePassword', ['id' => (int) $remoteId, 'name' => $row['user'] ?? $row['name'], 'password' => $password], 'db.password', true);

        return ProviderResult::completed(new ResourceRef('database', $remoteId, $this->instance->key, [], $site->serviceId), ['password_changed' => true]);
    }

    public function deleteDbUser(ResourceRef $site, string $remoteId): ProviderResult
    {
        throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'Database users are removed with their database on this panel');
    }

    public function listShellUsers(ResourceRef $site): array
    {
        return [];
    }

    public function createShellUser(ResourceRef $site, array $spec): ProviderResult
    {
        throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'This panel offers no per-site shell users; SFTP works with the FTP accounts');
    }

    public function setShellKey(ResourceRef $site, string $remoteId, string $sshKey): ProviderResult
    {
        throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'This panel offers no per-site shell users');
    }

    public function deleteShellUser(ResourceRef $site, string $remoteId): ProviderResult
    {
        throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'This panel offers no per-site shell users');
    }

    public function setStats(ResourceRef $site, array $spec): ProviderResult
    {
        throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'Traffic statistics are not offered by this panel; use the access logs');
    }

    public function uploadCertificate(ResourceRef $site, array $cert): ProviderResult
    {
        $pem = trim((string) $cert['cert']).(! empty($cert['chain']) ? "\n".trim((string) $cert['chain']) : '');
        $this->post('/site?action=SetSSL', ['type' => 0, 'siteName' => $site->meta['name'] ?? '', 'key' => (string) $cert['key'], 'csr' => $pem], 'site.ssl.upload', true);

        return ProviderResult::completed($site, ['uploaded' => true]);
    }

    public function listFiles(ResourceRef $site, string $path): array
    {
        $absolute = $this->jail($site, $path);
        $result = $this->post('/files?action=GetDir', ['path' => $absolute, 'p' => 1, 'showRow' => 500], 'files.list');
        if (isset($result['PATH']) && rtrim((string) $result['PATH'], '/') !== rtrim($absolute, '/')) {
            throw new ProviderException('aapanel', ProviderErrorCode::NOT_FOUND, 'The folder does not exist'); // a missing folder must never fall back to the node's default listing
        }
        $entries = [];
        foreach ([['DIR', 'dir'], ['FILES', 'file']] as [$key, $type]) {
            foreach ((array) ($result[$key] ?? []) as $line) {
                $parts = explode(';', (string) $line);
                if (($parts[0] ?? '') === '') {
                    continue;
                }
                $entries[] = ['name' => $parts[0], 'type' => $type, 'size' => $type === 'file' && isset($parts[1]) && is_numeric($parts[1]) ? (int) $parts[1] : null, 'modified' => isset($parts[2]) && is_numeric($parts[2]) ? date(DATE_ATOM, (int) $parts[2]) : null, 'perm' => $parts[3] ?? null];
            }
        }
        usort($entries, fn ($a, $b) => [$a['type'] !== 'dir', $a['name']] <=> [$b['type'] !== 'dir', $b['name']]);

        return ['path' => '/'.trim(str_replace('\\', '/', $path), '/'), 'entries' => $entries];
    }

    public function readFile(ResourceRef $site, string $path): string
    {
        $result = $this->post('/files?action=GetFileBody', ['path' => $this->jail($site, $path)], 'files.body');

        return is_array($result) ? (string) ($result['data'] ?? '') : (string) $result;
    }

    public function writeFile(ResourceRef $site, string $path, string $content): ProviderResult
    {
        $absolute = $this->jail($site, $path);
        try {
            $this->post('/files?action=SaveFileBody', ['path' => $absolute, 'data' => $content, 'encoding' => 'utf-8'], 'files.save', true);
        } catch (ProviderException $e) {
            if (! str_contains(mb_strtolower($e->getMessage()), 'not exist')) {
                throw $e;
            }
            // aaPanel only writes into existing files ("Configuration file not exist"): create it, then save the body
            $this->post('/files?action=CreateFile', ['path' => $absolute], 'files.create', true);
            $this->post('/files?action=SaveFileBody', ['path' => $absolute, 'data' => $content, 'encoding' => 'utf-8'], 'files.save', true);
        }

        return ProviderResult::completed(new ResourceRef('file', $path, $this->instance->key, [], $site->serviceId), ['bytes' => strlen($content)]);
    }

    public function deleteFile(ResourceRef $site, string $path, bool $directory = false): ProviderResult
    {
        $absolute = $this->jail($site, $path);
        if ($absolute === $this->sitePath($site)) {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'The site root cannot be deleted');
        }
        $this->post($directory ? '/files?action=DeleteDir' : '/files?action=DeleteFile', ['path' => $absolute], 'files.delete', true);

        return ProviderResult::completed(null, ['deleted' => true]);
    }

    public function createDirectory(ResourceRef $site, string $path): ProviderResult
    {
        $this->post('/files?action=CreateDir', ['path' => $this->jail($site, $path)], 'files.mkdir', true);

        return ProviderResult::completed(new ResourceRef('directory', $path, $this->instance->key, [], $site->serviceId), ['created' => true]);
    }

    public function listApps(ResourceRef $site): array
    {
        try {
            $result = $this->post('/deployment?action=GetList', [], 'apps.list');
        } catch (ProviderException $e) {
            if ($e->vendorCode === '403' || $e->errorCode === ProviderErrorCode::NOT_FOUND) {
                return []; // the one-click deployment plugin is not installed on this node: nothing to offer
            }
            throw $e;
        }
        $rows = is_array($result) ? ($result['list'] ?? $result['data'] ?? (array_is_list($result) ? $result : [])) : [];
        $out = [];
        foreach ((array) $rows as $row) {
            if (! is_array($row) || empty($row['name'])) {
                continue;
            }
            $out[] = ['name' => (string) $row['name'], 'version' => isset($row['version']) ? (string) $row['version'] : null, 'title' => isset($row['title']) ? (string) $row['title'] : null];
        }

        return $out;
    }

    public function installApp(ResourceRef $site, array $spec): ProviderResult
    {
        $php = str_replace('.', '', (string) ($spec['php_version'] ?? ''));
        $this->post('/deployment?action=SetupPackage', array_filter(['dname' => (string) $spec['name'], 'site_name' => $site->meta['name'] ?? '', 'php_version' => $php !== '' ? $php : null], fn ($v) => $v !== null), 'apps.install', true);

        return ProviderResult::completed($site, ['installed' => $spec['name']]);
    }

    private function sitePath(ResourceRef $site): string
    {
        $path = rtrim((string) ($site->meta['path'] ?? ''), '/');

        return $path !== '' ? $path : '/www/wwwroot/'.($site->meta['name'] ?? '');
    }

    private function rewritePath(ResourceRef $site): string
    {
        return '/www/server/panel/vhost/rewrite/'.($site->meta['name'] ?? '').'.conf';
    }

    /** Absolute path inside the site root; anything escaping it is refused before it reaches the panel. */
    private function jail(ResourceRef $site, string $path): string
    {
        $root = $this->sitePath($site);
        $relative = trim(str_replace('\\', '/', $path), '/');
        if ($relative !== '' && (str_contains($relative, "\0") || preg_match('~(^|/)\.\.?(/|$)~', $relative))) {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'Path must stay inside the site root');
        }

        return $relative === '' ? $root : $root.'/'.$relative;
    }
}
