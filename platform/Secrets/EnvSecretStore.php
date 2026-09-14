<?php

declare(strict_types=1);

namespace Onhost\Platform\Secrets;

use RuntimeException;

/**
 * Development/CI store: `env://PROXMOX_CZ1` resolves keys `PROXMOX_CZ1_TOKEN_ID`,
 * `PROXMOX_CZ1_TOKEN_SECRET`, … from the environment. Production MUST use OpenBao
 * (`config('onhost.secrets.driver') = 'openbao'`); the application refuses to boot
 * in production with this driver (see SecretsServiceProvider).
 */
final class EnvSecretStore implements SecretStore
{
    /** @var array<string, array<string,mixed>> */
    private array $memory = [];

    public function read(SecretRef $ref): array
    {
        if ($ref->scheme === 'file') {
            $path = base_path($ref->path);
            if (! is_file($path)) {
                throw new RuntimeException("Secret file not found: {$ref}");
            }
            $decoded = json_decode((string) file_get_contents($path), true);

            return is_array($decoded) ? $decoded : [];
        }
        if ($ref->scheme !== 'env') {
            throw new RuntimeException("EnvSecretStore cannot resolve {$ref}; configure the OpenBao driver");
        }
        if (isset($this->memory[$ref->path])) {
            return $this->memory[$ref->path];
        }
        $prefix = strtoupper(str_replace(['/', '-', '.'], '_', $ref->path)).'_';
        $values = [];
        foreach (array_merge(getenv() ?: [], $_ENV) as $key => $value) { // $_ENV wins over putenv values (tests and process overrides)
            if (is_string($key) && str_starts_with($key, $prefix) && $value !== false) {
                $values[strtolower(substr($key, strlen($prefix)))] = $value;
            }
        }
        if ($values === []) {
            throw new RuntimeException("No environment secrets found for {$ref} (prefix {$prefix})");
        }

        return $values;
    }

    public function write(SecretRef $ref, array $values): void
    {
        $this->memory[$ref->path] = $values;
    }

    public function exists(SecretRef $ref): bool
    {
        try {
            return $this->read($ref) !== [];
        } catch (RuntimeException) {
            return false;
        }
    }

    public function health(): SecretStoreHealth
    {
        return new SecretStoreHealth(true, 'env', 'development driver — not permitted in production');
    }
}
