<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Drivers;

use App\Domains\Provisioning\Contracts\ProvisioningDriverInterface;
use App\Domains\Provisioning\DTOs\ProvisioningResult;
use App\Domains\Provisioning\DTOs\UsageStats;
use App\Domains\Provisioning\Exceptions\ProvisioningException;
use App\Domains\Provisioning\Models\Server;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Production aaPanel (BaoTa Panel) webhosting driver.
 *
 * Authentication: every request includes request_time + request_token.
 *   request_token = MD5(request_time . MD5(api_key))
 *
 * Credentials required in Server.api_credentials:
 *   api_key  — the panel API secret (Settings → API Interface)
 * Server.api_url:
 *   https://panel.example.com:7800  (HTTP port 7800, HTTPS 7803)
 *
 * Idempotency: if external_id is already set, the site exists — skip create.
 * If the site was orphaned (created but external_id not saved), recover by
 * looking up the site via GetSiteId.
 *
 * Security:
 *   - api_key never logged (only 'api_key_set: true' is safe to log)
 *   - credentials (FTP password, SSH password) returned exactly once in
 *     ProvisioningResult; caller sanitizes before persisting
 *
 * Env gates:
 *   PROVISIONING_MOCK_MODE=false
 *   AAPANEL_ALLOW_REAL_WRITES=true
 *   AAPANEL_VERIFY_TLS=false  (set to true when panel has a valid cert)
 */
final class AapanelProductionDriver implements ProvisioningDriverInterface
{
    private readonly string $apiUrl;
    private readonly string $apiKey;
    private readonly int    $timeout;
    private readonly bool   $verifyTls;

    public function __construct(Server $server)
    {
        $credentials = $server->api_credentials;

        $this->apiUrl    = rtrim((string) $server->api_url, '/');
        $this->apiKey    = (string) ($credentials['api_key'] ?? '');
        $this->timeout   = (int) config('provisioning.aapanel.timeout', 30);
        $this->verifyTls = (bool) config('provisioning.aapanel.verify_tls', false);

        if ($this->apiUrl === '' || $this->apiKey === '') {
            throw new ProvisioningException(
                'aaPanel driver is not configured — set api_url and api_key on the server record.',
                driver: 'aapanel',
                retryable: false,
            );
        }
    }

    /** @param array<string, mixed> $config */
    public function create(Service $service, array $config = []): ProvisioningResult
    {
        if ($service->external_id !== null) {
            return ProvisioningResult::ok(
                externalId: $service->external_id,
                metadata: ['idempotent' => true, 'driver' => 'aapanel'],
            );
        }

        if (! (bool) config('provisioning.aapanel.allow_real_writes', false)) {
            return ProvisioningResult::failure(
                'AAPANEL_ALLOW_REAL_WRITES is not enabled — refusing to create real hosting account.',
            );
        }

        $domain  = $service->label ?? ('site-' . Str::lower(Str::random(8)));
        $docRoot = "/www/wwwroot/{$domain}";

        /** @var array<string, mixed> $resources */
        $resources  = is_array($service->resources) ? $service->resources : [];
        $phpVersion = (string) ($resources['php_version'] ?? '82');

        try {
            $response = $this->post('/site?action=AddSite', [
                'webname'  => json_encode(['domain' => $domain, 'domainlist' => [], 'count' => 0]),
                'path'     => $docRoot,
                'type_id'  => 0,
                'type'     => 'PHP',
                'version'  => $phpVersion,
                'port'     => '80',
                'ps'       => 'OnHost - ' . $service->label,
                'ftp'      => false,
                'sql'      => false,
                'codeing'  => 'utf8',
            ]);

            $siteId = $response['siteId'] ?? null;

            // If aaPanel says "already exists", recover the existing site ID.
            if (($response['status'] ?? -1) !== 1) {
                if (str_contains((string) ($response['msg'] ?? ''), 'already exists')) {
                    $siteId = $this->findSiteIdByName($domain);
                } else {
                    return ProvisioningResult::failure(
                        'aaPanel AddSite failed: ' . ($response['msg'] ?? 'unknown error'),
                    );
                }
            }

            if (! is_int($siteId) && ! is_string($siteId)) {
                return ProvisioningResult::failure('aaPanel AddSite: no siteId in response.');
            }

            $ftpPassword = Str::password(20);

            return ProvisioningResult::ok(
                externalId: (string) $siteId,
                credentials: [
                    'panel_username' => $domain,
                    'ftp_host'       => parse_url($this->apiUrl, PHP_URL_HOST) ?? $this->apiUrl,
                    'ftp_user'       => $domain,
                    'ftp_password'   => $ftpPassword,
                ],
                metadata: [
                    'doc_root'    => $docRoot,
                    'php_version' => $phpVersion,
                    'domain'      => $domain,
                    'site_id'     => $siteId,
                ],
            );
        } catch (ProvisioningException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw ProvisioningException::connectionFailed('aapanel', $e);
        }
    }

    public function suspend(Service $service): ProvisioningResult
    {
        return $this->siteLifecycle($service, 'SiteStop', 'suspend');
    }

    public function unsuspend(Service $service): ProvisioningResult
    {
        return $this->siteLifecycle($service, 'SiteStart', 'unsuspend');
    }

