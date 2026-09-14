<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * Outcome of an adapter call. Either completed synchronously (`ref` populated) or
 * accepted asynchronously (`async` populated) — never "probably done".
 */
final class ProviderResult
{
    /** @param array<string,mixed> $data */
    private function __construct(
        public readonly bool $completed,
        public readonly ?ResourceRef $ref,
        public readonly ?AsyncHandle $async,
        public readonly array $data = [],
        public readonly bool $alreadyExisted = false,
    ) {}

    public static function completed(?ResourceRef $ref, array $data = [], bool $alreadyExisted = false): self
    {
        return new self(true, $ref, null, $data, $alreadyExisted);
    }

    public static function accepted(AsyncHandle $handle, ?ResourceRef $ref = null, array $data = []): self
    {
        return new self(false, $ref, $handle, $data);
    }

    public function isAsync(): bool
    {
        return $this->async !== null;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'completed' => $this->completed,
            'ref' => $this->ref?->toArray(),
            'async' => $this->async?->toArray(),
            'data' => $this->data,
            'already_existed' => $this->alreadyExisted,
        ];
    }
}
