<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents;

use Onhost\Domain\Compliance\Models\AbuseCase;
use Onhost\Domain\Compliance\Models\ComplianceTimer;
use Onhost\Domain\Compliance\Models\CyberIncident;
use Onhost\Domain\Compliance\Models\DataRequest;
use Onhost\Domain\Incidents\Models\Incident;
use Onhost\Domain\Incidents\Models\IncidentUpdate;
use Onhost\Domain\Incidents\Models\Maintenance;
use Onhost\Domain\Incidents\Models\SlaCredit;
use Onhost\Domain\Incidents\Models\SlaProbe;
use Onhost\Platform\Money\Money;

/** JSON projections for incidents, maintenance, SLA and compliance objects (staff view includes internal updates). */
final class Presenters
{
    public static function incident(Incident $incident, bool $internal = false): array
    {
        $machine = IncidentStateMachine::machine();
        $updates = $internal ? $incident->updates()->get() : $incident->updates()->where('public', true)->get();

        return [
            'id' => $incident->id, 'number' => $incident->number, 'title' => $incident->title, 'sev' => $incident->severity, 'state' => $incident->state, 'ui_state' => $incident->uiState(),
            'state_label' => $machine->label($incident->state), 'next' => $machine->nextStates($incident->state), 'visibility' => $incident->visibility, 'security' => $incident->security,
            'impact' => $incident->impact, 'components' => $incident->components, 'affected_services' => $internal ? $incident->affected_services : null, 'source' => $incident->source,
            'commander_id' => $internal ? $incident->commander_id : null, 'started_at' => $incident->started_at->toIso8601String(), 'detected_at' => $incident->detected_at->toIso8601String(),
            'mitigated_at' => $incident->mitigated_at?->toIso8601String(), 'resolved_at' => $incident->resolved_at?->toIso8601String(), 'duration' => $incident->durationLabel(), 'sla_relevant' => $incident->sla_relevant,
            'hist' => $updates->map(fn (IncidentUpdate $u) => ['state' => $u->state, 'ui_state' => Incident::UI_STATES[$u->state] ?? 'vysetrovani', 'at' => $u->created_at->toIso8601String(), 'note' => $u->note, 'public' => $u->public, 'author_id' => $internal ? $u->author_id : null])->values()->all(),
            'postmortem' => $internal || ($incident->postmortem['published'] ?? false) ? $incident->postmortem : null,
        ];
    }

    public static function maintenance(Maintenance $m): array
    {
        return [
            'id' => $m->id, 'number' => $m->number, 'title' => $m->title, 'components' => $m->components, 'affected_services' => $m->affected_services, 'starts_at' => $m->starts_at->toIso8601String(), 'ends_at' => $m->ends_at->toIso8601String(),
            'impact' => $m->impact, 'rollback' => $m->rollback, 'owner_id' => $m->owner_id, 'approved_by' => $m->approved_by, 'change_ticket' => $m->change_ticket, 'sla_treatment' => $m->sla_treatment, 'state' => $m->state,
            'started_at' => $m->started_at?->toIso8601String(), 'completed_at' => $m->completed_at?->toIso8601String(),
        ];
    }

    public static function probe(SlaProbe $p): array
    {
        return ['id' => $p->id, 'key' => $p->key, 'component' => $p->component_key, 'kind' => $p->kind, 'target' => $p->target, 'location' => $p->location, 'expected' => $p->expected, 'interval_seconds' => $p->interval_seconds, 'state' => $p->state, 'last_seen_at' => $p->last_seen_at?->toIso8601String()];
    }

    public static function credit(SlaCredit $c): array
    {
        return [
            'id' => $c->id, 'organization_id' => $c->organization_id, 'incident_id' => $c->incident_id, 'service_id' => $c->service_id, 'policy_id' => $c->policy_id, 'availability_pct' => $c->availability_pct, 'credit_percent' => $c->credit_percent,
            'amount' => Money::minor($c->amount_minor, $c->currency), 'state' => $c->state, 'credit_note_id' => $c->invoice_id, 'calculation' => $c->calculation, 'approved_by' => $c->approved_by, 'issued_at' => $c->issued_at?->toIso8601String(), 'created_at' => $c->created_at?->toIso8601String(),
        ];
    }

    public static function cyberIncident(CyberIncident $c): array
    {
        return [
            'id' => $c->id, 'number' => $c->number, 'title' => $c->title, 'severity' => $c->severity, 'state' => $c->state, 'detected_at' => $c->detected_at->toIso8601String(), 'incident_id' => $c->incident_id,
            'affected_services' => $c->affected_services, 'jurisdictions' => $c->jurisdictions, 'personal_data_breach' => $c->personal_data_breach, 'life_safety_crime_suspicion' => $c->life_safety_crime_suspicion, 'nis2_scope' => $c->nis2_scope,
            'owner_id' => $c->owner_id, 'summary' => $c->summary, 'evidence' => $c->evidence, 'timers' => $c->timers()->get()->map(fn (ComplianceTimer $t) => self::timer($t))->values()->all(),
        ];
    }

    public static function timer(ComplianceTimer $t): array
    {
        return ['id' => $t->id, 'timer' => $t->timer, 'label' => $t->label(), 'case_type' => $t->case_type, 'case_id' => $t->case_id, 'state' => $t->state, 'starts_at' => $t->starts_at->toIso8601String(), 'deadline_at' => $t->deadline_at->toIso8601String(), 'remaining_seconds' => $t->state === 'running' ? $t->remainingSeconds() : null, 'submitted_at' => $t->submitted_at?->toIso8601String(), 'authority_reference' => $t->authority_reference, 'owner_id' => $t->owner_id];
    }

    public static function abuseCase(AbuseCase $a, bool $internal = false): array
    {
        return [
            'id' => $a->id, 'number' => $a->number, 'category' => $a->category, 'state' => $a->state, 'target_url' => $a->target_url, 'service_id' => $a->service_id, 'organization_id' => $a->organization_id, 'jurisdiction' => $a->jurisdiction, 'art18' => $a->art18,
            'reporter' => $internal ? $a->reporter : null, 'allegation' => $internal ? $a->allegation : null, 'evidence' => $internal ? $a->evidence : null,
            'decision' => $a->decision, 'decision_reason' => $a->decision_reason, 'action_taken' => $a->action_taken, 'ticket_id' => $a->ticket_id, 'handled_by' => $internal ? $a->handled_by : null,
            'customer_notified_at' => $a->customer_notified_at?->toIso8601String(), 'decided_at' => $a->decided_at?->toIso8601String(), 'appeal_deadline_at' => $a->appeal_deadline_at?->toIso8601String(), 'created_at' => $a->created_at?->toIso8601String(),
        ];
    }

    public static function dataRequest(DataRequest $r): array
    {
        return ['id' => $r->id, 'organization_id' => $r->organization_id, 'kind' => $r->kind, 'state' => $r->state, 'reason' => $r->reason, 'requested_by' => $r->requested_by, 'ready_at' => $r->ready_at?->toIso8601String(), 'expires_at' => $r->expires_at?->toIso8601String(), 'completed_at' => $r->completed_at?->toIso8601String(), 'meta' => $r->meta, 'created_at' => $r->created_at?->toIso8601String()];
    }
}
