<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * Desired state handed to an adapter. `idempotencyKey` is the ONhost key
 * (`ord-9012:provision.vps:v1`); adapters MUST look for an existing remote object
 * tagged with it before creating anything (docs-provider-apis §0.5).
 */
final class ResourceSpec
{
    /** @param array<string,mixed> $attributes */
    public function __construct(
        public readonly string $serviceId,
        public readonly string $kind,          // vm | website | game_server | mailbox | zone | domain | namespace …
        public readonly string $idempotencyKey,
        public readonly array $attributes = [],
        public readonly ?string $node = null,
        public readonly ?string $region = null,
        public readonly ?string $organizationId = null,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->attributes, $key, $default);
    }

    public function with(array $attributes): self
    {
        return new self($this->serviceId, $this->kind, $this->idempotencyKey, array_replace($this->attributes, $attributes), $this->node, $this->region, $this->organizationId);
    }
}
