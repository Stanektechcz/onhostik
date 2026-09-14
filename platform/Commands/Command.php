<?php

declare(strict_types=1);

namespace Onhost\Platform\Commands;

/**
 * A state-changing intent. Every command declares the permission it requires and
 * an idempotency key (blueprint §60.2: UI -> API -> AuthZ -> Command -> Job ...).
 */
interface Command
{
    /** Capability key such as `compute.vm.create`; null for system-internal commands. */
    public function permission(): ?string;

    /** Scope for the permission check: organization id, project id or null for global. */
    public function scope(): ?CommandScope;

    /** Stable idempotency key; the same key never produces two business effects. */
    public function idempotencyKey(): string;

    /** Short type name used in audit and operation records, e.g. `wallet.topup`. */
    public function name(): string;

    /** @return array<string,mixed> redacted payload for audit */
    public function toAudit(): array;
}
