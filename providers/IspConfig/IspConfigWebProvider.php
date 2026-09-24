<?php

declare(strict_types=1);

namespace Onhost\Providers\IspConfig;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Providers\Contracts\ActionPlan;
use Onhost\Providers\Contracts\ActualState;
use Onhost\Providers\Contracts\AsyncHandle;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\FileTransport;
use Onhost\Providers\Contracts\MailProvider;
use Onhost\Providers\Contracts\MailToolsProvider;
use Onhost\Providers\Contracts\ProviderHealth;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\ResourceSpec;
use Onhost\Providers\Contracts\SelfProbing;
use Onhost\Providers\Contracts\Usage;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;
use Onhost\Providers\Shell\ManagedDirectives;
use Onhost\Providers\Shell\Q;
use Onhost\Providers\Shell\SecurityRules;

/**
 * ISPConfig as the Shared Web Executor (blueprint §7) and legacy mail executor
 * (§15). One ISPConfig client per ONhost organization, one Unix user + PHP-FPM
 * pool per site, real per-site limits from the plan entitlements.
 */
final class IspConfigWebProvider implements MailProvider, MailToolsProvider, SelfProbing, WebHostingProvider, WebToolsProvider
{
    use IspConfigMailTools;
    use IspConfigTools;

    private readonly IspConfigConnector $api;

    public function __construct(
        private readonly ProviderInstance $instance,
        array $credentials,
        ProviderHttpClient $http,
        private readonly CacheRepository $cache,
    ) {
        $this->api = new IspConfigConnector($instance, $credentials, $http, $cache);
    }

    public static function providerKey(): string
    {
        return 'ispconfig';
    }

    public static function adapterVersion(): string
    {
        return '1.0.0';
    }

    public static function supportedVendorVersions(): array
    {
        return ['3.2.11', '3.2.12', '3.3.0', '3.3.1'];
    }

    public function capabilities(): array
    {
        return ['web.php' => true, 'web.database' => true, 'web.ssl_letsencrypt' => true, 'web.cron' => true, 'web.shell' => 'per_plan', 'node.shared' => false, 'mail.create' => 'legacy', 'dns.zone' => 'secondary_only', 'backup.restore' => 'panel_backup'];
    }

    /**
     * Remote login plus a permission self-test. The "Sites" function group is what web provisioning needs, so a
     * remote user without it is down; the "Server"/"Monitor" groups only add queue depth and hostname, so a remote
     * user without them is up with a warning (the operator sees exactly which groups to enable in ISPConfig →
     * System → Remote Users).
     */
    /**
     * The ISPConfig release the panel runs (`3.2.11p2`). Without it an upgrade of the panel could not even be noticed
     * (PanelVersionGate); null where the remote user may not ask or the release is older than the call.
     */
    private function appVersion(): ?string
    {
        try {
            $answer = $this->api->call('server_get_app_version', ['server_id' => 0]);
        } catch (ProviderException) {
            return null;
        }
        $version = is_array($answer) ? trim((string) ($answer['ispc_app_version'] ?? '')) : '';

        return $version !== '' ? mb_substr($version, 0, 40) : null;
    }

    public function health(): ProviderHealth
    {
        $started = hrtime(true);
        $ms = fn (): int => (int) ((hrtime(true) - $started) / 1_000_000);
        try {
            $this->api->call('sites_web_domain_get', ['primary_id' => ['domain_id' => -1]]);
        } catch (ProviderException $e) {
            $hint = $e->errorCode === ProviderErrorCode::AUTH ? ' — the remote user needs the "Sites functions" group (ISPConfig → System → Remote Users).' : '';

            return ProviderHealth::down($e->getMessage().$hint, $ms());
        }
        $detail = ['permissions' => ['sites' => true]];
        try {
            $serverId = $this->serverId();
            $queue = (int) $this->api->call('monitor_jobqueue_count', ['server_id' => $serverId]);
            $server = $this->api->call('server_get', ['server_id' => $serverId, 'section' => 'server']);
            $detail += ['server_id' => $serverId, 'jobqueue' => $queue, 'hostname' => is_array($server) ? ($server['hostname'] ?? null) : null];
            $detail['permissions'] += ['server' => true, 'monitor' => true];

            return new ProviderHealth($queue < 200, $this->appVersion(), $ms(), $detail, $queue >= 200 ? "ISPConfig job queue has {$queue} pending jobs" : null);
        } catch (ProviderException $e) {
            if ($e->errorCode !== ProviderErrorCode::AUTH) {
                return ProviderHealth::down($e->getMessage(), $ms());
            }
            $detail['permissions'] += ['server' => false, 'monitor' => false];
            $detail['server_id'] = $this->serverIdOrNull();
            $detail['warning'] = 'Remote user lacks the "Server functions" / "Monitor functions" groups: provisioning works, but queue depth and capacity are unknown. Enable them in ISPConfig → System → Remote Users.';

            return new ProviderHealth(true, null, $ms(), $detail);
        }
    }

