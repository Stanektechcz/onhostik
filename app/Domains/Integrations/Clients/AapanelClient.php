<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Clients;

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

        $this->assertRealCallAllowed($this->setting, self::GATE, 'connectionTest', self::REQUIRED);

        $response = $this->realRequest('/system?action=GetSystemTotal', []);

        return ['ok' => true, 'dry_run' => false, 'system' => $response];
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

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function dryRunOr(string $operation, array $payload, string $endpoint): array
    {
        if ($this->isDryRun($this->setting)) {
            Log::info("aapanel.dry_run.{$operation}", ['payload' => $payload]);

            return [
                'ok'       => true,
                'dry_run'  => true,
                'operation' => $operation,
                'would_call' => $endpoint,
                'payload'  => $payload,
            ];
        }

        $this->assertRealCallAllowed($this->setting, self::GATE, $operation, self::REQUIRED);

        return $this->realRequest($endpoint, $payload);
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
