<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

final class ProviderHealth
{
    /** @param array<string,mixed> $detail */
    public function __construct(
        public readonly bool $healthy,
        public readonly ?string $version = null,
        public readonly ?int $latencyMs = null,
        public readonly array $detail = [],
        public readonly ?string $error = null,
    ) {}

    public static function down(string $error, ?int $latencyMs = null): self
    {
        return new self(false, null, $latencyMs, [], $error);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['healthy' => $this->healthy, 'version' => $this->version, 'latency_ms' => $this->latencyMs, 'detail' => $this->detail, 'error' => $this->error];
    }
}
