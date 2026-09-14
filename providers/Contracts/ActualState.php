<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/** Observed state of a remote resource, normalised to ONhost field names. */
final class ActualState
{
    /** @param array<string,mixed> $attributes */
    public function __construct(
        public readonly bool $exists,
        public readonly array $attributes = [],
        public readonly ?string $status = null,   // running | stopped | suspended | installing | active | …
        public readonly ?string $observedAt = null,
    ) {}

    public static function missing(): self
    {
        return new self(false, [], 'missing', now()->toISOString());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->attributes, $key, $default);
    }

    public function checksum(): string
    {
        $data = $this->attributes;
        ksort($data);

        return substr(hash('sha256', json_encode($data) ?: ''), 0, 32);
    }
}
