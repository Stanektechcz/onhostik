<?php

declare(strict_types=1);

namespace Onhost\Domain\Compliance\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * Staff compliance controls, dispatched by `op`:
 *  cyber.open{…} · cyber.transition{case_id,state,summary?} · cyber.evidence{case_id,name,sha256,path?} ·
 *  timer.submit{timer_id,authority_reference,evidence?} · timer.waive{timer_id,reason} ·
 *  abuse.triage{case_id,decision,reason} · abuse.notify{case_id,statement} · abuse.action{case_id,action,reason} · abuse.close{case_id,note?} ·
 *  legal_hold{organization_id,hold,reason} · data_request.process{}
 */
final class ComplianceCommand extends GlobalCommand implements RiskAwareCommand
{
    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return match ($this->op()) {
            'cyber.open', 'cyber.transition', 'cyber.evidence' => 'security.incident.manage',
            'timer.submit', 'timer.waive', 'data_request.process' => 'compliance.case.manage',
            'abuse.triage', 'abuse.notify', 'abuse.action', 'abuse.close' => 'abuse.case.manage',
            'legal_hold' => 'compliance.legal_hold.manage',
            default => 'compliance.case.manage',
        };
    }

    public function name(): string
    {
        return 'compliance.'.$this->op();
    }

    public function riskLevel(): string
    {
        return match (true) {
            $this->op() === 'legal_hold', $this->op() === 'timer.waive' => PermissionCatalog::HIGH,
            $this->op() === 'abuse.action' && $this->get('action') === 'service_suspended' => PermissionCatalog::HIGH,
            default => PermissionCatalog::NORMAL,
        };
    }

    /** Suspending a customer for abuse, waiving a regulatory deadline and applying/lifting legal hold need a fresh step-up (legal hold is audited with the reason; the CRITICAL catalog level is enforced by the compliance_legal role scope). */
    public function requiresStepUp(): bool
    {
        return $this->op() === 'legal_hold' || ($this->op() === 'abuse.action' && $this->get('action') === 'service_suspended');
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
