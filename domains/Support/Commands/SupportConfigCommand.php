<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * The support desk's own settings: macros, queues and SLA policies (blueprint §68.4–68.6). They were seeded once and could
 * not be changed without a deploy (readiness audit 2026-10, P1-3). Dispatched by `kind` (macro | queue | sla_policy) and
 * `op` (create | update | delete); `id` names the row for update and delete, `data` carries the validated fields.
 */
final class SupportConfigCommand extends GlobalCommand implements RiskAwareCommand
{
    public const KINDS = ['macro', 'queue', 'sla_policy'];

    public const OPS = ['create', 'update', 'delete'];

    public function kind(): string
    {
        return (string) $this->get('kind');
    }

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): string
    {
        return 'support.queue.manage';
    }

    public function name(): string
    {
        return 'support.'.$this->kind().'.'.$this->op();
    }

    public function riskLevel(): string
    {
        return PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return false;
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
