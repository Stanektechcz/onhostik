<?php

declare(strict_types=1);

namespace Onhost\Providers\Proxmox;

use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Platform\ProviderHttp\ProviderRequest;
use Onhost\Platform\ProviderHttp\ProviderResponse;
use Onhost\Providers\Contracts\TlsOptions;

/**
 * Proxmox VE 8/9 REST transport: `Authorization: PVEAPIToken=user@realm!tokenid=secret`,
 * JSON responses wrapped in `{data: …}`, form-encoded mutations. Self-signed TLS is
 * pinned to the cluster CA from options.tls_ca; `verify=false` is refused in production.
 */
final class ProxmoxConnector
{
    public function __construct(
        private readonly ProviderInstance $instance,
        private readonly array $credentials,
        private readonly ProviderHttpClient $http,
    ) {
        $limit = (int) ($instance->rate_limits['per_minute'] ?? 120);
        $this->http->configureBucket($instance->key, $limit, 60, 0.1);
    }

    public function instance(): ProviderInstance
    {
        return $this->instance;
    }

    /** @param array<string,mixed> $params */
    public function get(string $path, array $params = [], string $action = 'get', bool $critical = false): mixed
    {
        return $this->call('GET', $path, $params, $action, $critical);
    }

    /** @param array<string,mixed> $params */
    public function post(string $path, array $params = [], string $action = 'post', bool $critical = false, ?string $operationId = null): mixed
    {
        return $this->call('POST', $path, $params, $action, $critical, $operationId);
    }

    /** @param array<string,mixed> $params */
    public function put(string $path, array $params = [], string $action = 'put', bool $critical = false, ?string $operationId = null): mixed
    {
        return $this->call('PUT', $path, $params, $action, $critical, $operationId);
    }

    /** @param array<string,mixed> $params */
    public function delete(string $path, array $params = [], string $action = 'delete', bool $critical = false, ?string $operationId = null): mixed
    {
        return $this->call('DELETE', $path, $params, $action, $critical, $operationId);
    }

    public function baseUrl(): string
    {
        return rtrim((string) $this->instance->base_url, '/');
    }

    /** @param array<string,mixed> $params */
    private function call(string $method, string $path, array $params, string $action, bool $critical, ?string $operationId = null): mixed
    {
        $tokenId = (string) ($this->credentials['token_id'] ?? '');
        $secret = (string) ($this->credentials['token_secret'] ?? '');
        if ($tokenId === '' || $secret === '') {
            throw new ProviderException('proxmox', ProviderErrorCode::AUTH, 'Proxmox API token is not configured');
        }
        $options = TlsOptions::verify($this->instance, 'proxmox');
        $request = new ProviderRequest(
            provider: 'proxmox', instanceKey: $this->instance->key, method: $method,
            url: $this->baseUrl().'/api2/json'.$path, action: $action,
            headers: ['Authorization' => "PVEAPIToken={$tokenId}={$secret}", 'Accept' => 'application/json'],
            body: $method === 'GET' ? null : $params, bodyType: 'form', query: $method === 'GET' ? $params : [],
            timeoutSeconds: (int) config('onhost.provisioning.provider_timeout_seconds', 10) + 5, critical: $critical, idempotent: $method === 'GET', options: $options, operationId: $operationId,
        );
        $response = $this->http->send($request);

        return $this->unwrap($response, $action);
    }

    private function unwrap(ProviderResponse $response, string $action): mixed
    {
        $json = $response->json();
        if ($response->status === 401 || $response->status === 403) {
            throw new ProviderException('proxmox', ProviderErrorCode::AUTH, "Proxmox rejected the API token for {$action} (HTTP {$response->status})", (string) $response->status);
        }
        if ($response->status === 404) {
            throw new ProviderException('proxmox', ProviderErrorCode::NOT_FOUND, "Proxmox object not found for {$action}", '404');
        }
        if ($response->status === 429) {
            throw new ProviderException('proxmox', ProviderErrorCode::RATE_LIMIT, 'Proxmox rate limited', '429', retryAfterSeconds: $response->retryAfterSeconds() ?? 10);
        }
        if ($response->status >= 500) {
            // Proxmox names the reason of a refusal in the HTTP status line and answers `{"data":null}`. Read from the body alone,
            // every refusal was "server error": a number somebody else holds could not be told from a VM that is busy, nor a VM
            // that is gone — Proxmox has no 404 for a guest, it says its configuration file does not exist
            $message = self::said($json, $response->reason);
            $lower = strtolower($message);
            $code = match (true) {
                str_contains($lower, 'already exists') => ProviderErrorCode::CONFLICT,
                (str_contains($lower, 'configuration file') && str_contains($lower, 'does not exist')) || str_contains($lower, 'no such vm')
                    || str_contains($lower, 'no such machine') || str_contains($lower, 'unable to find configuration file') => ProviderErrorCode::NOT_FOUND,
                // a guest locked by a running task (backup, clone, migration) is the usual one: it passes, so it is retried on the
                // ordinary backoff (10 s … 10 min) — a short fixed pause would spend every attempt of the operation inside one backup
                default => ProviderErrorCode::TRANSIENT,
            };
            throw new ProviderException('proxmox', $code, "Proxmox {$action} failed: {$message}", (string) $response->status);
        }
        if ($response->status >= 400) {
            $errors = is_array($json) ? ($json['errors'] ?? $json['message'] ?? null) : null;
            $reason = $errors === null && $response->reason !== '' ? ' ('.$response->reason.')' : '';
            throw new ProviderException('proxmox', ProviderErrorCode::VALIDATION, "Proxmox {$action} rejected: ".json_encode($errors).$reason, (string) $response->status, ['errors' => $errors]);
        }
        if (! is_array($json) || ! array_key_exists('data', $json)) {
            throw new ProviderException('proxmox', ProviderErrorCode::PROVIDER_BUG, "Proxmox {$action} returned no data envelope", (string) $response->status);
        }
        $this->http->recordSuccess($this->instance->key);

        return $json['data'];
    }

    /** What Proxmox said about a refusal: the body's message when it carries one, else the text of the status line. */
    private static function said(mixed $json, string $statusLine): string
    {
        $said = is_array($json) ? ($json['message'] ?? $json['errors'] ?? null) : null;
        $said = is_array($said) ? (string) json_encode($said, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (is_scalar($said) ? trim((string) $said) : '');
        if ($said === '') {
            $said = $statusLine !== '' ? $statusLine : 'server error';
        }

        return mb_substr($said, 0, 300);
    }
}
