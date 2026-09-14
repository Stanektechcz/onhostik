<?php

declare(strict_types=1);

namespace Onhost\Platform\Commands;

final class AuthorizationDecision
{
    /** @param list<string> $approvalIds */
    private function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason = null,
        public readonly ?string $stepUpMethod = null,
        public readonly array $approvalIds = [],
        public readonly ?string $requirement = null, // step_up | approval when denied for that reason
    ) {}

    /** @param list<string> $approvalIds */
    public static function allow(?string $stepUpMethod = null, array $approvalIds = []): self
    {
        return new self(true, null, $stepUpMethod, $approvalIds);
    }

    public static function deny(string $reason, ?string $requirement = null): self
    {
        return new self(false, $reason, requirement: $requirement);
    }
}
