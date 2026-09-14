<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/** Pointer to a resource at a provider (from provider_bindings). Never customer-facing. */
final class ResourceRef
{
    /** @param array<string,mixed> $meta */
    public function __construct(
        public readonly string $remoteType,   // qemu | site | server | client | web_domain | zone | domain …
        public readonly string $remoteId,
        public readonly ?string $node = null,
        public readonly array $meta = [],
        public readonly ?string $serviceId = null,
    ) {}

    public function withMeta(array $meta): self
    {
        return new self($this->remoteType, $this->remoteId, $this->node, array_merge($this->meta, $meta), $this->serviceId);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['remote_type' => $this->remoteType, 'remote_id' => $this->remoteId, 'node' => $this->node, 'meta' => $this->meta, 'service_id' => $this->serviceId];
    }
}
