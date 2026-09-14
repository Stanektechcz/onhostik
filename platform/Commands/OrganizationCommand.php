<?php

declare(strict_types=1);

namespace Onhost\Platform\Commands;

/**
 * Base for customer-facing commands: organization scope, explicit idempotency key,
 * payload with secrets stripped from the audit trail.
 */
abstract class OrganizationCommand implements Command
{
    protected const AUDIT_STRIP = ['password', 'auth_info', 'secret', 'code', 'token', 'totp', 'recovery_code'];

    /** @param array<string,mixed> $payload */
    public function __construct(
        public readonly string $organizationId,
        public readonly string $idempotencyKey,
        public readonly array $payload = [],
    ) {}

    public function scope(): ?CommandScope
    {
        return CommandScope::organization($this->organizationId);
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function toAudit(): array
    {
        return ['organization_id' => $this->organizationId, 'payload' => array_diff_key($this->payload, array_flip(static::AUDIT_STRIP))];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload, $key, $default);
    }
}
