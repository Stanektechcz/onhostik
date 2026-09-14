<?php

declare(strict_types=1);

namespace Onhost\Platform\Secrets;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;

/**
 * OpenBao KV v2 client (HashiCorp Vault compatible API). Authenticates with a
 * short-lived token from the environment or via AppRole (role_id/secret_id are the
 * only bootstrap secrets the worker holds). Reads `bao://<mount>/<path>`.
 */
final class OpenBaoSecretStore implements SecretStore
{
    private ?string $token = null;

    private ?int $tokenExpiresAt = null;

    /** @var array<string, array{values: array<string,mixed>, until:int}> */
    private array $memo = [];

    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $address,
        private readonly ?string $staticToken,
        private readonly ?string $roleId,
        private readonly ?string $secretId,
        private readonly string $namespace = '',
        private readonly int $memoSeconds = 60,
        private readonly ?string $caCertPath = null,
    ) {}

    public function read(SecretRef $ref): array
    {
        if ($ref->scheme !== 'bao') {
            throw new RuntimeException("OpenBaoSecretStore cannot resolve {$ref}");
        }
        $key = $ref->path;
        if (isset($this->memo[$key]) && $this->memo[$key]['until'] > time()) {
            return $this->memo[$key]['values'];
        }
        [$mount, $path] = $this->split($ref->path);
        $response = $this->client()->get("/v1/{$mount}/data/{$path}");
        if ($response->status() === 404) {
            throw new RuntimeException("Secret not found: {$ref}");
        }
        if (! $response->successful()) {
            throw new RuntimeException("OpenBao read failed for {$ref}: HTTP {$response->status()}");
        }
        $values = $response->json('data.data');
        if (! is_array($values)) {
            throw new RuntimeException("OpenBao returned an unexpected payload for {$ref}");
        }
        $this->memo[$key] = ['values' => $values, 'until' => time() + $this->memoSeconds];

        return $values;
    }

    public function write(SecretRef $ref, array $values): void
    {
        [$mount, $path] = $this->split($ref->path);
        $response = $this->client()->post("/v1/{$mount}/data/{$path}", ['data' => $values]);
        if (! $response->successful()) {
            throw new RuntimeException("OpenBao write failed for {$ref}: HTTP {$response->status()}");
        }
        unset($this->memo[$ref->path]);
    }

    public function exists(SecretRef $ref): bool
    {
        try {
            $this->read($ref);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function health(): SecretStoreHealth
    {
        try {
            $response = $this->baseRequest()->get('/v1/sys/health');
            $sealed = (bool) $response->json('sealed', false);
            $ok = $response->status() === 200 && ! $sealed;

            return new SecretStoreHealth($ok, 'openbao', 'HTTP '.$response->status(), $sealed);
        } catch (\Throwable $e) {
            return new SecretStoreHealth(false, 'openbao', $e->getMessage());
        }
    }

    /** @return array{0:string,1:string} */
    private function split(string $path): array
    {
        $parts = explode('/', trim($path, '/'), 2);
        if (count($parts) !== 2) {
            throw new RuntimeException("OpenBao reference must be <mount>/<path>: {$path}");
        }

        return $parts;
    }

    private function client(): PendingRequest
    {
        return $this->baseRequest()->withHeaders(['X-Vault-Token' => $this->token()]);
    }

    private function baseRequest(): PendingRequest
    {
        $request = $this->http->baseUrl(rtrim($this->address, '/'))->timeout(5)->acceptJson();
        if ($this->namespace !== '') {
            $request = $request->withHeaders(['X-Vault-Namespace' => $this->namespace]);
        }
        if ($this->caCertPath) {
            $request = $request->withOptions(['verify' => $this->caCertPath]);
        }

        return $request;
    }

    private function token(): string
    {
        if ($this->token !== null && ($this->tokenExpiresAt === null || $this->tokenExpiresAt > time() + 30)) {
            return $this->token;
        }
        if ($this->staticToken) {
            $this->token = $this->staticToken;

            return $this->token;
        }
        if (! $this->roleId || ! $this->secretId) {
            throw new RuntimeException('OpenBao authentication is not configured (token or AppRole required)');
        }
        $response = $this->baseRequest()->post('/v1/auth/approle/login', ['role_id' => $this->roleId, 'secret_id' => $this->secretId]);
        if (! $response->successful()) {
            throw new RuntimeException('OpenBao AppRole login failed: HTTP '.$response->status());
        }
        $this->token = (string) $response->json('auth.client_token');
        $ttl = (int) $response->json('auth.lease_duration', 3600);
        $this->tokenExpiresAt = time() + $ttl;

        return $this->token;
    }
}
