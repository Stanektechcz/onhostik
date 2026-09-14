<?php

declare(strict_types=1);

namespace Onhost\Platform\Secrets;

final class SecretStoreHealth
{
    public function __construct(
        public readonly bool $healthy,
        public readonly string $driver,
        public readonly ?string $detail = null,
        public readonly bool $sealed = false,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['healthy' => $this->healthy, 'driver' => $this->driver, 'detail' => $this->detail, 'sealed' => $this->sealed];
    }
}
