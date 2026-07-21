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