    /**
     * Servers of the installation for node discovery: every ISPConfig server with the services it runs. Without the
     * "Server functions" group the remote user cannot list servers, so the panel host itself is reported as the one
     * web (+ mail) server — the usual single-server layout.
     *
     * @return list<array{name:string, remote_id:int, roles:list<string>, capacity:array<string,mixed>}>
     */
    public function discoverServers(): array
    {
        $host = (string) parse_url((string) $this->instance->base_url, PHP_URL_HOST);
        try {
            $servers = $this->api->call('server_get_all');
        } catch (ProviderException $e) {
            if ($e->errorCode !== ProviderErrorCode::AUTH) {
                throw $e;
            }
            $roles = ['web'];
            if ((bool) ($this->instance->capabilities['mail.create'] ?? false)) {
                $roles[] = 'mail';
            }

            return [['name' => $host, 'remote_id' => $this->serverIdOrNull() ?? 1, 'roles' => $roles, 'capacity' => []]];
        }
        $out = [];
        foreach (is_array($servers) ? $servers : [] as $server) {
            $id = (int) ($server['server_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $roles = [];
            foreach (['web_server' => 'web', 'mail_server' => 'mail', 'dns_server' => 'dns', 'db_server' => 'managed'] as $flag => $role) {
                if ((int) ($server[$flag] ?? 0) === 1) {
                    $roles[] = $role;
                }
            }
            $out[] = ['name' => (string) ($server['server_name'] ?? "ispconfig-{$id}"), 'remote_id' => $id, 'roles' => $roles === [] ? ['web'] : $roles, 'capacity' => []];
        }

        return $out;
    }

    private function serverIdOrNull(): ?int
    {
        try {
            return $this->serverId();
        } catch (ProviderException) {
            return null;
        }
    }

    public function vendorVersion(): ?string
    {
        return $this->instance->vendor_version;
    }

    // ── Web ──────────────────────────────────────────────────────────────────

    public function provision(ResourceSpec $spec): ProviderResult
    {
        $domain = (string) $spec->get('domain');
        $serverId = $this->serverId();
        $existing = $this->findSite($domain);
        if ($existing !== null) {
            // A site of that name already on the node is ours only when the client the platform made for this
            // organization owns it — found again on a retry. Anything else was made by hand before ONhost: a
            // historical site, which the platform must never take over (the owner's rule, brain H304). Refused
            // before a single write, with the client looked up read-only: `ensureClient` would create one and
            // "bring its limits up to date" on the way.
            $ours = $this->findClientId($spec);
            if ($ours === null || ! self::siteOwnedBy($existing, $ours)) {
                throw new ProviderException('ispconfig', ProviderErrorCode::CONFLICT, "The site {$domain} already exists on this node and was not created by ONhost. It is a historical site and is not taken over; adopting it is an explicit operator decision.");
            }

            return ProviderResult::completed(new ResourceRef('web_domain', (string) $existing['domain_id'], (string) $serverId, ['client_id' => $ours, 'system_user' => $existing['system_user'] ?? null], $spec->serviceId), $existing, alreadyExisted: true);
        }
        $clientId = $this->ensureClient($spec);
        $ent = (array) $spec->get('entitlements', []);
        $php = (string) $spec->get('php_version', '8.3');
        $params = [
            'server_id' => $serverId, 'ip_address' => '*', 'ipv6_address' => '*', 'domain' => $domain, 'type' => 'vhost', 'parent_domain_id' => 0, 'vhost_type' => 'name',
            'http_port' => 80, 'https_port' => 443, // mandatory since ISPConfig 3.2 (validated by regex, an absent value fails)
            'hd_quota' => (int) (($ent['nvme_gb'] ?? 10) * 1024), 'traffic_quota' => -1, 'cgi' => 'n', 'ssi' => 'n', 'suexec' => 'y', 'errordocs' => 1, 'subdomain' => 'www',
            'ssl' => 'y', 'ssl_letsencrypt' => 'y', 'rewrite_to_https' => 'y', 'php' => 'php-fpm', 'php_fpm_use_socket' => 'y', 'pm' => 'ondemand',
            'pm_max_children' => (int) ($ent['php_workers'] ?? 2), 'pm_max_requests' => 500, 'pm_process_idle_timeout' => 10,
            'fastcgi_php_version' => $this->phpVersionString($php), 'allow_override' => 'All', 'active' => 'y', 'backup_interval' => 'daily', 'backup_copies' => (int) ($ent['backup_generations'] ?? 7), 'backup_excludes' => '',
            'php_open_basedir' => "/var/www/clients/client{$clientId}/web[website_id]/web:/var/www/clients/client{$clientId}/web[website_id]/private:/var/www/clients/client{$clientId}/web[website_id]/tmp:/var/www/{$domain}/web:/srv/www/{$domain}/web:/usr/share/php5:/usr/share/php:/tmp:/usr/share/phpmyadmin:/etc/phpmyadmin:/var/lib/phpmyadmin:/dev/random:/dev/urandom",
            'custom_php_ini' => 'memory_limit = '.(int) ($ent['php_memory_mb'] ?? 512)."M\nmax_execution_time = 120\nupload_max_filesize = 128M\npost_max_size = 128M",
        ];
        $domainId = (int) $this->api->call('sites_web_domain_add', ['client_id' => $clientId, 'params' => $params], true);
        $site = $this->api->call('sites_web_domain_get', ['primary_id' => $domainId]);

        return ProviderResult::accepted(
            $this->jobqueueHandle($serverId, ['domain_id' => $domainId]),
            new ResourceRef('web_domain', (string) $domainId, (string) $serverId, ['domain' => $domain, 'client_id' => $clientId, 'system_user' => $site['system_user'] ?? null, 'document_root' => $site['document_root'] ?? null], $spec->serviceId),
            ['client_id' => $clientId, 'domain_id' => $domainId, 'system_user' => $site['system_user'] ?? null],
        );
    }

    public function getActualState(ResourceRef $ref): ActualState
    {
        if ($ref->remoteType === 'mail_domain') { // a mail service: its mail domain, never a web site with the same number
            $domain = $this->getMailDomain((int) $ref->remoteId);

            return $domain === null ? ActualState::missing() : new ActualState(true, ['domain' => $domain['domain'] ?? null, 'active' => ($domain['active'] ?? 'n') === 'y', 'dkim' => ($domain['dkim'] ?? 'n') === 'y'], ($domain['active'] ?? 'n') === 'y' ? 'active' : 'suspended', now()->toISOString());
        }
        $this->assertWebDomain($ref);
        $site = $this->getSite((int) $ref->remoteId);
        if ($site === null) {
            return ActualState::missing();
        }
        preg_match('/PHP\s*([\d.]+)/', (string) ($site['fastcgi_php_version'] ?? ''), $m);

        return new ActualState(true, [
            'domain' => $site['domain'] ?? null, 'php_version' => $m[1] ?? null, 'active' => ($site['active'] ?? 'n') === 'y', 'hd_quota_mb' => (int) ($site['hd_quota'] ?? 0),
            'php_workers' => (int) ($site['pm_max_children'] ?? 0), 'ssl' => ($site['ssl'] ?? 'n') === 'y', 'letsencrypt' => ($site['ssl_letsencrypt'] ?? 'n') === 'y', 'rewrite_to_https' => ($site['rewrite_to_https'] ?? 'n') === 'y',
            'system_user' => $site['system_user'] ?? null, 'document_root' => $site['document_root'] ?? null,
        ], ($site['active'] ?? 'n') === 'y' ? 'active' : 'suspended', now()->toISOString());
    }

    public function reconcile(ResourceSpec $spec, ActualState $actual): ActionPlan
    {
        if (! $actual->exists) {
            return new ActionPlan([ActionPlan::drift('existence', 'present', 'missing', 'ONHOST_MANAGED', 'SECURITY_SUSPICIOUS')]);
        }
        $ent = (array) $spec->get('entitlements', []);
        $drifts = [];
        if (isset($ent['php_workers']) && (int) $ent['php_workers'] !== (int) $actual->get('php_workers')) {
            $drifts[] = ActionPlan::drift('php_workers', (int) $ent['php_workers'], $actual->get('php_workers'), 'ONHOST_MANAGED', 'AUTO_REPAIRABLE');
        }
        if (isset($ent['nvme_gb']) && (int) $ent['nvme_gb'] * 1024 !== (int) $actual->get('hd_quota_mb')) {
            $drifts[] = ActionPlan::drift('hd_quota_mb', (int) $ent['nvme_gb'] * 1024, $actual->get('hd_quota_mb'), 'ONHOST_MANAGED', 'REQUIRES_APPROVAL');
        }
        $php = $spec->get('php_version');
        if ($php !== null && (string) $actual->get('php_version') !== (string) $php) {
            $drifts[] = ActionPlan::drift('php_version', $php, $actual->get('php_version'), 'CUSTOMER_MUTABLE', 'EXPECTED');
        }

        return new ActionPlan($drifts);
    }

    public function resize(ResourceRef $ref, ResourceSpec $spec): ProviderResult
    {
        if ($ref->remoteType === 'mail_domain') {
            return ProviderResult::completed($ref, ['updated' => []]); // mailbox quotas live on the mailboxes
        }
        $this->assertWebDomain($ref);
        $ent = (array) $spec->get('entitlements', []);
        // the plan's space is the ACCOUNT's; this site holds what the customer's other sites leave of it (`site_nvme_gb`)
        $siteGb = $spec->get('site_nvme_gb') !== null ? (int) $spec->get('site_nvme_gb') : (isset($ent['nvme_gb']) ? (int) $ent['nvme_gb'] : null);
        $params = array_filter(['hd_quota' => $siteGb !== null ? $siteGb * 1024 : null, 'pm_max_children' => $ent['php_workers'] ?? null, 'backup_copies' => $ent['backup_generations'] ?? null], fn ($v) => $v !== null);
        // the client's limits first: ISPConfig checks a site against them, so a bigger site under the old limits is refused
        $client = $this->updateClientLimits((int) ($ref->meta['client_id'] ?? 0), $ent);
        $this->updateSite($ref, $params);

        return ProviderResult::accepted($this->jobqueueHandle((int) $ref->node), $ref, ['updated' => array_keys($params), 'client_limits' => $client]);
    }

    public function suspend(ResourceRef $ref): ProviderResult
    {
        if ($ref->remoteType === 'mail_domain') {
            $this->setMailDomainActive($ref, false);

            return ProviderResult::accepted($this->jobqueueHandle((int) $ref->node), $ref);
        }
        $this->assertWebDomain($ref);
        $this->updateSite($ref, ['active' => 'n']);

        return ProviderResult::accepted($this->jobqueueHandle((int) $ref->node), $ref);
    }

    public function resume(ResourceRef $ref): ProviderResult
    {
        if ($ref->remoteType === 'mail_domain') {
            $this->setMailDomainActive($ref, true);

            return ProviderResult::accepted($this->jobqueueHandle((int) $ref->node), $ref);
        }
        $this->assertWebDomain($ref);
        $this->updateSite($ref, ['active' => 'y']);

        return ProviderResult::accepted($this->jobqueueHandle((int) $ref->node), $ref);
    }

    public function terminate(ResourceRef $ref): ProviderResult
    {
        if ($ref->remoteType === 'mail_domain') { // deleting a mail service removes its mail domain — the web site with the same number is not touched
            if ($this->getMailDomain((int) $ref->remoteId) === null) {
                return ProviderResult::completed(null, ['already_deleted' => true], alreadyExisted: true);
            }

            return $this->deleteMailDomain($ref);
        }
        $this->assertWebDomain($ref);
        $site = $this->getSite((int) $ref->remoteId);
        $expected = (string) ($ref->meta['domain'] ?? '');
        if ($site !== null && $expected !== '' && strtolower((string) ($site['domain'] ?? '')) !== strtolower($expected)) {
            throw new ProviderException('ispconfig', ProviderErrorCode::CONFLICT, "ISPConfig web domain {$ref->remoteId} is {$site['domain']}, not {$expected}; refusing to delete it.");
        }
        $user = (string) ($ref->meta['system_user'] ?? ''); // and never a site of another customer on a shared panel
        if ($site !== null && $user !== '' && (string) ($site['system_user'] ?? '') !== $user) {
            throw new ProviderException('ispconfig', ProviderErrorCode::CONFLICT, "ISPConfig web domain {$ref->remoteId} ({$site['domain']}) belongs to another site user; refusing to delete it.");
        }
        // everything of the site that is a row of its own goes first — and it goes whether the vhost is still there or
        // not, so a site somebody deleted in the panel by hand does not leave the customer's data and logins behind
        $left = $this->dropSiteChildren($ref);
        if ($site === null) {
            return ProviderResult::completed(null, ['already_deleted' => true] + ($left === [] ? [] : ['leftover' => $left]), alreadyExisted: true);
        }
        $this->api->call('sites_web_domain_delete', ['primary_id' => (int) $ref->remoteId], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $ref->node), null, $left === [] ? [] : ['leftover' => $left]);
    }

    /**
     * Everything of a site that ISPConfig keeps as a row of its own, removed before the site itself.
     *
     * `sites_web_domain_delete` deletes one row: the vhost. The databases live in `web_database`, their logins in
     * `web_database_user`, the FTP accounts in `ftp_user`, the SSH accounts in `shell_user`, the jobs in `cron`, and
     * every further host name in a `web_domain` row of its own — each pointing at the site by `parent_domain_id`,
     * none of them named in that one call. Whether the panel tidies up behind the remote API is not something the
     * platform may assume: what it created, it removes itself. What was left behind is a terminated customer's data
     * on a live node long past every retention promise, their FTP, SSH and database passwords still working on a
     * shared machine, the disk never freed, and an alias vhost still answering for a name the platform believes
     * nobody holds. The aaPanel adapter has always done this (`terminate()` drops the cron jobs and the Node apps
     * first, and `DeleteSite` takes the files, databases and FTP accounts with it).
     *
     * Every listing is scoped by `parent_domain_id` to this very site, so nothing of a neighbour is ever in reach,
     * and the final archive has already been taken by the time the workflow calls this. A refusal does not stop the
     * termination — the service must end — but it is named and given back, so nothing is left silently.
     *
     * @return array<string, list<string>> what is still on the node, by kind
     */
    private function dropSiteChildren(ResourceRef $ref): array
    {
        $left = [];
        // the database logins are collected BEFORE their databases: `listDbUsers` finds them through the databases,
        // so once those are gone the login rows cannot be found at all — and a login without its database is still a
        // login into the database server. For the same reason they are deleted last: ISPConfig (and this adapter)
        // refuse a login that still owns a database.
        $logins = array_column($this->listing('db_user', fn () => $this->listDbUsers($ref), $left), 'remote_id');
        $kinds = [
            'domain' => [fn () => $this->listSubdomains($ref), fn (string $id) => $this->removeSubdomain($ref, $id)],
            'database' => [fn () => $this->listDatabases($ref), fn (string $id) => $this->deleteDatabase($ref, $id)],
            'ftp' => [fn () => $this->listFtpAccounts($ref), fn (string $id) => $this->deleteFtpAccount($ref, $id)],
            'shell' => [fn () => $this->listShellUsers($ref), fn (string $id) => $this->deleteShellUser($ref, $id)],
            'cron' => [fn () => $this->listCron($ref), fn (string $id) => $this->deleteCron($ref, $id)],
        ];
        foreach ($kinds as $kind => [$list, $delete]) {
            foreach ($this->listing($kind, $list, $left) as $row) {
                $id = (string) ($row['remote_id'] ?? '');
                try {
                    $delete($id);
                } catch (ProviderException $e) {
                    $left[$kind][] = $id;
                }
            }
        }
        foreach ($logins as $id) {
            // a login whose database is still there stays with it, and one shared with another site's database is not
            // ours to take away. `deleteDbUser()` itself cannot be used here: it looks the login up through the
            // databases, which are gone by now. The lookup is inside the try with the delete — a panel that will not
            // answer which databases a login still has is one more thing left behind, not a reason to stop.
            try {
                if ($this->databasesOfLogin((int) $id) !== []) {
                    $left['db_user'][] = (string) $id;

                    continue;
                }
                $this->api->call('sites_database_user_delete', ['primary_id' => (int) $id], true);
            } catch (ProviderException $e) {
                $left['db_user'][] = (string) $id;
            }
        }

        return $left;
    }

    /** @return list<array<string,mixed>> every database that still belongs to this login, wherever that database belongs */
    private function databasesOfLogin(int $loginId): array
    {
        return array_values(array_filter((array) $this->api->call('sites_database_get', ['primary_id' => ['database_user_id' => $loginId]]), 'is_array'));
    }

    /**
     * A listing that cannot be read is not a reason to stop a termination — but the kind is named in what is left,
     * with the panel's own word for the refusal, because nothing of it was removed.
     *
     * @param  callable():array<int,array<string,mixed>>  $list
     * @param  array<string, list<string>>  $left
     * @return array<int,array<string,mixed>>
     */
    private function listing(string $kind, callable $list, array &$left): array
    {
        try {
            return $list();
        } catch (ProviderException $e) {
            $left[$kind][] = 'listing:'.$e->errorCode->value;

            return [];
        }
    }

    public function usage(ResourceRef $ref, ?string $periodStart = null, ?string $periodEnd = null): Usage
    {
        if ($ref->remoteType !== 'web_domain' && $ref->remoteType !== 'site') {
            return new Usage([], now()->toISOString());
        }
        $site = $this->getSite((int) $ref->remoteId) ?? [];
        $quota = (array) $this->api->call('quota_get_by_user', ['client_id' => (int) ($ref->meta['client_id'] ?? 0)]);
        $row = collect($quota)->firstWhere('domain', $site['domain'] ?? '') ?? [];

        return new Usage([
            'disk_used_bytes' => (int) ($row['used'] ?? 0) * 1024, 'disk_quota_bytes' => (int) ($site['hd_quota'] ?? 0) * 1024 * 1024,
            'traffic_bytes' => null, 'php_workers' => (int) ($site['pm_max_children'] ?? 0),
        ], now()->toISOString());
    }

    public function createDatabase(ResourceRef $site, array $spec): ProviderResult
    {
        $clientId = (int) ($site->meta['client_id'] ?? 0);
        $name = (string) $spec['name'];
        $existing = collect((array) $this->api->call('sites_database_get', ['primary_id' => ['database_name' => $name]]))->first();
        if (is_array($existing) && ! empty($existing['database_id'])) {
            // the name is unique on the database server, not on the site: a database of that name that hangs off
            // another site is somebody else's, however familiar the name looks
            if ((int) ($existing['parent_domain_id'] ?? 0) !== (int) $site->remoteId) {
                throw new ProviderException('ispconfig', ProviderErrorCode::CONFLICT, "A database named {$name} already exists on this server and belongs to another site; it is not taken over.");
            }

            return ProviderResult::completed(new ResourceRef('database', (string) $existing['database_id'], $site->node, ['name' => $name], $site->serviceId), alreadyExisted: true);
        }
        $userId = (int) $this->api->call('sites_database_user_add', ['client_id' => $clientId, 'params' => ['server_id' => (int) $site->node, 'database_user' => (string) $spec['user'], 'database_password' => (string) $spec['password']]], true);
        $dbId = (int) $this->api->call('sites_database_add', ['client_id' => $clientId, 'params' => [
            'server_id' => (int) $site->node, 'type' => 'mysql', 'parent_domain_id' => (int) $site->remoteId, 'database_name' => $name, 'database_user_id' => $userId, 'database_ro_user_id' => 0,
            'database_charset' => $spec['charset'] ?? 'utf8mb4', 'remote_access' => 'n', 'remote_ips' => '', 'backup_interval' => 'daily', 'backup_copies' => 7, 'active' => 'y',
        ]], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), new ResourceRef('database', (string) $dbId, $site->node, ['name' => $name, 'user_id' => $userId], $site->serviceId), ['database_id' => $dbId, 'user_id' => $userId]);
    }

