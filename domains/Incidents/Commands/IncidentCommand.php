<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * Staff incident/status/SLA controls, dispatched by `op`:
 *  open{title,severity,components,impact?,affected_services?,security?,visibility?,note?} · update{incident_id,note,state?,public?} ·
 *  resolve{incident_id,note} · postmortem{incident_id,summary,root_cause,timeline?,actions?,publish?} ·
 *  maintenance.schedule{title,components,starts_at,ends_at,impact?,rollback,affected_services?,sla_treatment?,emergency?} ·
 *  maintenance.approve{maintenance_id} · maintenance.cancel{maintenance_id,reason} · maintenance.complete{maintenance_id,note?} ·
 *  probe.register{key,component_key,kind,target,location,expected?,interval_seconds?} ·
 *  credit.candidates{incident_id} · credit.approve{credit_id} · credit.reject{credit_id,reason} · credit.issue{credit_id}
 */
final class IncidentCommand extends GlobalCommand implements RiskAwareCommand
{
    public const OPS = ['open', 'update', 'resolve', 'postmortem', 'maintenance.schedule', 'maintenance.approve', 'maintenance.cancel', 'maintenance.complete', 'probe.register', 'credit.candidates', 'credit.approve', 'credit.reject', 'credit.issue'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return match ($this->op()) {
            'open' => ($this->get('security') ? 'security.incident.manage' : ((string) $this->get('visibility', 'public') === 'public' ? 'incident.publish' : 'incident.manage')),
            'update', 'resolve', 'postmortem' => (bool) $this->get('public', true) ? 'incident.publish' : 'incident.manage',
            'maintenance.schedule', 'maintenance.approve', 'maintenance.cancel', 'maintenance.complete' => 'maintenance.manage',
            'probe.register' => 'incident.manage',
            'credit.candidates', 'credit.approve', 'credit.reject', 'credit.issue' => 'sla.credit.manage',
            default => 'incident.manage',
        };
    }

    public function name(): string
    {
        return 'incident.'.$this->op();
    }

    public function riskLevel(): string
    {
        return in_array($this->op(), ['credit.issue', 'maintenance.approve'], true) ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
    }

    /** Issuing money (SLA credit) is a financial action: fresh step-up required. */
    public function requiresStepUp(): bool
    {
        return $this->op() === 'credit.issue';
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