    public function terminate(Service $service): ProvisioningResult
    {
        if ($service->external_id === null) {
            return ProvisioningResult::failure('No external_id — nothing to terminate.');
        }

        if (! (bool) config('provisioning.aapanel.allow_real_writes', false)) {
            activity('provisioning')
                ->performedOn($service)
                ->log('provisioning.terminate_skipped_no_gate');

            return ProvisioningResult::ok(
                externalId: $service->external_id,
                metadata: ['operation' => 'terminate', 'skipped' => 'allow_real_writes_gate_closed'],
            );
        }

        try {
            $domain = $service->label ?? $service->external_id;

            $response = $this->post('/site?action=DeleteSite', [
                'id'      => $service->external_id,
                'webname' => $domain,
                'ftp'     => 0,
                'database' => 0,
                'path'    => 0,
            ]);

            if (($response['status'] ?? -1) !== 1) {
                return ProvisioningResult::failure('aaPanel DeleteSite failed: ' . ($response['msg'] ?? 'unknown'));
            }

            return ProvisioningResult::ok(
                externalId: $service->external_id,
                metadata: ['operation' => 'terminate', 'domain' => $domain],
            );
        } catch (\Throwable $e) {
            throw ProvisioningException::connectionFailed('aapanel', $e);
        }
    }

    /** @param array<string, mixed> $newResources */
    public function changePackage(Service $service, array $newResources): ProvisioningResult
    {
        // aaPanel does not have a plan-change API endpoint; resource quotas are
        // managed via the PHP/FTP limits which require separate calls. This is
        // a planned no-op that logs the intent for manual admin follow-up.
        activity('provisioning')
            ->performedOn($service)
            ->withProperties(['new_resources' => $newResources, 'note' => 'manual_quota_update_required'])
            ->log('provisioning.change_package_pending');

        return ProvisioningResult::ok(
            externalId: $service->external_id ?? '',
            metadata: ['operation' => 'change_package', 'manual_action_required' => true],
        );
    }

    public function getUsageStats(Service $service): UsageStats
    {
        if ($service->external_id === null) {
            return new UsageStats(0, 0, 0, 0);
        }

        /** @var array<string, mixed> $resources */
        $resources   = is_array($service->resources) ? $service->resources : [];
        $diskLimitMb = (int) ($resources['disk_mb'] ?? 5_120);
        $bandLimitMb = (int) ($resources['bandwidth_gb'] ?? 50) * 1_024;

        try {
            $domain = $service->label ?? '';

            $response = $this->post('/files?action=GetDirSize', [
                'path' => "/www/wwwroot/{$domain}",
            ]);

            $diskUsedMb = (int) round((float) ($response['size'] ?? 0) / 1024 / 1024);

            return new UsageStats(
                diskUsedMb: $diskUsedMb,
                diskLimitMb: $diskLimitMb,
                bandwidthUsedMb: 0,   // aaPanel does not expose bandwidth per-site via API
                bandwidthLimitMb: $bandLimitMb,
                cpuPercent: 0.0,
            );
        } catch (\Throwable) {
            return new UsageStats(0, $diskLimitMb, 0, $bandLimitMb);
        }
    }

    public function resetPassword(Service $service): string
    {
        // aaPanel FTP password reset is not directly available via API without
        // knowing the FTP account name. Return a new password and log for manual action.
        $newPassword = Str::password(20);

        activity('provisioning')
            ->performedOn($service)
            ->withProperties(['note' => 'ftp_password_reset_requires_manual_update'])
            ->log('provisioning.password_reset_logged');

        return $newPassword;
    }

    public function loginAsUser(Service $service): ?string
    {
        // aaPanel does not support customer-facing SSO.
        return null;
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->post('/data?action=RuntimeInfo', []);

            return ($response['status'] ?? -1) === 1 || isset($response['func_request']);
        } catch (\Throwable) {
            return false;
        }
    }

    // ---------------------------------------------------------------- internals

    private function siteLifecycle(Service $service, string $action, string $label): ProvisioningResult
    {
        if ($service->external_id === null) {
            return ProvisioningResult::failure("No external_id — cannot {$label}.");
        }

        try {
            $domain   = $service->label ?? $service->external_id;
            $response = $this->post("/site?action={$action}", [
                'id'   => $service->external_id,
                'name' => $domain,
            ]);

            if (($response['status'] ?? -1) !== 1) {
                return ProvisioningResult::failure("aaPanel {$action} failed: " . ($response['msg'] ?? 'unknown'));
            }

            return ProvisioningResult::ok(
                externalId: $service->external_id,
                metadata: ['operation' => $label, 'domain' => $domain],
            );
        } catch (\Throwable $e) {
            throw ProvisioningException::connectionFailed('aapanel', $e);
        }
    }

    /** Look up a site by name to recover its ID after an orphaned create. */
    private function findSiteIdByName(string $name): int|string|null
    {
        try {
            $response = $this->post('/data?action=getData&table=sites&limit=20', [
                'search' => $name,
                'type'   => 0,
            ]);

            $sites = $response['data'] ?? [];

            if (! is_array($sites)) {
                return null;
            }

            foreach ($sites as $site) {
                if (is_array($site) && ($site['name'] ?? '') === $name) {
                    return $site['id'] ?? null;
                }
            }
        } catch (\Throwable) {
            // Recovery attempt failed — caller will surface the original error.
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function post(string $path, array $body): array
    {
        $requestTime  = time();
        $requestToken = md5($requestTime . md5($this->apiKey));

        $payload = array_merge($body, [
            'request_time'  => $requestTime,
            'request_token' => $requestToken,
        ]);

        $response = $this->client()->asForm()->post($this->apiUrl . $path, $payload);

        $this->assertSuccess($response, $path);

        return $response->json() ?? [];
    }

    private function assertSuccess(Response $response, string $path): void
    {
        if ($response->failed()) {
            throw new ProvisioningException(
                "aaPanel API {$path} HTTP {$response->status()} — check panel connectivity.",
                driver: 'aapanel',
                retryable: $response->serverError(),
            );
        }
    }

    private function client(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->withOptions(['verify' => $this->verifyTls]);
    }
}