    public function setPhpVersion(ResourceRef $site, string $version): ProviderResult
    {
        $this->updateSite($site, ['fastcgi_php_version' => $this->phpVersionString($version)]);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), $site, ['php_version' => $version]);
    }

    public function issueCertificate(ResourceRef $site, array $domains): ProviderResult
    {
        $this->updateSite($site, ['ssl' => 'y', 'ssl_letsencrypt' => 'y']);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node, [], 20, 1800), $site, ['domains' => $domains]);
    }

    public function forceHttps(ResourceRef $site, bool $enabled): ProviderResult
    {
        $this->updateSite($site, ['rewrite_to_https' => $enabled ? 'y' : 'n']);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), $site);
    }

    public function createCron(ResourceRef $site, array $job): ProviderResult
    {
        $this->assertWebDomain($site); // a mail domain's id among web sites is a stranger's site
        [$minute, $hour, $dom, $month, $dow] = array_pad(explode(' ', trim($job['schedule'])), 5, '*');
        $id = (int) $this->api->call('sites_cron_add', ['client_id' => (int) ($site->meta['client_id'] ?? 0), 'params' => [
            'server_id' => (int) $site->node, 'parent_domain_id' => (int) $site->remoteId, 'type' => 'chrooted', 'command' => $job['command'], 'run_min' => $minute, 'run_hour' => $hour, 'run_mday' => $dom, 'run_month' => $month, 'run_wday' => $dow, 'active' => 'y', 'log' => 'y',
        ]], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), new ResourceRef('cron', (string) $id, $site->node, [], $site->serviceId));
    }

    public function tailLog(ResourceRef $site, string $log = 'access', int $lines = 200): array
    {
        // The remote API has no log endpoint — but the node keeps the site's own logs in `log/` next to `web/`, which is
        // exactly what the customer sees over SFTP, and the platform already reaches that directory as the site's agent
        // user. Without this a shared-hosting customer could not read their own access or error log in ONhost at all,
        // while a managed one could; „use the log pipeline“ was an answer for operators, not for the customer.
        $this->assertWebDomain($site);
        $name = $log === 'error' ? 'error.log' : 'access.log';
        $path = rtrim($this->siteDirInShell($site), '/').'/log/'.$name;
        $run = $this->shell($site)->run('tail -n '.max(1, min(5000, $lines)).' '.Q::arg($path), ['timeout' => 30]);
        if (! $run->ok()) {
            throw new ProviderException('ispconfig', ProviderErrorCode::NOT_FOUND, "The site has no {$name} yet (the node writes it once the site has been asked for something).", context: ['log' => $log]);
        }
        $all = preg_split('/\r?\n/', rtrim($run->stdout, "\n")) ?: [];

        return array_slice(array_filter($all, fn (string $line) => trim($line) !== ''), -$lines);
    }

    public function nodeLoad(?string $node = null): array
    {
        $serverId = $node === null ? $this->serverId() : (int) $node;
        $data = (array) $this->api->call('monitor_get_server_data', ['server_id' => $serverId]);
        $sites = (int) collect((array) $this->api->call('sites_web_domain_get', ['primary_id' => ['server_id' => $serverId]]))->count();

        // the size of the disk, when the monitor gives it: the placement rule works in gigabytes, not in percent
        $totalGb = isset($data['disk_total']) && is_numeric($data['disk_total']) ? (float) $data['disk_total'] / 1024 ** 3 : null;
        $usedGb = isset($data['disk_used']) && is_numeric($data['disk_used']) ? (float) $data['disk_used'] / 1024 ** 3 : null;

        return ['cpu_pct' => isset($data['cpu']) ? (float) $data['cpu'] : null, 'mem_pct' => isset($data['mem']) ? (float) $data['mem'] : null, 'disk_pct' => isset($data['disk']) ? (float) $data['disk'] : null,
            'disk_total_gb' => $totalGb, 'disk_used_gb' => $usedGb, 'load' => isset($data['load']) ? (float) $data['load'] : null, 'sites' => $sites];
    }

    public function backup(ResourceRef $ref, array $policy): ProviderResult
    {
        $this->assertWebDomain($ref); // a mail domain's id among web sites is a stranger's site
        // ISPConfig archives sites on the server's own schedule (backup_interval/backup_copies, set at provisioning) and its remote
        // API has no "make one now". This used to rewrite the schedule and report success — and the caller took last night's
        // archive for the backup it had asked for. A backup on demand is the platform's own archive (ServiceBackups).
        throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, 'ISPConfig makes site backups on its own nightly schedule; a backup on demand is made by the platform (files and databases over the agent user).');
    }

    public function listBackups(ResourceRef $ref): array
    {
        $this->assertWebDomain($ref); // a mail domain's id among web sites is a stranger's site
        $out = [];
        foreach ((array) $this->api->call('sites_web_domain_backup_list', ['site_id' => (int) $ref->remoteId]) as $b) {
            $out[] = ['remote_id' => (string) $b['backup_id'], 'created_at' => date('c', (int) ($b['tstamp'] ?? 0)), 'size_bytes' => isset($b['filesize']) ? (int) $b['filesize'] : null, 'verified' => null, 'protected' => null, 'meta' => ['type' => $b['backup_type'] ?? null, 'filename' => $b['filename'] ?? null]];
        }

        return $out;
    }

    public function restore(ResourceRef $ref, string $backupRemoteId, array $options = []): ProviderResult
    {
        $this->assertWebDomain($ref); // a mail domain's id among web sites is a stranger's site
        $this->backupAction($ref, $backupRemoteId, 'backup_restore');

        return ProviderResult::accepted($this->jobqueueHandle((int) $ref->node, ['backup_id' => $backupRemoteId], 20, 3600), $ref);
    }

    /**
     * `sites_web_domain_backup($session, $primary_id, $action_type)`: `primary_id` is the BACKUP's id and the action is
     * `backup_restore` | `backup_download` | `backup_delete` (the server plugin registers exactly these). The panel does not ask
     * whose backup it is — so the id has to stand in this site's own list first, or a number would restore a stranger's site.
     */
    public function backupAction(ResourceRef $site, string $backupRemoteId, string $action): void
    {
        $this->assertWebDomain($site);
        if (! in_array($action, ['backup_restore', 'backup_download', 'backup_delete'], true) || ! ctype_digit($backupRemoteId)) {
            throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, 'Unknown backup action or backup id.');
        }
        if (collect($this->listBackups($site))->firstWhere('remote_id', $backupRemoteId) === null) {
            throw new ProviderException('ispconfig', ProviderErrorCode::NOT_FOUND, 'The backup does not belong to this site');
        }
        $this->api->call('sites_web_domain_backup', ['primary_id' => (int) $backupRemoteId, 'action_type' => $action], true);
    }

    // ── Mail (legacy executor) ────────────────────────────────────────────────

    public function createMailDomain(ResourceSpec $spec): ProviderResult
    {
        // lower-case and carried in the reference: the binding is written from it, and every mailbox query needs it
        $domain = mb_strtolower(trim((string) $spec->get('domain')));
        $serverId = (int) ($this->instance->option('mail_server_id') ?: $this->serverId());
        $existing = collect((array) $this->api->call('mail_domain_get', ['primary_id' => ['domain' => $domain]]))->first();
        if (is_array($existing) && ! empty($existing['domain_id'])) {
            // A mail domain carries no system group, only the group of the client that owns it: ours when that is the
            // group of the organization's ONhost client. Anything else is somebody's historical mail — taking it over
            // would switch off its sending on a suspension and delete it with its mailboxes at the end.
            $ours = $this->findClientId($spec);
            $group = $ours === null ? null : $this->groupOf($ours);
            if ($group === null || (int) ($existing['sys_groupid'] ?? 0) !== $group) {
                throw new ProviderException('ispconfig', ProviderErrorCode::CONFLICT, "The mail domain {$domain} already exists on this server and was not created by ONhost. It is historical mail and is not taken over; adopting it is an explicit operator decision.");
            }

            return ProviderResult::completed(new ResourceRef('mail_domain', (string) $existing['domain_id'], (string) $serverId, ['domain' => $domain, 'client_id' => $ours, 'dkim_selector' => $existing['dkim_selector'] ?? null, 'dkim_public' => $existing['dkim_public'] ?? null], $spec->serviceId), alreadyExisted: true);
        }
        $clientId = $this->ensureClient($spec);
        $dkim = $this->generateDkim();
        $id = (int) $this->api->call('mail_domain_add', ['client_id' => $clientId, 'params' => [
            'server_id' => $serverId, 'domain' => $domain, 'active' => 'y', 'dkim' => 'y', 'dkim_selector' => $dkim['selector'], 'dkim_private' => $dkim['private'], 'dkim_public' => $dkim['public'],
        ]], true);

        return ProviderResult::accepted($this->jobqueueHandle($serverId), new ResourceRef('mail_domain', (string) $id, (string) $serverId, ['domain' => $domain, 'client_id' => $clientId, 'dkim_selector' => $dkim['selector'], 'dkim_public' => $dkim['public']], $spec->serviceId), ['domain_id' => $id, 'dkim_selector' => $dkim['selector'], 'dkim_public' => $dkim['public']]);
    }

    public function deleteMailDomain(ResourceRef $domain): ProviderResult
    {
        $this->api->call('mail_domain_delete', ['primary_id' => (int) $domain->remoteId], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $domain->node), null);
    }

    public function createMailbox(ResourceRef $domain, array $mailbox): ProviderResult
    {
        $existing = collect((array) $this->api->call('mail_user_get', ['primary_id' => ['email' => $mailbox['address']]]))->first();
        if (is_array($existing) && ! empty($existing['mailuser_id'])) {
            return ProviderResult::completed(new ResourceRef('mailbox', (string) $existing['mailuser_id'], $domain->node, ['email' => $mailbox['address']], $domain->serviceId), alreadyExisted: true);
        }
        [$local] = explode('@', (string) $mailbox['address'], 2);
        $id = (int) $this->api->call('mail_user_add', ['client_id' => (int) ($domain->meta['client_id'] ?? 0), 'params' => [
            'server_id' => (int) $domain->node, 'email' => $mailbox['address'], 'login' => $mailbox['address'], 'password' => $mailbox['password'], 'name' => $mailbox['name'] ?? $local,
            'quota' => (int) ($mailbox['quota_mb'] ?? 2048) * 1024 * 1024, 'cc' => '', 'maildir' => '', 'homedir' => '', 'uid' => 5000, 'gid' => 5000,
            'postfix' => 'y', 'access' => 'y', 'disableimap' => 'n', 'disablepop3' => 'n', 'disablesmtp' => 'n', 'disabledeliver' => 'n', 'disablesieve' => 'n', 'move_junk' => 'y',
        ]], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $domain->node), new ResourceRef('mailbox', (string) $id, $domain->node, ['email' => $mailbox['address']], $domain->serviceId), ['mailuser_id' => $id]);
    }

    public function updateMailbox(ResourceRef $mailbox, array $changes): ProviderResult
    {
        $params = array_filter(['quota' => isset($changes['quota_mb']) ? (int) $changes['quota_mb'] * 1024 * 1024 : null, 'password' => $changes['password'] ?? null, 'name' => $changes['name'] ?? null, 'disablesmtp' => isset($changes['sending_enabled']) ? ($changes['sending_enabled'] ? 'n' : 'y') : null], fn ($v) => $v !== null);
        $this->updateMailUser($mailbox, $params);

        return ProviderResult::accepted($this->jobqueueHandle((int) $mailbox->node), $mailbox);
    }

    /**
     * One field of a mailbox, without losing the rest of it. ISPConfig takes what an update sends as the whole record
     * — that is why a site update reads the row back and merges (`updateSite`) — and the mailbox path did not: turning
     * sending off, or renaming a mailbox, sent that one field alone and left the panel to fill in the rest from its
     * form defaults. The stored password is **never** sent back: what the panel returns is its hash, and a hash handed
     * in as a password would be hashed again and lock the customer out of their own mail.
     *
     * @param  array<string,mixed>  $params
     */
    private function updateMailUser(ResourceRef $mailbox, array $params): void
    {
        $row = $this->api->call('mail_user_get', ['primary_id' => (int) $mailbox->remoteId]);
        $current = is_array($row) && array_is_list($row) ? (array) ($row[0] ?? []) : (array) $row;
        $base = [];
        foreach ($current as $key => $value) {
            if (in_array($key, ['mailuser_id', 'password'], true) || str_starts_with((string) $key, 'sys_')) {
                continue;
            }
            $base[$key] = $value;
        }
        $this->api->call('mail_user_update', ['client_id' => (int) ($mailbox->meta['client_id'] ?? ($current['client_id'] ?? 0)), 'primary_id' => (int) $mailbox->remoteId, 'params' => array_merge($base, $params)], true);
    }

    public function deleteMailbox(ResourceRef $mailbox): ProviderResult
    {
        $this->api->call('mail_user_delete', ['primary_id' => (int) $mailbox->remoteId], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $mailbox->node), null);
    }

    public function createAlias(ResourceRef $domain, array $alias): ProviderResult
    {
        $id = (int) $this->api->call('mail_alias_add', ['client_id' => (int) ($domain->meta['client_id'] ?? 0), 'params' => ['server_id' => (int) $domain->node, 'source' => $alias['source'], 'destination' => $alias['destination'], 'type' => 'alias', 'active' => 'y']], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $domain->node), new ResourceRef('mail_alias', (string) $id, $domain->node, [], $domain->serviceId));
    }

    public function dkim(ResourceRef $domain): ?array
    {
        $record = $this->api->call('mail_domain_get', ['primary_id' => (int) $domain->remoteId]);
        if (! is_array($record) || empty($record['dkim_public'])) {
            return null;
        }
        $key = preg_replace('/-----[A-Z ]+-----|\s+/', '', (string) $record['dkim_public']) ?? '';

        return ['selector' => (string) $record['dkim_selector'], 'public_key' => $key, 'dns_record' => "v=DKIM1; k=rsa; p={$key}"];
    }

    public function setSendingEnabled(ResourceRef $domain, bool $enabled): ProviderResult
    {
        $users = (array) $this->api->call('mail_user_get', ['primary_id' => ['email' => '%@'.$this->mailDomainName($domain)]]);
        foreach ($users as $user) {
            $this->updateMailUser(new ResourceRef('mailbox', (string) $user['mailuser_id'], $domain->node, ['client_id' => $domain->meta['client_id'] ?? null], $domain->serviceId), ['disablesmtp' => $enabled ? 'n' : 'y']);
        }

        return ProviderResult::accepted($this->jobqueueHandle((int) $domain->node), $domain, ['mailboxes' => count($users), 'sending_enabled' => $enabled]);
    }

    // ── Site features (customer panel) ──────────────────────────────────────

    /** A fact `onhost:nodes:check` recorded on the instance (NodePrerequisites), null until the first check. */
    /**
     * What the remote user of THIS panel may really call (SelfProbing). The backup calls decide whether panel archives can be
     * restored at all; `sys_datalog_get_by_tstamp` decides whether a change can be followed by its own record instead of by
     * the length of the whole server's queue (production-readiness-audit §7, item 2) — its field names are written down so
     * that tracking is built on what the panel sends.
     */
    public function probes(?ResourceRef $anyResource = null): array
    {
        $out = [];
        $site = $anyResource !== null && in_array($anyResource->remoteType, ['web_domain', 'site'], true) ? $anyResource : null;
        $out['backup_api'] = $site === null ? 'skipped: no site on this instance yet' : $this->probe(fn () => $this->api->call('sites_web_domain_backup_list', ['site_id' => (int) $site->remoteId]));
        $rows = null;
        $out['datalog_api'] = $this->probe(function () use (&$rows): void {
            $rows = $this->api->call('sys_datalog_get_by_tstamp', ['tstamp' => time() - 3600]);
        });
        $first = is_array($rows) ? collect($rows)->first(fn ($row) => is_array($row)) : null;
        $out['datalog_fields'] = is_array($first) ? array_values(array_map('strval', array_keys($first))) : [];

        return $out;
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

    private function prerequisite(string $key): mixed
    {
        return $this->instance->capabilities['prereqs'][$key] ?? null;
    }

    public function siteFeatures(): array
    {
        return [
            // cron only where the node's cron API works (NodePrerequisites records `cron_api`); proxies only where the operator confirmed mod_proxy (instance option)
            'php' => true, 'databases' => true, 'ftp' => true, 'ssl' => true, 'https' => true, 'cron' => $this->prerequisite('cron_api') !== 'broken', 'logs' => true, 'backups' => true, 'restore' => true, // logs: read as the site's own agent user out of its `log/` directory
            'subdomains' => true, 'redirects' => true, 'ssh' => true, 'mail' => (bool) ($this->instance->capabilities['mail.create'] ?? false), 'file_manager' => true, 'usage' => true,
            // extended tabs: ISPConfig manages these through the remote API; the file manager runs over SFTP as the site's agent user; one-click apps it does not offer
            'errpages' => true, 'directives' => true, 'protected' => true, 'db_users' => true, 'stats' => true, 'ssl_upload' => true, 'files' => true, 'apps' => false, 'db_admin' => (bool) $this->instance->option('phpmyadmin_url'),
            // tools (WebToolsProvider) through the remote API and the jailed agent user; mail extras (MailToolsProvider) when the server carries mail
            'terminal' => true, 'php_settings' => true, 'security' => true, 'rate_limit' => false, 'http3' => false, 'cron_edit' => $this->prerequisite('cron_api') !== 'broken', 'cron_logs' => false, 'db_export' => true, 'db_access' => true,
            'backup_download' => true, 'backup_delete' => false, 'backup_on_demand' => false, 'mailbox_backup_on_demand' => false, 'files_advanced' => true, 'quotas' => true, 'node_projects' => false, 'staging' => true, 'deploy' => true, 'wordpress' => true, 'hsts' => true, 'panel_login' => true, 'proxy' => (string) $this->instance->option('mod_proxy', 'yes') !== 'no', 'default_docs' => true, // proxies and DirectoryIndex live in a managed block of the site directives (ManagedDirectives)
            'mail_tools' => (bool) ($this->instance->capabilities['mail.create'] ?? false),
        ];
    }

    public function phpVersions(): array
    {
        $key = "onhost:ispconfig:php:{$this->instance->id}";
        $cached = $this->cache->get($key);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }
        $versions = array_map('strval', array_keys((array) $this->instance->option('php_versions', [])));
        try {
            foreach ((array) $this->api->call('server_get_php_versions', ['server_id' => $this->serverId(), 'php' => 'php-fpm']) as $row) {
                $name = is_array($row) ? (string) ($row['name'] ?? ($row[1] ?? '')) : (string) $row;
                if (preg_match('/(\d\.\d)/', $name, $m)) {
                    $versions[] = $m[1];
                }
            }
        } catch (ProviderException $e) {
            if ($e->errorCode !== ProviderErrorCode::AUTH) {
                throw $e;
            }
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
        $this->assertWebDomain($site); // a mail domain's id among web sites is a stranger's site
        $out = [];
        foreach ((array) $this->api->call('sites_database_get', ['primary_id' => ['parent_domain_id' => (int) $site->remoteId]]) as $row) {
            if (! is_array($row) || empty($row['database_id'])) {
                continue;
            }
            $user = null;
            if (! empty($row['database_user_id'])) {
                $u = $this->api->call('sites_database_user_get', ['primary_id' => (int) $row['database_user_id']]);
                $user = is_array($u) ? ($u['database_user'] ?? null) : null;
            }
            $out[] = ['remote_id' => (string) $row['database_id'], 'name' => (string) $row['database_name'], 'user' => $user, 'charset' => $row['database_charset'] ?? null, 'size_bytes' => null];
        }

        return $out;
    }

    public function deleteDatabase(ResourceRef $site, string $remoteId): ProviderResult
    {
        $this->assertWebDomain($site); // a mail domain's id among web sites is a stranger's site
        if (collect($this->listDatabases($site))->firstWhere('remote_id', $remoteId) === null) {
            return ProviderResult::completed(null, ['deleted' => false], alreadyExisted: true);
        }
        $this->api->call('sites_database_delete', ['primary_id' => (int) $remoteId], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), null, ['deleted' => true]);
    }

    public function certificate(ResourceRef $site): array
    {
        $row = $this->getSite((int) $site->remoteId) ?? [];
        $domain = (string) ($row['domain'] ?? '');
        $le = ($row['ssl_letsencrypt'] ?? 'n') === 'y';

        return [
            'issued' => ($row['ssl'] ?? 'n') === 'y', 'letsencrypt' => $le, 'expires_at' => null, 'issuer' => $le ? "Let's Encrypt" : null,
            'domains' => $domain === '' ? [] : array_values(array_filter([$domain, ($row['subdomain'] ?? '') === 'www' ? "www.{$domain}" : null])), 'https_forced' => ($row['rewrite_to_https'] ?? 'n') === 'y',
        ];
    }

    public function listCron(ResourceRef $site): array
    {
        $this->assertWebDomain($site); // a mail domain's id among web sites is a stranger's site
        $out = [];
        foreach ((array) $this->api->call('sites_cron_get', ['primary_id' => ['parent_domain_id' => (int) $site->remoteId]]) as $row) {
            if (! is_array($row) || empty($row['id'])) {
                continue;
            }
            $out[] = ['remote_id' => (string) $row['id'], 'schedule' => implode(' ', [$row['run_min'] ?? '*', $row['run_hour'] ?? '*', $row['run_mday'] ?? '*', $row['run_month'] ?? '*', $row['run_wday'] ?? '*']), 'command' => (string) ($row['command'] ?? ''), 'label' => null, 'active' => ($row['active'] ?? 'y') === 'y'];
        }

        return $out;
    }

    public function deleteCron(ResourceRef $site, string $remoteId): ProviderResult
    {
        $this->assertWebDomain($site); // a mail domain's id among web sites is a stranger's site
        if (collect($this->listCron($site))->firstWhere('remote_id', $remoteId) === null) {
            return ProviderResult::completed(null, ['deleted' => false], alreadyExisted: true);
        }
        $this->api->call('sites_cron_delete', ['primary_id' => (int) $remoteId], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), null, ['deleted' => true]);
    }

    public function createFtpAccount(ResourceRef $site, array $account): ProviderResult
    {
        $this->assertWebDomain($site); // a mail domain's id among web sites is a stranger's site
        $existing = collect($this->listFtpAccounts($site))->firstWhere('user', $account['user']);
        if ($existing !== null) {
            return ProviderResult::completed(new ResourceRef('ftp', $existing['remote_id'], $site->node, ['user' => $account['user']], $site->serviceId), alreadyExisted: true);
        }
        $row = $this->getSite((int) $site->remoteId) ?? [];
        $root = rtrim((string) ($row['document_root'] ?? ($site->meta['document_root'] ?? '')), '/');
        $id = (int) $this->api->call('sites_ftp_user_add', ['client_id' => (int) ($site->meta['client_id'] ?? 0), 'params' => [
            'server_id' => (int) $site->node, 'parent_domain_id' => (int) $site->remoteId, 'username' => $account['user'], 'password' => $account['password'], 'quota_size' => (int) ($account['quota_mb'] ?? -1), 'active' => 'y',
            'uid' => (string) ($row['system_user'] ?? ($site->meta['system_user'] ?? '')), 'gid' => (string) ($row['system_group'] ?? ''), 'dir' => $root.'/'.ltrim((string) ($account['path'] ?? 'web'), '/'),
        ]], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), new ResourceRef('ftp', (string) $id, $site->node, ['user' => $account['user']], $site->serviceId), ['created' => true]);
    }

    public function listFtpAccounts(ResourceRef $site): array
    {
        $this->assertWebDomain($site); // a mail domain's id among web sites is a stranger's site
        $out = [];
        foreach ((array) $this->api->call('sites_ftp_user_get', ['primary_id' => ['parent_domain_id' => (int) $site->remoteId]]) as $row) {
            if (! is_array($row) || empty($row['ftp_user_id'])) {
                continue;
            }
            $out[] = ['remote_id' => (string) $row['ftp_user_id'], 'user' => (string) $row['username'], 'path' => $row['dir'] ?? null, 'active' => ($row['active'] ?? 'y') === 'y'];
        }

        return $out;
    }

    public function deleteFtpAccount(ResourceRef $site, string $remoteId): ProviderResult
    {
        $this->assertWebDomain($site); // a mail domain's id among web sites is a stranger's site
        if (collect($this->listFtpAccounts($site))->firstWhere('remote_id', $remoteId) === null) {
            return ProviderResult::completed(null, ['deleted' => false], alreadyExisted: true);
        }
        $this->api->call('sites_ftp_user_delete', ['primary_id' => (int) $remoteId], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), null, ['deleted' => true]);
    }

    public function setFtpPassword(ResourceRef $site, string $remoteId, string $password): ProviderResult
    {
        $row = collect($this->listFtpAccounts($site))->firstWhere('remote_id', $remoteId);
        if ($row === null) {
            throw new ProviderException('ispconfig', ProviderErrorCode::NOT_FOUND, 'FTP account not found on this site');
        }
        $this->api->call('sites_ftp_user_update', ['client_id' => (int) ($site->meta['client_id'] ?? 0), 'primary_id' => (int) $remoteId, 'params' => ['password' => $password]], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), new ResourceRef('ftp', $remoteId, $site->node, ['user' => $row['user']], $site->serviceId), ['password_changed' => true]);
    }

    public function setFtpAccountActive(ResourceRef $site, string $remoteId, bool $active): ProviderResult
    {
        $this->assertWebDomain($site);
        $row = collect((array) $this->api->call('sites_ftp_user_get', ['primary_id' => ['parent_domain_id' => (int) $site->remoteId]]))->first(fn ($r) => is_array($r) && (string) ($r['ftp_user_id'] ?? '') === $remoteId);
        if (! is_array($row)) {
            throw new ProviderException('ispconfig', ProviderErrorCode::NOT_FOUND, 'FTP account not found on this site');
        }
        // the whole record goes back with one field changed — WITHOUT the password: the panel returns its hash, and a hash sent back
        // as a password would be hashed again and lock the account for good
        $base = array_filter($row, fn ($value, $key) => ! in_array($key, ['ftp_user_id', 'password'], true) && ! str_starts_with((string) $key, 'sys_'), ARRAY_FILTER_USE_BOTH);
        $this->api->call('sites_ftp_user_update', ['client_id' => (int) ($site->meta['client_id'] ?? 0), 'primary_id' => (int) $remoteId, 'params' => array_merge($base, ['active' => $active ? 'y' : 'n'])], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), new ResourceRef('ftp', $remoteId, $site->node, ['user' => (string) ($row['username'] ?? '')], $site->serviceId), ['active' => $active]);
    }

    public function addSubdomain(ResourceRef $site, array $subdomain): ProviderResult
    {
        $domain = strtolower((string) $subdomain['domain']);
        if (collect($this->listSubdomains($site))->firstWhere('domain', $domain) !== null) {
            return ProviderResult::completed(new ResourceRef('site_domain', $domain, $site->node, ['domain' => $domain], $site->serviceId), alreadyExisted: true);
        }
        $path = trim((string) ($subdomain['path'] ?? ''), '/');
        $clientId = (int) ($site->meta['client_id'] ?? 0);
        if ($path !== '') { // sub-folder of the site: ISPConfig "subdomain (for website)" with an internal redirect
            $id = (int) $this->api->call('sites_web_subdomain_add', ['client_id' => $clientId, 'params' => ['server_id' => (int) $site->node, 'domain' => $domain, 'type' => 'subdomain', 'parent_domain_id' => (int) $site->remoteId, 'redirect_type' => 'L', 'redirect_path' => "/{$path}/", 'active' => 'y']], true);
            $remote = "sub:{$id}";
        } else { // another host name for the same site: alias domain
            $id = (int) $this->api->call('sites_web_aliasdomain_add', ['client_id' => $clientId, 'params' => ['server_id' => (int) $site->node, 'domain' => $domain, 'type' => 'alias', 'parent_domain_id' => (int) $site->remoteId, 'redirect_type' => '', 'redirect_path' => '', 'subdomain' => 'www', 'active' => 'y']], true);
            $remote = "alias:{$id}";
        }

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), new ResourceRef('site_domain', $remote, $site->node, ['domain' => $domain], $site->serviceId), ['added' => true]);
    }

    public function listSubdomains(ResourceRef $site): array
    {
        $this->assertWebDomain($site); // a mail domain's id among web sites is a stranger's site
        $out = [];
        foreach (['sub' => 'sites_web_subdomain_get', 'alias' => 'sites_web_aliasdomain_get'] as $kind => $function) {
            foreach ((array) $this->api->call($function, ['primary_id' => ['parent_domain_id' => (int) $site->remoteId]]) as $row) {
                if (! is_array($row) || empty($row['domain_id'])) {
                    continue;
                }
                $out[] = ['remote_id' => "{$kind}:{$row['domain_id']}", 'domain' => (string) $row['domain'], 'path' => $kind === 'sub' ? (($row['redirect_path'] ?? '') !== '' ? $row['redirect_path'] : null) : null];
            }
        }

        return $out;
    }

    public function removeSubdomain(ResourceRef $site, string $remoteId): ProviderResult
    {
        if (collect($this->listSubdomains($site))->firstWhere('remote_id', $remoteId) === null) {
            return ProviderResult::completed(null, ['removed' => false], alreadyExisted: true);
        }
        [$kind, $id] = array_pad(explode(':', $remoteId, 2), 2, '');
        $this->api->call($kind === 'alias' ? 'sites_web_aliasdomain_delete' : 'sites_web_subdomain_delete', ['primary_id' => (int) $id], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), null, ['removed' => true]);
    }

    public function setRedirect(ResourceRef $site, array $redirect): ProviderResult
    {
        $target = trim((string) ($redirect['target'] ?? ''));
        $params = $target === '' ? ['redirect_type' => '', 'redirect_path' => ''] : ['redirect_type' => ((string) ($redirect['type'] ?? '301')) === '302' ? 'R,L' : 'R=301,L', 'redirect_path' => $target];
        $this->updateSite($site, $params);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), $site, ['redirect' => $target === '' ? null : $target]);
    }

    public function redirect(ResourceRef $site): array
    {
        $row = $this->getSite((int) $site->remoteId) ?? [];
        $path = (string) ($row['redirect_path'] ?? '');

        return ['target' => $path !== '' ? $path : null, 'type' => $path === '' ? null : (str_contains((string) ($row['redirect_type'] ?? ''), '301') ? '301' : '302')];
    }

    public function listMailboxes(ResourceRef $domain): array
    {
        $out = [];
        foreach ((array) $this->api->call('mail_user_get', ['primary_id' => ['email' => '%@'.$this->mailDomainName($domain)]]) as $row) {
            if (! is_array($row) || empty($row['mailuser_id'])) {
                continue;
            }
            $out[] = ['remote_id' => (string) $row['mailuser_id'], 'address' => (string) $row['email'], 'name' => $row['name'] ?? null, 'quota_mb' => isset($row['quota']) ? (int) round((int) $row['quota'] / 1048576) : null, 'used_mb' => null, 'active' => ($row['postfix'] ?? 'y') === 'y' && ($row['disabledeliver'] ?? 'n') !== 'y', 'sending' => ($row['disablesmtp'] ?? 'n') !== 'y'];
        }

        return $out;
    }

    public function listAliases(ResourceRef $domain): array
    {
        $out = [];
        foreach ((array) $this->api->call('mail_alias_get', ['primary_id' => ['source' => '%@'.$this->mailDomainName($domain)]]) as $row) {
            if (! is_array($row) || empty($row['forwarding_id'])) {
                continue;
            }
            $out[] = ['remote_id' => (string) $row['forwarding_id'], 'source' => (string) $row['source'], 'destination' => (string) $row['destination'], 'active' => ($row['active'] ?? 'y') === 'y'];
        }

        return $out;
    }

    public function deleteAlias(ResourceRef $alias): ProviderResult
    {
        $this->api->call('mail_alias_delete', ['primary_id' => (int) $alias->remoteId], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $alias->node), null, ['deleted' => true]);
    }

    /**
     * ISPConfig applies nothing in the response: the server cron reads `sys_datalog` and writes the outcome back into
     * that row. The adapter used to watch the length of the whole server's queue — so an empty queue meant "succeeded"
     * even when OUR job had failed, and somebody else's writes on a busy server kept us waiting until the timeout
     * (audit §2). When the write could be named (`IspConfigConnector::lastWrite`) and this panel's change log really
     * reports a status, the row of our own job decides. Anything else keeps the old behaviour: never worse than before.
     */
    public function awaitStatus(AsyncHandle $handle): AsyncStatus
    {
        $serverId = (int) ($handle->meta['server_id'] ?? $handle->node ?? $this->serverId());
        $row = $this->datalogRow($handle, $serverId);
        if ($row !== null) {
            $status = strtolower(trim((string) ($row['status'] ?? '')));
            $error = trim((string) ($row['error'] ?? ''));
            if ($status === 'ok' || ($status === '' && $error === '' && ($row['processed'] ?? 0))) {
                return AsyncStatus::succeeded(['server_id' => $serverId, 'datalog_id' => $row['datalog_id'] ?? null]);
            }
            if ($status === 'error' || $error !== '') {
                return AsyncStatus::failed('ISPConfig applied nothing: '.mb_substr($error !== '' ? $error : 'the server reported an error', 0, 300), ['server_id' => $serverId, 'datalog_id' => $row['datalog_id'] ?? null]);
            }

            return AsyncStatus::running('the server has not applied this change yet', ['datalog_id' => $row['datalog_id'] ?? null]);
        }
        $count = (int) $this->api->call('monitor_jobqueue_count', ['server_id' => $serverId]);

        return $count === 0 ? AsyncStatus::succeeded(['server_id' => $serverId, 'meta' => $handle->meta]) : AsyncStatus::running("{$count} jobs pending on server {$serverId}", ['jobqueue' => $count]);
    }

    /**
     * Our own row in the change log, or null when this panel cannot be followed that way (the write had no name, the
     * panel's change log carries no status, the function is not allowed to this user, or the row is not there yet).
     *
     * @return array<string,mixed>|null
     */
    private function datalogRow(AsyncHandle $handle, int $serverId): ?array
    {
        $write = (array) ($handle->meta['datalog'] ?? []);
        if (($write['dbtable'] ?? '') === '' || ($write['dbidx'] ?? '') === '') {
            return null;
        }
        // opt in on evidence: the nightly node check reads the change log and writes down which fields it really returns.
        // Until this panel has answered with a `status`, nothing changes — an older ISPConfig, or a remote user without
        // that function group, keeps the queue count it always had.
        $fields = (array) data_get($this->instance->capabilities, 'prereqs.probes.datalog_fields', []);
        if (! in_array('status', $fields, true)) {
            return null;
        }
        try {
            $rows = (array) $this->api->call('sys_datalog_get_by_tstamp', ['tstamp' => max(0, (int) ($write['at'] ?? time()) - 5)]);
        } catch (ProviderException) {
            return null; // the remote user may lack this function group; the queue count still works
        }
        $ours = null;
        foreach ($rows as $row) {
            if (! is_array($row) || (string) ($row['dbtable'] ?? '') !== (string) $write['dbtable'] || (string) ($row['dbidx'] ?? '') !== (string) $write['dbidx']) {
                continue;
            }
            if (isset($row['server_id']) && (int) $row['server_id'] !== $serverId && (int) $row['server_id'] !== 0) {
                continue;
            }
            $ours = $row; // the newest row for this record wins: the list is ordered oldest first
        }

        return $ours;
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    public function serverId(): int
    {
        $configured = (int) $this->instance->option('server_id', 0);
        if ($configured > 0) {
            return $configured;
        }
        $key = "onhost:ispconfig:server_id:{$this->instance->id}";
        $cached = $this->cache->get($key);
        if (is_int($cached)) {
            return $cached;
        }
        // Resolution order: option server_ip → the panel host itself (single-server installs) → server 1.
        // Multi-server installations set option server_id explicitly (docs/runbooks/provider-onboarding.md).
        $ip = (string) $this->instance->option('server_ip', '');
        if ($ip === '') {
            $host = (string) parse_url((string) $this->instance->base_url, PHP_URL_HOST);
            $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : (string) gethostbyname($host);
        }
        $id = 0;
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            try {
                $id = (int) $this->api->call('server_get_serverid_by_ip', ['ipaddress' => $ip]);
            } catch (ProviderException $e) {
                if ($e->errorCode !== ProviderErrorCode::AUTH) {
                    throw $e;
                }
            }
        }
        if ($id <= 0) {
            $id = 1;
        }
        $this->cache->put($key, $id, 3600);

        return $id;
    }

    /**
     * The name of a mail domain, for the `LIKE '%@<name>'` every mailbox, alias and spam query is built on. Taken from
     * the binding, else asked of the panel by the domain's own id — and never empty: `'%@'` is every mailbox on the
     * shared mail server, historical customers' included. A mail service used to be bound without the name, so its
     * suspension switched off sending for the whole server.
     */
    private function mailDomainName(ResourceRef $domain): string
    {
        $name = mb_strtolower(trim((string) ($domain->meta['domain'] ?? '')));
        if ($name === '' && $domain->remoteType === 'mail_domain' && (int) $domain->remoteId > 0) {
            $row = $this->getMailDomain((int) $domain->remoteId);
            $name = mb_strtolower(trim((string) ($row['domain'] ?? '')));
        }
        if ($name === '') {
            throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, 'The mail domain has no name on record; refusing to query every mailbox on the mail server.');
        }

        return $name;
    }

    /** The ISPConfig client the platform made for this organization (`onh_…`), looked up without creating anything. */
    private function findClientId(ResourceSpec $spec): ?int
    {
        $existing = $this->clientRecord(self::clientUsername($spec));

        return is_array($existing) && ! empty($existing['client_id']) ? (int) $existing['client_id'] : null;
    }

    private static function clientUsername(ResourceSpec $spec): string
    {
        return 'onh_'.substr(preg_replace('/[^a-z0-9]/', '', strtolower((string) $spec->organizationId)) ?? '', 4, 20);
    }

    /** @return array<string,mixed>|null */
    private function clientRecord(string $username): ?array
    {
        try {
            $existing = $this->api->call('client_get_by_username', ['username' => $username]);
        } catch (ProviderException $e) {
            if (! str_contains(mb_strtolower($e->getMessage()), 'no user account')) {
                throw $e;
            }

            return null; // ISPConfig 3.2 answers an unknown username with a fault instead of an empty result
        }

        return is_array($existing) ? $existing : null;
    }

    /**
     * Whether a web domain row belongs to the given client. ISPConfig names the site's system group after its client
     * and roots the site under that client's directory (`client12`, `/var/www/clients/client12/web77`) — either one
     * proves it, and a site made by hand under another client has neither.
     *
     * @param  array<string,mixed>  $site
     */
    private static function siteOwnedBy(array $site, int $clientId): bool
    {
        return (string) ($site['system_group'] ?? '') === "client{$clientId}"
            || str_starts_with((string) ($site['document_root'] ?? ''), "/var/www/clients/client{$clientId}/");
    }

    /** The system group of a client — what a mail domain names as its owner. Null when the panel will not say. */
    private function groupOf(int $clientId): ?int
    {
        try {
            $group = $this->api->call('client_get_groupid', ['client_id' => $clientId]);
        } catch (ProviderException $e) {
            if ($e->isRetryable()) {
                throw $e; // "not now" is asked again, not turned into a final "not yours"
            }

            return null; // the panel refuses to say: not proven is not ours, and the caller refuses
        }

        return is_numeric($group) && (int) $group > 0 ? (int) $group : null;
    }

    private function ensureClient(ResourceSpec $spec): int
    {
        $username = self::clientUsername($spec);
        $existing = $this->clientRecord($username);
        // One client per organization, so its limits are the ORGANIZATION's — what it holds on this panel across all
        // of its services (`ClientAllowance`), not what the service that happens to be provisioning sells. Written
        // once from one plan they were wrong for everyone else: two ordinary hostings (`sites = 1` each) left the
        // client at `limit_web_domain = 1`, and ISPConfig refused the second site **after the customer had paid**.
        $ent = (array) ($spec->get('client_entitlements') ?: $spec->get('entitlements', []));
        if (is_array($existing) && ! empty($existing['client_id'])) {
            $clientId = (int) $existing['client_id'];
            $this->updateClientLimits($clientId, $ent); // it calls the panel only when something really differs

            return $clientId;
        }
        $clientId = (int) $this->api->call('client_add', ['reseller_id' => 0, 'params' => [
            'company_name' => (string) $spec->get('organization_name', $spec->organizationId), 'contact_name' => (string) $spec->get('contact_name', 'ONhost customer'), 'email' => (string) $spec->get('contact_email', ''),
            'username' => $username, 'password' => self::panelPassword(), 'language' => 'cz', 'usertheme' => 'default', 'country' => 'CZ',
            'default_webserver' => $this->serverId(), 'default_mailserver' => (int) ($this->instance->option('mail_server_id') ?: $this->serverId()), 'default_dnsserver' => $this->serverId(), 'default_dbserver' => $this->serverId(),
            'limit_web_domain' => (int) ($ent['sites'] ?? 1), 'limit_web_quota' => (int) (($ent['nvme_gb'] ?? 10) * 1024), 'limit_database' => (int) ($ent['databases'] ?? 1),
            'limit_maildomain' => (int) ($ent['domains'] ?? 3), 'limit_mailbox' => (int) ($ent['mailboxes'] ?? 5), 'limit_mailquota' => (int) (($ent['quota_gb_per_mailbox'] ?? 2) * 1024),
            'limit_shell_user' => ! empty($ent['ssh']) ? 1 : 0, 'limit_cron' => (int) ($ent['cron_concurrency'] ?? 1), 'limit_dns_zone' => 0, 'limit_client' => 0, 'limit_ftp_user' => 1, 'web_php_options' => 'php-fpm', 'ssh_chroot' => 'jailkit', 'locked' => 'n', 'canceled' => 'n',
        ]], true);

        return $clientId;
    }

    /**
     * ISPConfig's client password policy ("Very Strong", ≥ 13 characters) needs upper- and lower-case letters, digits and
     * symbols; the panel login is never used by anyone (customers work through ONhost), so the value is discarded.
     */
    public static function panelPassword(int $length = 24): string
    {
        $classes = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghjkmnpqrstuvwxyz', '23456789', '!#%*+-=?@_'];
        $chars = [];
        foreach ($classes as $set) {
            for ($i = 0; $i < 3; $i++) {
                $chars[] = $set[random_int(0, strlen($set) - 1)];
            }
        }
        $all = implode('', $classes);
        while (count($chars) < $length) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        shuffle($chars);

        return implode('', $chars);
    }

    private function findSite(string $domain): ?array
    {
        $rows = (array) $this->api->call('sites_web_domain_get', ['primary_id' => ['domain' => $domain]]);
        $row = collect($rows)->first();

        return is_array($row) && ! empty($row['domain_id']) ? $row : null;
    }

    /** A lifecycle call on a resource that is not a web domain must never reach the web domain functions (numbers overlap between ISPConfig tables). */
    private function assertWebDomain(ResourceRef $ref): void
    {
        if ($ref->remoteType !== 'web_domain' && $ref->remoteType !== 'site') {
            throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, "ISPConfig lifecycle call for a {$ref->remoteType} resource; only web and mail domains are supported.");
        }
    }

    /** @return array<string,mixed>|null */
    private function getMailDomain(int $domainId): ?array
    {
        $row = $this->api->call('mail_domain_get', ['primary_id' => $domainId]);

        return is_array($row) && ! empty($row['domain_id']) ? $row : null;
    }

    private function setMailDomainActive(ResourceRef $ref, bool $active): void
    {
        $current = $this->getMailDomain((int) $ref->remoteId);
        if ($current === null) {
            throw new ProviderException('ispconfig', ProviderErrorCode::NOT_FOUND, "ISPConfig mail domain {$ref->remoteId} no longer exists.");
        }
        $params = array_filter($current, fn ($v, $k) => ! str_starts_with((string) $k, 'sys_') && $k !== 'domain_id', ARRAY_FILTER_USE_BOTH);
        $params['active'] = $active ? 'y' : 'n';
        $this->api->call('mail_domain_update', ['client_id' => (int) ($ref->meta['client_id'] ?? $current['sys_groupid'] ?? 0), 'primary_id' => (int) $ref->remoteId, 'params' => $params], true);
    }

    private function getSite(int $domainId): ?array
    {
        $row = $this->api->call('sites_web_domain_get', ['primary_id' => $domainId]);

        return is_array($row) && ! empty($row['domain_id']) ? $row : null;
    }

    private function jobqueueHandle(int $serverId, array $meta = [], int $poll = 5, int $timeout = 900): AsyncHandle
    {
        // the change-log row this write becomes, so awaitStatus can watch our own job instead of the whole server's queue
        $write = $this->api->lastWrite();

        return new AsyncHandle('ispconfig_jobqueue', "jobqueue:{$serverId}:".now()->timestamp, (string) $serverId,
            array_merge(['server_id' => $serverId], $write === null ? [] : ['datalog' => $write], $meta), $poll, $timeout);
    }

    private function phpVersionString(string $version): string
    {
        $versions = (array) $this->instance->option('php_versions', []);

        return (string) ($versions[$version] ?? "PHP {$version}:/usr/bin/php-fpm{$version}:/etc/php/{$version}/fpm");
    }

    /** @return array{selector:string, private:string, public:string} */
    private function generateDkim(): array
    {
        // with a configuration of its own: OpenSSL refuses to make a key on a host that has no openssl.cnf, and a mail
        // domain cannot be created without one — the same trap the ACME account key was in
        $config = tempnam(sys_get_temp_dir(), 'dkim');
        file_put_contents($config, "[req]\ndefault_bits = 2048\ndefault_md = sha256\ndistinguished_name = dn\n[dn]\n");
        try {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $config]);
            if ($key === false || ! openssl_pkey_export($key, $private, null, ['config' => $config])) {
                throw new ProviderException('ispconfig', ProviderErrorCode::PROVIDER_BUG, 'Unable to generate DKIM key pair: '.(string) openssl_error_string());
            }
        } finally {
            @unlink($config);
        }
        $details = openssl_pkey_get_details($key);

        return ['selector' => 'onhost'.date('Ym'), 'private' => (string) $private, 'public' => (string) ($details['key'] ?? '')];
    }

    // ── extended site management (blueprint: the customer gets everything the panel offers for the service) ────

    public function siteSettings(ResourceRef $site): array
    {
        $row = $this->getSite((int) $site->remoteId) ?? [];
        $domain = (string) ($row['domain'] ?? ($site->meta['domain'] ?? ''));
        $stats = (string) ($row['stats_type'] ?? '');

        return [
            'errordocs' => isset($row['errordocs']) ? in_array((string) $row['errordocs'], ['1', 'y'], true) : null,
            // the customer's own directives: the managed security and tools blocks are edited through their own tabs
            'directives' => ['apache' => ManagedDirectives::strip(SecurityRules::strip((string) ($row['apache_directives'] ?? ''))), 'nginx' => ManagedDirectives::strip(SecurityRules::strip((string) ($row['nginx_directives'] ?? '')))],
            'stats' => ['type' => $stats !== '' ? $stats : null, 'url' => $domain !== '' ? "https://{$domain}/stats/" : null, 'user' => 'admin'],
            'db_admin_url' => $this->instance->option('phpmyadmin_url') ?: null, 'document_root' => (string) ($row['document_root'] ?? ($site->meta['document_root'] ?? '')), 'site_password' => null,
        ];
    }

    public function setErrorDocs(ResourceRef $site, bool $enabled): ProviderResult
    {
        $this->updateSite($site, ['errordocs' => $enabled ? 1 : 0]);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), $site, ['errordocs' => $enabled]);
    }

    public function setDirectives(ResourceRef $site, string $kind, string $content): ProviderResult
    {
        $field = match ($kind) {
            'apache' => 'apache_directives', 'nginx' => 'nginx_directives', default => throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, "Directive kind {$kind} is not offered by this panel")
        };
        // the customer's text replaces only the customer's part: the managed security and tools blocks are kept as they stand
        $current = (string) (($this->getSite((int) $site->remoteId) ?? [])[$field] ?? '');
        $content = ManagedDirectives::strip(SecurityRules::strip($content));
        foreach ([SecurityRules::extract($current), ManagedDirectives::extract($current)] as $block) {
            if ($block !== '') {
                $content = ($content === '' ? '' : rtrim($content)."\n").$block;
            }
        }
        $this->updateSite($site, [$field => $content]);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), $site, ['kind' => $kind, 'bytes' => strlen($content)]);
    }

    public function listProtectedFolders(ResourceRef $site): array
    {
        $out = [];
        foreach ((array) $this->api->call('sites_web_folder_get', ['primary_id' => ['parent_domain_id' => (int) $site->remoteId]]) as $row) {
            if (! is_array($row) || empty($row['web_folder_id'])) {
                continue;
            }
            $users = [];
            foreach ((array) $this->api->call('sites_web_folder_user_get', ['primary_id' => ['web_folder_id' => (int) $row['web_folder_id']]]) as $u) {
                if (is_array($u) && ! empty($u['username'])) {
                    $users[] = (string) $u['username'];
                }
            }
            $out[] = ['remote_id' => (string) $row['web_folder_id'], 'path' => (string) ($row['path'] ?? '/'), 'users' => $users, 'active' => (($row['active'] ?? 'y') === 'y')];
        }

        return $out;
    }

    public function protectFolder(ResourceRef $site, array $spec): ProviderResult
    {
        $clientId = (int) ($site->meta['client_id'] ?? 0);
        $path = '/'.trim((string) $spec['path'], '/');
        $existing = collect($this->listProtectedFolders($site))->firstWhere('path', $path);
        $folderId = $existing !== null ? (int) $existing['remote_id'] : (int) $this->api->call('sites_web_folder_add', ['client_id' => $clientId, 'params' => ['server_id' => (int) $site->node, 'parent_domain_id' => (int) $site->remoteId, 'path' => $path, 'active' => 'y']], true);
        $this->api->call('sites_web_folder_user_add', ['client_id' => $clientId, 'params' => ['server_id' => (int) $site->node, 'web_folder_id' => $folderId, 'username' => (string) $spec['user'], 'password' => (string) $spec['password'], 'active' => 'y']], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), new ResourceRef('web_folder', (string) $folderId, $site->node, ['path' => $path], $site->serviceId), ['path' => $path, 'user' => $spec['user']]);
    }

    public function unprotectFolder(ResourceRef $site, string $remoteId): ProviderResult
    {
        if (collect($this->listProtectedFolders($site))->firstWhere('remote_id', $remoteId) === null) {
            return ProviderResult::completed(null, ['deleted' => false], alreadyExisted: true);
        }
        foreach ((array) $this->api->call('sites_web_folder_user_get', ['primary_id' => ['web_folder_id' => (int) $remoteId]]) as $u) {
            if (is_array($u) && ! empty($u['web_folder_user_id'])) {
                $this->api->call('sites_web_folder_user_delete', ['primary_id' => (int) $u['web_folder_user_id']], true);
            }
        }
        $this->api->call('sites_web_folder_delete', ['primary_id' => (int) $remoteId], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), null, ['deleted' => true]);
    }

    public function listDbUsers(ResourceRef $site): array
    {
        $users = [];
        foreach ((array) $this->api->call('sites_database_get', ['primary_id' => ['parent_domain_id' => (int) $site->remoteId]]) as $db) {
            if (! is_array($db) || empty($db['database_user_id'])) {
                continue;
            }
            $id = (string) $db['database_user_id'];
            if (! isset($users[$id])) {
                $u = $this->api->call('sites_database_user_get', ['primary_id' => (int) $id]);
                $users[$id] = ['remote_id' => $id, 'user' => (string) (is_array($u) ? ($u['database_user'] ?? '') : ''), 'databases' => []];
            }
            $users[$id]['databases'][] = (string) ($db['database_name'] ?? '');
        }

        return array_values($users);
    }

    public function createDbUser(ResourceRef $site, array $spec): ProviderResult
    {
        $existing = collect($this->listDbUsers($site))->firstWhere('user', $spec['user']);
        if ($existing !== null) {
            return ProviderResult::completed(new ResourceRef('database_user', $existing['remote_id'], $site->node, ['user' => $spec['user']], $site->serviceId), alreadyExisted: true);
        }
        $id = (int) $this->api->call('sites_database_user_add', ['client_id' => (int) ($site->meta['client_id'] ?? 0), 'params' => ['server_id' => (int) $site->node, 'database_user' => (string) $spec['user'], 'database_password' => (string) $spec['password']]], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), new ResourceRef('database_user', (string) $id, $site->node, ['user' => $spec['user']], $site->serviceId), ['created' => true]);
    }

    public function setDbUserPassword(ResourceRef $site, string $remoteId, string $password): ProviderResult
    {
        $this->assertWebDomain($site);
        $this->ownRow($this->listDbUsers($site), $remoteId, 'Database user');
        $this->api->call('sites_database_user_update', ['client_id' => (int) ($site->meta['client_id'] ?? 0), 'primary_id' => (int) $remoteId, 'params' => ['database_password' => $password]], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), new ResourceRef('database_user', $remoteId, $site->node, [], $site->serviceId), ['password_changed' => true]);
    }

    public function deleteDbUser(ResourceRef $site, string $remoteId): ProviderResult
    {
        $this->assertWebDomain($site);
        // the lookup used to feed only the conflict below, so an id that is NOT ours (`$user === null`) fell straight
        // through to the delete — a neighbour's database user, gone
        $user = $this->ownRow($this->listDbUsers($site), $remoteId, 'Database user');
        if ($user['databases'] !== []) {
            throw new ProviderException('ispconfig', ProviderErrorCode::CONFLICT, 'The user still owns databases: '.implode(', ', $user['databases']));
        }
        $this->api->call('sites_database_user_delete', ['primary_id' => (int) $remoteId], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), null, ['deleted' => true]);
    }

    public function listShellUsers(ResourceRef $site): array
    {
        $this->assertWebDomain($site); // a mail domain's id among web sites is a stranger's site
        $out = [];
        foreach ((array) $this->api->call('sites_shell_user_get', ['primary_id' => ['parent_domain_id' => (int) $site->remoteId]]) as $row) {
            if (! is_array($row) || empty($row['shell_user_id'])) {
                continue;
            }
            $out[] = ['remote_id' => (string) $row['shell_user_id'], 'user' => (string) ($row['username'] ?? ''), 'has_key' => trim((string) ($row['ssh_rsa'] ?? '')) !== '', 'chroot' => (($row['chroot'] ?? '') !== ''), 'active' => (($row['active'] ?? 'y') === 'y')];
        }

        return $out;
    }

    public function createShellUser(ResourceRef $site, array $spec): ProviderResult
    {
        $this->assertWebDomain($site); // a mail domain's id among web sites is a stranger's site
        $existing = collect($this->listShellUsers($site))->firstWhere('user', $spec['user']);
        if ($existing !== null) {
            return ProviderResult::completed(new ResourceRef('shell_user', $existing['remote_id'], $site->node, ['user' => $spec['user']], $site->serviceId), alreadyExisted: true);
        }
        $row = $this->getSite((int) $site->remoteId) ?? [];
        $id = (int) $this->api->call('sites_shell_user_add', ['client_id' => (int) ($site->meta['client_id'] ?? 0), 'params' => [
            'server_id' => (int) $site->node, 'parent_domain_id' => (int) $site->remoteId, 'username' => (string) $spec['user'], 'password' => (string) $spec['password'], 'quota_size' => -1, 'active' => 'y',
            'puser' => (string) ($row['system_user'] ?? ($site->meta['system_user'] ?? '')), 'pgroup' => (string) ($row['system_group'] ?? ''), 'shell' => '/bin/bash', 'dir' => (string) ($row['document_root'] ?? ($site->meta['document_root'] ?? '')),
            'chroot' => 'jailkit', 'ssh_rsa' => (string) ($spec['ssh_key'] ?? ''),
        ]], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), new ResourceRef('shell_user', (string) $id, $site->node, ['user' => $spec['user']], $site->serviceId), ['created' => true]);
    }

    /**
     * The row of this site's own listing that an id names — or a refusal.
     *
     * The panel is reached through ONE administrator session per instance and never asks whose record a `primary_id`
     * is; the platform checks only the SHAPE of an id a customer sends. On a shared node the ids are consecutive, so
     * an id that arrives from outside is not proof of anything until it is found in what this site owns. Nine of the
     * methods here always did this inline; the three that did not let a customer reach a neighbour's shell user and
     * database user.
     *
     * @param  list<array<string,mixed>>  $owned
     * @return array<string,mixed>
     */
    private function ownRow(array $owned, string $remoteId, string $what): array
    {
        $row = collect($owned)->firstWhere('remote_id', $remoteId);
        if (! is_array($row)) {
            throw new ProviderException('ispconfig', ProviderErrorCode::NOT_FOUND, "{$what} not found on this site");
        }

        return $row;
    }

    public function setShellKey(ResourceRef $site, string $remoteId, string $sshKey): ProviderResult
    {
        $this->assertWebDomain($site);
        $this->ownRow($this->listShellUsers($site), $remoteId, 'Shell user');
        $this->api->call('sites_shell_user_update', ['client_id' => (int) ($site->meta['client_id'] ?? 0), 'primary_id' => (int) $remoteId, 'params' => ['ssh_rsa' => $sshKey]], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), new ResourceRef('shell_user', $remoteId, $site->node, [], $site->serviceId), ['key_set' => $sshKey !== '']);
    }

    public function deleteShellUser(ResourceRef $site, string $remoteId): ProviderResult
    {
        $this->assertWebDomain($site); // a mail domain's id among web sites is a stranger's site
        if (collect($this->listShellUsers($site))->firstWhere('remote_id', $remoteId) === null) {
            return ProviderResult::completed(null, ['deleted' => false], alreadyExisted: true);
        }
        $this->api->call('sites_shell_user_delete', ['primary_id' => (int) $remoteId], true);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), null, ['deleted' => true]);
    }

    public function setStats(ResourceRef $site, array $spec): ProviderResult
    {
        $type = (string) ($spec['type'] ?? 'awstats');
        $params = ['stats_type' => $type === 'none' ? '' : $type];
        if (! empty($spec['password'])) {
            $params['stats_password'] = (string) $spec['password'];
        }
        $this->updateSite($site, $params);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), $site, ['stats_type' => $type]);
    }

    public function uploadCertificate(ResourceRef $site, array $cert): ProviderResult
    {
        $this->updateSite($site, ['ssl' => 'y', 'ssl_letsencrypt' => 'n', 'ssl_cert' => (string) $cert['cert'], 'ssl_key' => (string) $cert['key'], 'ssl_bundle' => (string) ($cert['chain'] ?? ''), 'ssl_action' => 'save']);

        return ProviderResult::accepted($this->jobqueueHandle((int) $site->node), $site, ['uploaded' => true]);
    }

    /* The panel has no file API: the file manager runs over the site's agent user (SFTP, IspConfigTools). Until the
     * agent exists the first call prepares it and the customer is told to come back in a minute. */
    private function fileTransport(ResourceRef $site): FileTransport
    {
        if (! $this->shellAvailable($site)) {
            $this->ensureAgent($site);
            throw new ProviderException('ispconfig', ProviderErrorCode::TRANSIENT, 'The file manager is being prepared for this site; try again in a minute', null, [], 60);
        }

        return $this->transport($site);
    }

    public function listFiles(ResourceRef $site, string $path): array
    {
        $listing = $this->fileTransport($site)->list($path);

        return ['path' => $listing['path'], 'entries' => array_map(fn (array $e) => ['name' => $e['name'], 'type' => $e['type'], 'size' => $e['size'], 'modified' => $e['modified'], 'perm' => $e['mode'] ?? null], $listing['entries'])];
    }

    public function readFile(ResourceRef $site, string $path): string
    {
        return $this->fileTransport($site)->read($path, 20 * 1024 * 1024);
    }

    public function writeFile(ResourceRef $site, string $path, string $content): ProviderResult
    {
        $this->fileTransport($site)->write($path, $content);

        return ProviderResult::completed(new ResourceRef('file', $path, $site->node, [], $site->serviceId), ['written' => true]);
    }

    public function deleteFile(ResourceRef $site, string $path, bool $directory = false): ProviderResult
    {
        $this->fileTransport($site)->delete($path, $directory);

        return ProviderResult::completed(null, ['deleted' => true]);
    }

    public function createDirectory(ResourceRef $site, string $path): ProviderResult
    {
        $this->fileTransport($site)->mkdir($path);

        return ProviderResult::completed(new ResourceRef('file', $path, $site->node, [], $site->serviceId), ['created' => true]);
    }

    public function listApps(ResourceRef $site): array
    {
        return [];
    }

    public function installApp(ResourceRef $site, array $spec): ProviderResult
    {
        throw new ProviderException('ispconfig', ProviderErrorCode::VALIDATION, 'This panel offers no one-click application installer');
    }

    /** @param array<string,mixed> $params partial web_domain update */
    /**
     * ISPConfig validates the whole record on `sites_web_domain_update` (server, domain, quota …), so a change is
     * applied as get → merge → update. Record identity and stored certificate material are never sent back.
     */
    /**
     * The limits of the ISPConfig client follow the plan. They were written once, when the client was created, and a
     * plan change never touched them: a customer who paid for „10 webů“ and 50 GB still had `limit_web_domain = 1` and
     * the old quota at the panel, so the panel refused the second site and the bigger quota — the upgrade was paid for
     * and not delivered. ISPConfig replaces the whole record on update, so the client is read back and merged.
     *
     * @param  array<string,mixed>  $ent
     * @return list<string> the limits that were changed (empty when the panel already had them)
     */
    private function updateClientLimits(int $clientId, array $ent): array
    {
        if ($clientId <= 0 || $ent === []) {
            return [];
        }
        $wanted = array_filter([
            'limit_web_domain' => isset($ent['sites']) ? (int) $ent['sites'] : null,
            'limit_web_quota' => isset($ent['nvme_gb']) ? (int) $ent['nvme_gb'] * 1024 : null,
            'limit_database' => isset($ent['databases']) ? (int) $ent['databases'] : null,
            'limit_mailbox' => isset($ent['mailboxes']) ? (int) $ent['mailboxes'] : null,
            'limit_cron' => isset($ent['cron_concurrency']) ? (int) $ent['cron_concurrency'] : null,
            'limit_shell_user' => isset($ent['ssh']) ? (empty($ent['ssh']) ? 0 : 1) : null,
        ], fn ($value) => $value !== null);
        if ($wanted === []) {
            return [];
        }
        try {
            $current = $this->api->call('client_get', ['client_id' => $clientId]);
        } catch (ProviderException $e) {
            // the remote user may not have the client functions: the platform tried, the panel said no, and the site
            // update that follows will fail on the panel's own limits with the panel's own words — nothing is hidden
            return ['refused: '.mb_substr($e->getMessage(), 0, 120)];
        }
        if (! is_array($current) || $current === []) {
            return []; // the client is not there (a panel rebuilt underneath us): the site update below says so
        }
        $current = array_is_list($current) ? (array) ($current[0] ?? []) : $current;
        $changed = array_keys(array_filter($wanted, fn ($value, $key) => (string) ($current[$key] ?? '') !== (string) $value, ARRAY_FILTER_USE_BOTH));
        if ($changed === []) {
            return [];
        }
        $base = [];
        foreach ($current as $key => $value) {
            if (in_array($key, ['client_id', 'password', 'parent_client_id'], true) || str_starts_with((string) $key, 'sys_')) {
                continue; // the password is hashed in the record; sending it back would set the hash as the new password
            }
            $base[$key] = $value;
        }
        $this->api->call('client_update', ['client_id' => $clientId, 'reseller_id' => 0, 'params' => array_merge($base, $wanted)], true);

        return $changed;
    }

    private function updateSite(ResourceRef $site, array $params): void
    {
        $current = $this->getSite((int) $site->remoteId);
        if ($current === null) {
            throw new ProviderException('ispconfig', ProviderErrorCode::NOT_FOUND, "ISPConfig web domain {$site->remoteId} no longer exists (removed in the panel or by compensation); the site has to be created again.", 'missing');
        }
        $base = [];
        foreach ($current as $key => $value) {
            if (in_array($key, ['domain_id', 'ssl_request', 'ssl_cert', 'ssl_bundle', 'ssl_key', 'ssl_action'], true) || str_starts_with((string) $key, 'sys_')) {
                continue;
            }
            $base[$key] = $value;
        }
        $this->api->call('sites_web_domain_update', ['client_id' => (int) ($site->meta['client_id'] ?? 0), 'primary_id' => (int) $site->remoteId, 'params' => array_merge($base, $params)], true);
    }
}
