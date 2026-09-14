<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Authorization;

/**
 * Commands whose risk depends on data (refund above threshold, deleting the last
 * backup generation, mass adjustments) declare it dynamically.
 */
interface RiskAwareCommand
{
    /** PermissionCatalog::NORMAL | HIGH | CRITICAL */
    public function riskLevel(): string;

    public function requiresStepUp(): bool;

    public function requiresApproval(): bool;
}
