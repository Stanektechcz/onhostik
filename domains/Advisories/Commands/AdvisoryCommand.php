<?php

declare(strict_types=1);

namespace Onhost\Domain\Advisories\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * Staff decisions about a security advisory case (SN), dispatched by `op`:
 *  case.triage{case_id, decision: relevant|dismissed, note?} · case.approve{case_id, include_risky, plan_revision, targets, note?} ·
 *  case.notify{case_id, note?} · case.draft_post{case_id}.
 *
 * Approving changes customers' sites: HIGH (step-up). A plan that changes more than `onhost.advisories.single_approver_max` sites,
 * or includes fixes that may change how a site behaves (class B), takes a second person (four eyes); the approval is bound to the
 * plan revision and the number of sites, so a plan that grew since is approved again. Telling customers is outward: HIGH.
 */
final class AdvisoryCommand extends GlobalCommand implements RiskAwareCommand
{
    public const OPS = ['case.triage', 'case.approve', 'case.notify', 'case.draft_post'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): string
    {
        return match ($this->op()) {
            'case.approve', 'case.notify' => 'security.advisory.remediate',
            default => 'security.advisory.manage',
        };
    }

    public function name(): string
    {
        return 'security.advisory.'.$this->op();
    }

    public function riskLevel(): string
    {
        return in_array($this->op(), ['case.approve', 'case.notify'], true) ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return in_array($this->op(), ['case.approve', 'case.notify'], true);
    }

    public function requiresApproval(): bool
    {
        return $this->op() === 'case.approve' && ((bool) $this->get('include_risky', false) || (int) $this->get('targets', 0) > self::singleApproverMax());
    }

    public static function singleApproverMax(): int
    {
        return max(0, (int) config('onhost.advisories.single_approver_max', 25));
    }
}
