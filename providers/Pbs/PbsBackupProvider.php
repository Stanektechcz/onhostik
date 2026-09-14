<?php

declare(strict_types=1);

namespace Onhost\Providers\Pbs;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Platform\ProviderHttp\ProviderRequest;
use Onhost\Platform\ProviderHttp\ProviderResponse;
use Onhost\Providers\Contracts\AsyncHandle;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\BackupProvider;
use Onhost\Providers\Contracts\ProviderHealth;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\TlsOptions;

/**
 * Proxmox Backup Server 3/4 API. The control-plane token is `DatastoreReader` +
 * `DatastoreVerifier` only: listing, verification and status. Pruning/deletion is
 * performed server-side by PBS jobs under a different identity (§12.1, §37.3), so a
 * compromised control plane or PVE node cannot erase history.
 */
final class PbsBackupProvider implements BackupProvider
{
    public function __construct(
        private readonly ProviderInstance $instance,
        private readonly array $credentials,
        private readonly ProviderHttpClient $http,
        private readonly CacheRepository $cache,
    ) {
        $this->http->configureBucket($instance->key, (int) ($instance->rate_limits['per_minute'] ?? 120), 60, 0.1);
    }

    public static function providerKey(): string
    {
        return 'pbs';
    }

    public static function adapterVersion(): string
    {
        return '1.0.0';
    }

    public static function supportedVendorVersions(): array
    {
        return ['3.2', '3.3', '3.4', '4.0', '4.1', '4.2'];
    }

    public function capabilities(): array
    {
        return ['backup.list' => true, 'backup.verify' => true, 'backup.protect' => true, 'backup.delete' => false, 'datastore.status' => true, 'namespaces' => true, 'sync.offsite' => 'server_side'];
    }

    public function health(): ProviderHealth
    {
        $started = hrtime(true);
        try {
            $version = $this->get('/version', [], 'version');
            $ms = (int) ((hrtime(true) - $started) / 1_000_000);
            $this->cache->put("onhost:pbs:version:{$this->instance->id}", (string) ($version['version'] ?? ''), 3600);

            return new ProviderHealth(true, (string) ($version['version'] ?? null), $ms, ['release' => $version['release'] ?? null]);
        } catch (ProviderException $e) {
            return ProviderHealth::down($e->getMessage(), (int) ((hrtime(true) - $started) / 1_000_000));
        }
    }

    public function vendorVersion(): ?string
    {
        $v = $this->cache->get("onhost:pbs:version:{$this->instance->id}");

        return is_string($v) && $v !== '' ? $v : null;
    }

    public function listSnapshots(string $datastore, ?string $namespace = null, ?string $backupId = null): array
    {
        $params = array_filter(['ns' => $namespace, 'backup-id' => $backupId], fn ($v) => $v !== null && $v !== '');
        $out = [];
        foreach ((array) $this->get("/admin/datastore/{$datastore}/snapshots", $params, 'snapshots') as $s) {
            $out[] = [
                'datastore' => $datastore, 'namespace' => $s['ns'] ?? $namespace, 'backup_type' => (string) $s['backup-type'], 'backup_id' => (string) $s['backup-id'],
                'backup_time' => (int) $s['backup-time'], 'size' => isset($s['size']) ? (int) $s['size'] : null,
                'verification' => $s['verification'] ?? null, 'protected' => (bool) ($s['protected'] ?? false),
            ];
        }

        return $out;
    }

    public function verify(string $datastore, ?string $namespace, string $backupType, string $backupId, int $backupTime): ProviderResult
    {
        $upid = $this->post("/admin/datastore/{$datastore}/verify", array_filter(['ns' => $namespace, 'backup-type' => $backupType, 'backup-id' => $backupId, 'backup-time' => $backupTime, 'ignore-verified' => false], fn ($v) => $v !== null), 'verify');

        return ProviderResult::accepted(new AsyncHandle('pbs_task', (string) $upid, null, ['datastore' => $datastore, 'backup_id' => $backupId, 'backup_time' => $backupTime], 15, 3 * 3600));
    }

