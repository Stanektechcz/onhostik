<?php

declare(strict_types=1);

namespace Onhost\Platform\Secrets;

/**
 * Provider credentials, signing keys and DB master secrets live in OpenBao; the
 * database stores only `secret_ref` strings (blueprint §6.1, §19). Values are read
 * at runtime, never cached to disk, never logged.
 */
interface SecretStore
{
    /** @return array<string,mixed> the key/value payload stored under the reference */
    public function read(SecretRef $ref): array;

    /** @param array<string,mixed> $values */
    public function write(SecretRef $ref, array $values): void;

    public function exists(SecretRef $ref): bool;

    public function health(): SecretStoreHealth;
}
