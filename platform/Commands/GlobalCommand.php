<?php

declare(strict_types=1);

namespace Onhost\Platform\Commands;

/** Base for staff commands that act on platform-wide resources (operations, providers, freeze switch). */
abstract class GlobalCommand implements Command
{
    protected const AUDIT_STRIP = ['password', 'secret', 'token'];

    /** @param array<string,mixed> $payload */
    public function __construct(public readonly string $idempotencyKey, public readonly array $payload = []) {}

    public function scope(): ?CommandScope
    {
        return CommandScope::global();
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function toAudit(): array
    {
        return ['payload' => array_diff_key($this->payload, array_flip(static::AUDIT_STRIP))];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload, $key, $default);
    }
}