    public function datastoreStatus(string $datastore): array
    {
        $status = $this->get("/admin/datastore/{$datastore}/status", [], 'datastore.status');

        return ['total' => (int) ($status['total'] ?? 0), 'used' => (int) ($status['used'] ?? 0), 'avail' => (int) ($status['avail'] ?? 0), 'gc_status' => $status['gc-status'] ?? null];
    }

    public function awaitStatus(AsyncHandle $handle): AsyncStatus
    {
        try {
            $status = $this->get('/nodes/localhost/tasks/'.rawurlencode($handle->handle).'/status', [], 'task.status');
        } catch (ProviderException $e) {
            return $e->errorCode === ProviderErrorCode::NOT_FOUND ? AsyncStatus::unknown('task not found') : throw $e;
        }
        if (($status['status'] ?? '') === 'running') {
            return AsyncStatus::running();
        }
        $exit = (string) ($status['exitstatus'] ?? '');

        return $exit === 'OK' ? AsyncStatus::succeeded(['upid' => $handle->handle, 'meta' => $handle->meta]) : AsyncStatus::failed("PBS task finished with: {$exit}");
    }

    private function get(string $path, array $params, string $action): mixed
    {
        return $this->call('GET', $path, $params, $action);
    }

    private function post(string $path, array $params, string $action): mixed
    {
        return $this->call('POST', $path, $params, $action);
    }

    private function call(string $method, string $path, array $params, string $action): mixed
    {
        $tokenId = (string) ($this->credentials['token_id'] ?? '');
        $secret = (string) ($this->credentials['token_secret'] ?? '');
        if ($tokenId === '' || $secret === '') {
            throw new ProviderException('pbs', ProviderErrorCode::AUTH, 'PBS API token is not configured');
        }
        $options = TlsOptions::verify($this->instance, 'pbs');
        $response = $this->http->send(new ProviderRequest(
            provider: 'pbs', instanceKey: $this->instance->key, method: $method, url: rtrim((string) $this->instance->base_url, '/').'/api2/json'.$path, action: $action,
            headers: ['Authorization' => "PBSAPIToken={$tokenId}:{$secret}", 'Accept' => 'application/json'],
            body: $method === 'GET' ? null : $params, bodyType: 'json', query: $method === 'GET' ? $params : [], timeoutSeconds: 15, critical: true, idempotent: $method === 'GET', options: $options,
        ));

        return $this->unwrap($response, $action);
    }

    private function unwrap(ProviderResponse $response, string $action): mixed
    {
        $json = $response->json();
        if (in_array($response->status, [401, 403], true)) {
            throw new ProviderException('pbs', ProviderErrorCode::AUTH, "PBS rejected the token for {$action}", (string) $response->status);
        }
        if ($response->status === 404) {
            throw new ProviderException('pbs', ProviderErrorCode::NOT_FOUND, "PBS object not found for {$action}", '404');
        }
        if ($response->status >= 500) {
            throw new ProviderException('pbs', ProviderErrorCode::TRANSIENT, "PBS {$action} failed: ".(is_array($json) ? json_encode($json['message'] ?? $json['errors'] ?? null) : 'server error'), (string) $response->status);
        }
        if ($response->status >= 400) {
            throw new ProviderException('pbs', ProviderErrorCode::VALIDATION, "PBS {$action} rejected: ".(is_array($json) ? json_encode($json['errors'] ?? $json['message'] ?? null) : ''), (string) $response->status);
        }
        if (! is_array($json) || ! array_key_exists('data', $json)) {
            throw new ProviderException('pbs', ProviderErrorCode::PROVIDER_BUG, "PBS {$action} returned no data envelope");
        }

        return $json['data'];
    }
}
