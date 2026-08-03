<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Clients;

use App\Domains\Shared\Support\SecretRedactor;
use App\Domains\Integrations\Clients\Concerns\GuardsRealCalls;
use App\Domains\Integrations\Models\IntegrationSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Real-provider-READY aaPanel client. NOT live in this phase.
 *
 * Every write method routes through dryRunOr(): unless all five refusal
 * gates open (active + !mock + !dry_run + AAPANEL_ALLOW_REAL_WRITES=true
 * + credentials present), the method returns a simulated dry-run payload
 * and logs the intent — no HTTP is ever sent.
 *
 * aaPanel auth model (for the future real path): every request carries
 * request_time + request_token = md5(request_time . md5(api_key)).
 */
final class AapanelClient
{
    use GuardsRealCalls;

    private const GATE = 'aapanel';

    private const REQUIRED = ['base_url', 'api_key'];

    public function __construct(
        private readonly IntegrationSetting $setting,
    ) {}

    public static function fromSettings(): self
    {
        return new self(
            IntegrationSetting::query()->firstOrCreate(
                ['provider' => 'aapanel'],
                ['label' => 'aaPanel (webhosting)', 'mock_mode' => true, 'dry_run' => true],
            ),
        );
    }

    /** @return array<string, mixed> */
    public function connectionTest(): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['ok' => true, 'dry_run' => true, 'message' => 'Mock/dry-run connection OK (no HTTP sent).'];
        }

        // Connection test is READ-ONLY — bypasses the write gate intentionally.
        // Only requires credentials present, active, not mock/dry-run.
        $this->assertReadyForReadCall(self::REQUIRED);

        $response = $this->realRequest('/system?action=GetSystemTotal', []);

        return ['ok' => true, 'dry_run' => false, 'system' => $response];
    }

    /**
     * Read-only site overview for the admin Service 360° page.
     * Like connectionTest, this is a pure GET — no write gate required.
     *
     * @return array<string, mixed>
     */
    public function getSiteOverview(string $siteName): array
    {
        if ($this->isDryRun($this->setting)) {
            return [
                'ok'      => true,
                'dry_run' => true,
                'site'    => ['name' => $siteName, 'status' => 'mock-run', 'path' => "/www/wwwroot/{$siteName}"],
            ];
        }

        $this->assertReadyForReadCall(self::REQUIRED, 'getSiteOverview');

        $response = $this->realRequest('/data?action=getBySearch&table=sites', ['search' => $siteName]);

        return ['ok' => true, 'dry_run' => false, 'site' => $response];
    }

    /** @param list<string> $required */
    private function assertReadyForReadCall(array $required, string $operation = 'connectionTest'): void
    {
        if (!$this->setting->is_active) {
            throw new \RuntimeException("[aapanel] {$operation}: provider is inactive.");
        }
        $credentials = $this->setting->credentials;
        foreach ($required as $key) {
            if (($credentials[$key] ?? '') === '') {
                throw new \RuntimeException("[aapanel] {$operation}: missing credential [{$key}].");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function createSite(string $domain, array $config = []): array
    {
        return $this->dryRunOr('createSite', ['domain' => $domain] + $config, '/site?action=AddSite');
    }

    /** @return array<string, mixed> */
    public function createDatabase(string $name, string $username): array
    {
        return $this->dryRunOr('createDatabase', ['name' => $name, 'username' => $username], '/database?action=AddDatabase');
    }

    /** @return array<string, mixed> */
    public function createFtpAccount(string $username, string $path): array
    {
        return $this->dryRunOr('createFtpAccount', ['username' => $username, 'path' => $path], '/ftp?action=AddUser');
    }

    /** @return array<string, mixed> */
    public function setPhpVersion(string $siteId, string $version): array
    {
        return $this->dryRunOr('setPhpVersion', ['site' => $siteId, 'version' => $version], '/site?action=SetPHPVersion');
    }

    /** @return array<string, mixed> */
    public function configureSsl(string $siteId): array
    {
        return $this->dryRunOr('configureSsl', ['site' => $siteId], '/site?action=ApplyCert');
    }

    /** @return array<string, mixed> */
    public function suspendSite(string $siteId): array
    {
        return $this->dryRunOr('suspendSite', ['site' => $siteId], '/site?action=SiteStop');
    }

    /** @return array<string, mixed> */
    public function reactivateSite(string $siteId): array
    {
        return $this->dryRunOr('reactivateSite', ['site' => $siteId], '/site?action=SiteStart');
    }

    /** @return array<string, mixed> */
    public function deleteSite(string $siteId): array
    {
        return $this->dryRunOr('deleteSite', ['site' => $siteId], '/site?action=DeleteSite');
    }

    /** @return array<string, mixed> */
    public function getUsage(string $siteId): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['dry_run' => true, 'disk_used_mb' => 0, 'bandwidth_used_mb' => 0];
        }

        $this->assertRealCallAllowed($this->setting, self::GATE, 'getUsage', self::REQUIRED);

        return $this->realRequest('/site?action=GetSiteInfo', ['site' => $siteId]);
    }

    /** @return array<string, mixed> */
    public function getServerHealth(): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['dry_run' => true, 'cpu' => 5, 'memory' => 20, 'disk' => 10, 'status' => 'mock-healthy'];
        }

        $this->assertRealCallAllowed($this->setting, self::GATE, 'getServerHealth', self::REQUIRED);

        return $this->realRequest('/system?action=GetNetWork', []);
    }

    /**
     * List all sites (paginated).
     *
     * @return array<string, mixed>
     */
    public function listSites(int $page = 1, int $perPage = 20, string $search = ''): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['dry_run' => true, 'data' => [
                ['id' => 1, 'name' => 'example.com', 'status' => 'run'],
                ['id' => 2, 'name' => 'demo.cz',     'status' => 'stop'],
            ]];
        }

        $this->assertRealCallAllowed($this->setting, self::GATE, 'listSites', self::REQUIRED);

        return $this->realRequest('/data?action=getData&table=sites', [
            'limit'  => $perPage,
            'p'      => $page,
            'search' => $search,
            'type'   => 0,
        ]);
    }

    /**
     * Get detailed info for a single site.
     *
     * @return array<string, mixed>
     */
    public function getSiteInfo(string $siteName): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['dry_run' => true, 'name' => $siteName, 'status' => 'run', 'path' => "/www/wwwroot/{$siteName}"];
        }

        $this->assertRealCallAllowed($this->setting, self::GATE, 'getSiteInfo', self::REQUIRED);

        return $this->realRequest('/data?action=getBySearch&table=sites', ['search' => $siteName]);
    }

    /**
     * Set disk quota for a site (in MB, 0 = unlimited).
     *
     * @return array<string, mixed>
     */
    public function setDiskQuota(string $siteId, int $quotaMb): array
    {
        return $this->dryRunOr('setDiskQuota', ['site' => $siteId, 'quota' => $quotaMb], '/site?action=SetQuota');
    }

    /** @return array<string, mixed> */
    public function deleteFtpAccount(string $username): array
    {
        return $this->dryRunOr('deleteFtpAccount', ['username' => $username], '/ftp?action=DeleteUser');
    }

    /** @return array<string, mixed> */
    public function deleteDatabase(string $name): array
    {
        return $this->dryRunOr('deleteDatabase', ['name' => $name], '/database?action=DeleteDatabase');
    }

    /**
     * Get site error/access logs (last N lines).
     *
     * @return array<string, mixed>
     */
    public function getSiteLogs(string $siteName, string $type = 'error', int $lines = 100): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['dry_run' => true, 'logs' => "[mock] No real logs in dry-run mode."];
        }

        $this->assertRealCallAllowed($this->setting, self::GATE, 'getSiteLogs', self::REQUIRED);

        $logPath = $type === 'error'
            ? "/www/wwwlogs/{$siteName}.error.log"
            : "/www/wwwlogs/{$siteName}.log";

        return $this->realRequest('/files?action=GetFileBody', [
            'path'  => $logPath,
            'limit' => $lines,
        ]);
    }

    /**
     * Bind additional domains to a site.
     *
     * @param  list<string>  $domains
     * @return array<string, mixed>
     */
    public function setDomainBindings(string $siteId, string $siteName, array $domains): array
    {
        return $this->dryRunOr('setDomainBindings', ['site' => $siteId, 'domains' => $domains], '/site?action=AddDomain');
    }

    /*
    |--------------------------------------------------------------------------
    | Site configuration — reads
    |--------------------------------------------------------------------------
    | These back the "complete configuration" view of a service. They are pure
    | GETs and deliberately use assertReadyForReadCall (NOT the write gate), so
    | showing a customer their own configuration never needs write access.
    */

    /**
     * Databases belonging to a site.
     *
     * @return array<string, mixed>
     */
    public function listDatabases(string $siteName): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['dry_run' => true, 'data' => [
                ['name' => str_replace('.', '_', $siteName) . '_db', 'username' => str_replace('.', '_', $siteName), 'ps' => 'mock'],
            ]];
        }

        $this->assertReadyForReadCall(self::REQUIRED, 'listDatabases');

        return $this->realRequest('/data?action=getData&table=databases', ['search' => $siteName, 'limit' => 100, 'p' => 1]);
    }

    /**
     * FTP accounts belonging to a site.
     *
     * @return array<string, mixed>
     */
    public function listFtpAccounts(string $siteName): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['dry_run' => true, 'data' => [
                ['name' => str_replace('.', '_', $siteName), 'path' => "/www/wwwroot/{$siteName}", 'status' => '1'],
            ]];
        }

        $this->assertReadyForReadCall(self::REQUIRED, 'listFtpAccounts');

        return $this->realRequest('/data?action=getData&table=ftps', ['search' => $siteName, 'limit' => 100, 'p' => 1]);
    }

    /**
     * Scheduled (cron) tasks configured on the panel.
     *
     * @return array<string, mixed>
     */
    public function listCronJobs(): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['dry_run' => true, 'data' => []];
        }

        $this->assertReadyForReadCall(self::REQUIRED, 'listCronJobs');

        return $this->realRequest('/crontab?action=GetCrontab', ['limit' => 100, 'p' => 1]);
    }

    /**
     * SSL certificate state for a site (issuer, validity, auto-renew).
     *
     * @return array<string, mixed>
     */
    public function getSslInfo(string $siteName): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['dry_run' => true, 'status' => false, 'msg' => 'mock — no certificate'];
        }

        $this->assertReadyForReadCall(self::REQUIRED, 'getSslInfo');

        return $this->realRequest('/site?action=GetSSL', ['siteName' => $siteName]);
    }

    /**
     * PHP versions actually installed on the panel.
     *
     * Returns aaPanel's raw list; the caller filters out non-PHP builds
     * (version '00' is "Static"). Used instead of a hardcoded list so the
     * UI can never offer a version the server does not have (audit E78).
     *
     * @return array<string, mixed>
     */
    public function getPhpVersions(): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['dry_run' => true, 'data' => [
                ['version' => '74', 'name' => 'PHP-74'],
                ['version' => '82', 'name' => 'PHP-82'],
                ['version' => '83', 'name' => 'PHP-83'],
            ]];
        }

        $this->assertReadyForReadCall(self::REQUIRED, 'getPhpVersions');

        return $this->realRequest('/site?action=GetPHPVersion', []);
    }

    /**
     * Mailboxes on a domain (audit E80 / F88).
     *
     * aaPanel exposes mail through its mail_sys plugin; when the plugin is not
     * installed the panel answers with an error, which the caller surfaces as
     * a section error rather than an empty list.
     *
     * @return array<string, mixed>
     */
    public function listMailboxes(string $domain): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['dry_run' => true, 'data' => [
                ['username' => "info@{$domain}", 'quota' => 1024, 'is_active' => true],
            ]];
        }

        $this->assertReadyForReadCall(self::REQUIRED, 'listMailboxes');

        return $this->realRequest('/plugin?action=a&name=mail_sys&s=get_mailboxes', ['domain' => $domain]);
    }

    /*
    |--------------------------------------------------------------------------
    | Site configuration — writes (all behind the AAPANEL_ALLOW_REAL_WRITES gate)
    |--------------------------------------------------------------------------
    */

    /**
     * Create a mailbox.
     *
     * The password is passed straight through to the panel and is NEVER
     * returned, logged or stored — callers must not persist it either.
     *
     * @return array<string, mixed>
     */
    public function createMailbox(string $domain, string $username, string $password, int $quotaMb = 1024): array
    {
        return $this->dryRunOr('createMailbox', [
            'domain'   => $domain,
            'username' => $username,
            'password' => $password,
            'quota'    => $quotaMb,
        ], '/plugin?action=a&name=mail_sys&s=add_mailbox');
    }

    /** @return array<string, mixed> */
    public function deleteMailbox(string $domain, string $username): array
    {
        return $this->dryRunOr('deleteMailbox', [
            'domain'   => $domain,
            'username' => $username,
        ], '/plugin?action=a&name=mail_sys&s=delete_mailbox');
    }

    /** @return array<string, mixed> */
    public function setMailboxQuota(string $domain, string $username, int $quotaMb): array
    {
        return $this->dryRunOr('setMailboxQuota', [
            'domain'   => $domain,
            'username' => $username,
            'quota'    => $quotaMb,
        ], '/plugin?action=a&name=mail_sys&s=set_mailbox_quota');
    }

    /** @return array<string, mixed> */
    public function createCronJob(string $name, string $command, string $type = 'day', int $hour = 3, int $minute = 0): array
    {
        return $this->dryRunOr('createCronJob', [
            'name'    => $name,
            'type'    => $type,
            'where1'  => '',
            'hour'    => $hour,
            'minute'  => $minute,
            'sType'   => 'toShell',
            'sBody'   => $command,
        ], '/crontab?action=AddCrontab');
    }

    /** @return array<string, mixed> */
    public function deleteCronJob(string $cronId): array
    {
        return $this->dryRunOr('deleteCronJob', ['id' => $cronId], '/crontab?action=DelCrontab');
    }

    // ---------------------------------------------------------------- internals

    // ── Backups ─────────────────────────────────────────────────────────────

    /**
     * Create a site backup (files).
     *
     * @return array<string, mixed>
     */
    public function createSiteBackup(int $siteId): array
    {
        return $this->dryRunOr('createSiteBackup', ['id' => $siteId, 'type' => 0], '/site?action=ToBackup');
    }

    /** Create a database backup. */
    /** @return array<string, mixed> */
    public function createDatabaseBackup(int $databaseId): array
    {
        return $this->dryRunOr('createDatabaseBackup', ['id' => $databaseId], '/database?action=ToBackup');
    }

    /** @return array<string, mixed> */
    public function listSiteBackups(int $siteId): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['dry_run' => true, 'data' => []];
        }

        $this->assertReadyForReadCall(self::REQUIRED, 'listSiteBackups');

        return $this->realRequest('/site?action=GetBackupList', ['id' => $siteId, 'limit' => 50, 'p' => 1]);
    }

    /** @return array<string, mixed> */
    public function deleteSiteBackup(int $backupId): array
    {
        return $this->dryRunOr('deleteSiteBackup', ['id' => $backupId], '/site?action=DelBackup');
    }

    /** Restore a database from a previously created backup file. */
    /** @return array<string, mixed> */
    public function restoreDatabaseBackup(int $databaseId, string $file): array
    {
        return $this->dryRunOr('restoreDatabaseBackup', [
            'id'   => $databaseId,
            'file' => $file,
        ], '/database?action=InputSql');
    }

    // ── Mail: forwarding and aliases ────────────────────────────────────────

    /**
     * Forward a mailbox to another address (audit: aaPanel mail parity).
     * `keep` decides whether a copy stays in the original mailbox.
     */
    /** @return array<string, mixed> */
    public function createMailForward(string $mailbox, string $forwardTo, bool $keep = true): array
    {
        return $this->dryRunOr('createMailForward', [
            'mail_from' => $mailbox,
            'mail_to'   => $forwardTo,
            'keep'      => $keep ? 1 : 0,
        ], '/plugin?action=a&name=mail_sys&s=add_forward');
    }

    /** @return array<string, mixed> */
    public function deleteMailForward(string $mailbox, string $forwardTo): array
    {
        return $this->dryRunOr('deleteMailForward', [
            'mail_from' => $mailbox,
            'mail_to'   => $forwardTo,
        ], '/plugin?action=a&name=mail_sys&s=delete_forward');
    }

    /** @return array<string, mixed> */
    public function listMailForwards(string $domain): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['dry_run' => true, 'data' => []];
        }

        $this->assertReadyForReadCall(self::REQUIRED, 'listMailForwards');

        return $this->realRequest('/plugin?action=a&name=mail_sys&s=get_forward_list', ['domain' => $domain]);
    }

    /** Auto-reply (vacation responder) for a mailbox. */
    /** @return array<string, mixed> */
    public function setMailAutoReply(string $mailbox, string $subject, string $body, bool $enabled = true): array
    {
        return $this->dryRunOr('setMailAutoReply', [
            'mail_addr' => $mailbox,
            'subject'   => $subject,
            'body'      => $body,
            'is_open'   => $enabled ? 1 : 0,
        ], '/plugin?action=a&name=mail_sys&s=set_autoreply');
    }

    /** Change an existing mailbox password. */
    /** @return array<string, mixed> */
    public function setMailboxPassword(string $mailbox, string $password): array
    {
        return $this->dryRunOr('setMailboxPassword', [
            'mail_addr' => $mailbox,
            'password'  => $password,
        ], '/plugin?action=a&name=mail_sys&s=modify_mailbox_password');
    }

    // ── Redirects and rewrites ──────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function createRedirect(string $siteName, string $from, string $to, bool $keepPath = true, int $type = 301): array
    {
        return $this->dryRunOr('createRedirect', [
            'sitename'    => $siteName,
            'redirectname' => $from,
            'tourl'       => $to,
            'redirecttype' => $type,
            'redirectpath' => $keepPath ? 1 : 0,
            'type'        => 1,
        ], '/site?action=CreateRedirect');
    }

    /** @return array<string, mixed> */
    public function deleteRedirect(string $siteName, string $redirectName): array
    {
        return $this->dryRunOr('deleteRedirect', [
            'sitename'     => $siteName,
            'redirectname' => $redirectName,
        ], '/site?action=DeleteRedirect');
    }

    /** @return array<string, mixed> */
    public function listRedirects(string $siteName): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['dry_run' => true, 'data' => []];
        }

        $this->assertReadyForReadCall(self::REQUIRED, 'listRedirects');

        return $this->realRequest('/site?action=GetRedirectList', ['sitename' => $siteName]);
    }

    /** Read the site's rewrite (URL rules) configuration. */
    /** @return array<string, mixed> */
    public function getRewriteRules(string $siteName): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['dry_run' => true, 'data' => ''];
        }

        $this->assertReadyForReadCall(self::REQUIRED, 'getRewriteRules');

        return $this->realRequest('/site?action=GetRewriteList', ['sitename' => $siteName]);
    }

    /** @return array<string, mixed> */
    public function setRewriteRules(string $path, string $content): array
    {
        return $this->dryRunOr('setRewriteRules', [
            'path'     => $path,
            'data'     => $content,
            'encoding' => 'utf-8',
        ], '/files?action=SaveFileBody');
    }

    // ── Traffic and resource statistics ─────────────────────────────────────

    /** @return array<string, mixed> */
    public function getTrafficStats(string $siteName, int $days = 30): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['dry_run' => true, 'data' => []];
        }

        $this->assertReadyForReadCall(self::REQUIRED, 'getTrafficStats');

        return $this->realRequest('/site?action=GetSiteLogsTraffic', [
            'siteName' => $siteName,
            'days'     => $days,
        ]);
    }

    /**
     * Network/CPU/memory time series for the server dashboard.
     *
     * @return array<string, mixed>
     */
    public function getServerLoadHistory(int $hours = 24): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['dry_run' => true, 'data' => []];
        }

        $this->assertReadyForReadCall(self::REQUIRED, 'getServerLoadHistory');

        return $this->realRequest('/ajax?action=GetCpuIo', ['hours' => $hours]);
    }

    // ── Credentials ─────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function setFtpPassword(string $username, string $password): array
    {
        return $this->dryRunOr('setFtpPassword', [
            'username' => $username,
            'password' => $password,
        ], '/ftp?action=SetUserPassword');
    }

    /** @return array<string, mixed> */
    public function setDatabasePassword(string $databaseName, string $password): array
    {
        return $this->dryRunOr('setDatabasePassword', [
            'name'     => $databaseName,
            'password' => $password,
        ], '/database?action=ResDatabasePassword');
    }

    /** Enable/disable an FTP account without deleting it. */
    /** @return array<string, mixed> */
    public function toggleFtpAccount(int $ftpId, bool $enabled): array
    {
        return $this->dryRunOr('toggleFtpAccount', [
            'id'     => $ftpId,
            'status' => $enabled ? 1 : 0,
        ], '/ftp?action=SetStatus');
    }

    // ── SSL automation ──────────────────────────────────────────────────────

    /** Request/renew a Let's Encrypt certificate for the site's domains. */
    /**
     * @param  list<string>  $domains
     * @return array<string, mixed>
     */
    public function issueLetsEncrypt(string $siteName, array $domains, string $email): array
    {
        return $this->dryRunOr('issueLetsEncrypt', [
            'domains'  => json_encode($domains),
            'siteName' => $siteName,
            'email'    => $email,
            'auth_type' => 'http',
        ], '/acme?action=apply_cert_api');
    }

    /** Force HTTPS (HTTP → HTTPS redirect) on a site. */
    /** @return array<string, mixed> */
    public function setForceHttps(string $siteName, bool $enabled): array
    {
        return $this->dryRunOr('setForceHttps', [
            'siteName' => $siteName,
        ], $enabled ? '/site?action=HttpToHttps' : '/site?action=CloseToHttps');
    }

    // ── Firewall ────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function listFirewallRules(): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['dry_run' => true, 'data' => []];
        }

        $this->assertReadyForReadCall(self::REQUIRED, 'listFirewallRules');

        return $this->realRequest('/safe?action=GetFireWallList', ['limit' => 100, 'p' => 1]);
    }

    /** @return array<string, mixed> */
    public function addFirewallRule(string $port, string $protocol = 'tcp', string $note = ''): array
    {
        return $this->dryRunOr('addFirewallRule', [
            'port'  => $port,
            'type'  => $protocol,
            'ps'    => $note,
        ], '/safe?action=AddDropAddress');
    }

    /** @return array<string, mixed> */
    public function deleteFirewallRule(int $ruleId, string $port): array
    {
        return $this->dryRunOr('deleteFirewallRule', [
            'id'   => $ruleId,
            'port' => $port,
        ], '/safe?action=DelDropAddress');
    }

    // ── Site lifecycle extras ───────────────────────────────────────────────

    /** Change the document root of a site. */
    /** @return array<string, mixed> */
    public function setSiteDirectory(int $siteId, string $path): array
    {
        return $this->dryRunOr('setSiteDirectory', [
            'id'   => $siteId,
            'path' => $path,
        ], '/site?action=SetPath');
    }

    /** Toggle the site's access log on/off. */
    /** @return array<string, mixed> */
    public function setSiteLogging(string $siteName, bool $enabled): array
    {
        return $this->dryRunOr('setSiteLogging', [
            'siteName' => $siteName,
            'status'   => $enabled ? 'open' : 'close',
        ], '/site?action=logsOpen');
    }

    // ── Shell execution (git deployment) ────────────────────────────────────

    /**
     * Run a shell command on the panel host.
     *
     * DANGEROUS BY NATURE — this is the one call that can do anything on the
     * server, so it is deliberately NOT a general-purpose helper: callers must
     * build the command from validated, escaped parts (see GitDeployService).
     * Never interpolate user input into $command without escapeshellarg().
     *
     * Passes through the same five-gate refusal as every other write.
     *
     * @return array<string, mixed>
     */
    public function runShellCommand(string $command, int $timeoutSeconds = 120): array
    {
        return $this->dryRunOr('runShellCommand', [
            'shell'   => $command,
            'timeout' => $timeoutSeconds,
        ], '/files?action=ExecShell');
    }

    /**
     * Generate an SSH deploy keypair on the panel host and return the public
     * half, so a private repository can be cloned without a password.
     *
     * @return array<string, mixed>
     */
    public function generateDeployKey(string $keyName): array
    {
        return $this->dryRunOr('generateDeployKey', [
            'name' => $keyName,
            'type' => 'ed25519',
        ], '/ssh?action=CreateKey');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function dryRunOr(string $operation, array $payload, string $endpoint): array
    {
        if ($this->isDryRun($this->setting)) {
            // The dry-run echo is written to the log AND handed back to the
            // caller (who may persist it on a ProvisioningTask), so secrets in
            // the payload — e.g. a new mailbox password — must be masked here.
            $safe = self::redactSecrets($payload);

            Log::info("aapanel.dry_run.{$operation}", ['payload' => $safe]);

            return [
                'ok'       => true,
                'dry_run'  => true,
                'operation' => $operation,
                'would_call' => $endpoint,
                'payload'  => $safe,
            ];
        }

        $this->assertRealCallAllowed($this->setting, self::GATE, $operation, self::REQUIRED);

        return $this->realRequest($endpoint, $payload);
    }

    /**
     * Mask credential-bearing keys before a payload is logged or returned.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function redactSecrets(array $payload): array
    {
        /** @var array<string, mixed> $redacted */
        $redacted = SecretRedactor::redact($payload);

        return $redacted;
    }

    /**
     * Only reachable once every refusal gate is open.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function realRequest(string $endpoint, array $payload): array
    {
        $credentials = $this->setting->credentials;
        $time        = time();

        $response = Http::timeout(Config::integer('provisioning.aapanel.timeout', 30))
            ->asForm()
            ->post(rtrim($credentials['base_url'] ?? '', '/') . $endpoint, $payload + [
                'request_time'  => $time,
                'request_token' => md5($time . md5($credentials['api_key'] ?? '')),
            ]);

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : ['raw' => $response->body()];
    }
}
