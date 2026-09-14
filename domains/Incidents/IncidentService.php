<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Incidents\Models\Incident;
use Onhost\Domain\Incidents\Models\IncidentUpdate;
use Onhost\Domain\Incidents\Models\Maintenance;
use Onhost\Domain\Incidents\Models\StatusComponent;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Incident lifecycle (blueprint §73.2): open → status updates → resolve → post-mortem,
 * with the public status page deriving component states from open incidents and
 * maintenance windows. Customers of affected services are notified via the outbox
 * (`incident.*` events → NotificationRouter, mandatory kind `incident.affecting`).
 */
final class IncidentService
{
    public function __construct(
        private readonly OutboxPublisher $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array{title:string,severity?:string,components:list<string>,impact?:string,visibility?:string,security?:bool,affected_services?:list<string>,started_at?:string,source?:string,sla_relevant?:bool,note?:string,commander_id?:string}  $input
     */
    public function open(array $input, CommandContext $context): Incident
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new DomainError('incident_title_required', 'Incident needs a title.', 422, ['field' => 'title']);
        }
        $severity = strtolower((string) ($input['severity'] ?? 'p3'));
        if (! in_array($severity, Incident::SEVERITIES, true)) {
            throw new DomainError('incident_severity_invalid', 'Severity must be p1–p4.', 422, ['field' => 'severity']);
        }
        $components = array_values(array_unique(array_map('strval', (array) ($input['components'] ?? []))));
        if ($components === []) {
            throw new DomainError('incident_components_required', 'Select at least one status component.', 422, ['field' => 'components']);
        }
        $known = StatusComponent::query()->whereIn('key', $components)->pluck('key')->all();
        if ($missing = array_diff($components, $known)) {
            throw new DomainError('incident_component_unknown', 'Unknown status component: '.implode(', ', $missing), 422, ['field' => 'components']);
        }
        $security = (bool) ($input['security'] ?? false);
        $services = array_values(array_unique(array_map('strval', (array) ($input['affected_services'] ?? []))));
        $organizations = $services === [] ? [] : Service::query()->whereIn('id', $services)->pluck('organization_id')->unique()->values()->all();
        $startedAt = isset($input['started_at']) ? now()->parse((string) $input['started_at']) : now();

        $incident = DB::transaction(function () use ($input, $title, $severity, $components, $security, $services, $organizations, $startedAt, $context) {
            $incident = $this->withNumber(fn (string $number) => Incident::query()->create([
                'number' => $number,
                'title' => $title,
                'severity' => $severity,
                'state' => IncidentStateMachine::DETECTED,
                'visibility' => $security ? 'internal' : (string) ($input['visibility'] ?? 'public'),
                'security' => $security,
                'impact' => isset($input['impact']) ? mb_substr((string) $input['impact'], 0, 2000) : null,
                'components' => $components,
                'affected_services' => $services,
                'affected_organizations' => $organizations,
                'commander_id' => $input['commander_id'] ?? ($context->actorType === 'user' ? $context->actorId : null),
                'source' => (string) ($input['source'] ?? 'manual'),
                'started_at' => $startedAt,
                'detected_at' => now(),
                'sla_relevant' => (bool) ($input['sla_relevant'] ?? true),
                'meta' => $input['meta'] ?? null,
            ]));
            IncidentUpdate::query()->create([
                'incident_id' => $incident->id, 'state' => $incident->state, 'public' => $incident->visibility === 'public',
                'note' => (string) ($input['note'] ?? ($incident->impact ?? 'Incident detekován, probíhá vyšetřování.')), 'author_id' => $context->actorId,
            ]);
            $this->refreshComponents($components);
            $this->audit->record($context, 'incident.open', 'succeeded', ['number' => $incident->number, 'severity' => $severity, 'components' => $components, 'security' => $security], 'incident', $incident->id);

            return $incident;
        });

        $this->publish('incident.opened', $incident, ['impact' => $incident->impact, 'state' => $incident->state, 'state_label' => $this->label($incident->state)]);
        if ($security) {
            $this->outbox->publish(GenericEvent::of('security.incident.opened', 'incident', $incident->id, ['number' => $incident->number, 'title' => $incident->title, 'severity' => $severity]));
        }

        return $incident;
    }

    /** Post a status update, optionally transitioning state. Internal notes never reach the status page. */
    public function update(Incident $incident, string $note, CommandContext $context, ?string $state = null, bool $public = true): Incident
    {
        $note = trim($note);
        if ($note === '') {
            throw new DomainError('incident_note_required', 'Update note is required.', 422, ['field' => 'note']);
        }
        $target = $state !== null ? strtoupper($state) : $incident->state;
        if ($target === IncidentStateMachine::RESOLVED || $target === IncidentStateMachine::POSTMORTEM) {
            throw new DomainError('incident_use_resolve', 'Use resolve/postmortem for terminal states.', 422);
        }
        if ($target !== $incident->state) {
            IncidentStateMachine::machine()->assertTransition($incident->state, $target);
        }
        $public = $public && $incident->visibility === 'public';

        DB::transaction(function () use ($incident, $note, $target, $public, $context): void {
            $from = $incident->state;
            $incident->forceFill(['state' => $target, 'mitigated_at' => $target === IncidentStateMachine::MONITORING && $incident->mitigated_at === null ? now() : $incident->mitigated_at]);
            if ($from === IncidentStateMachine::RESOLVED) {
                $incident->resolved_at = null; // reopened
            }
            $incident->save();
            IncidentUpdate::query()->create(['incident_id' => $incident->id, 'state' => $target, 'note' => $note, 'public' => $public, 'author_id' => $context->actorId]);
            $this->refreshComponents($incident->components);
            $this->audit->record($context, 'incident.update', 'succeeded', ['number' => $incident->number, 'from' => $from, 'to' => $target, 'public' => $public], 'incident', $incident->id);
        });

        if ($public) {
            $this->publish('incident.updated', $incident, ['note' => $note, 'state' => $incident->state, 'state_label' => $this->label($incident->state)]);
        }

        return $incident;
    }

    public function resolve(Incident $incident, string $note, CommandContext $context): Incident
    {
        IncidentStateMachine::machine()->assertTransition($incident->state, IncidentStateMachine::RESOLVED);
        DB::transaction(function () use ($incident, $note, $context): void {
            $incident->forceFill(['state' => IncidentStateMachine::RESOLVED, 'resolved_at' => now(), 'mitigated_at' => $incident->mitigated_at ?? now()])->save();
            IncidentUpdate::query()->create(['incident_id' => $incident->id, 'state' => $incident->state, 'note' => trim($note) !== '' ? $note : 'Incident vyřešen.', 'public' => $incident->visibility === 'public', 'author_id' => $context->actorId]);
            $this->refreshComponents($incident->components);
            $this->audit->record($context, 'incident.resolve', 'succeeded', ['number' => $incident->number, 'duration_seconds' => $incident->durationSeconds()], 'incident', $incident->id);
        });
        if ($incident->visibility === 'public') {
            $this->publish('incident.resolved', $incident, ['note' => $note, 'duration' => $incident->durationLabel()]);
        }

        return $incident;
    }

    /**
     * Blameless post-mortem (blueprint §73.4): mandatory for p1/p2 and for incidents that burned >20 % of a budget.
     *
     * @param  array{summary:string,root_cause:string,timeline?:list<array{at:string,what:string}>,actions?:list<array{owner:string,deadline:string,what:string,verified?:bool}>,publish?:bool}  $postmortem
     */
    public function postmortem(Incident $incident, array $postmortem, CommandContext $context): Incident
    {
        IncidentStateMachine::machine()->assertTransition($incident->state, IncidentStateMachine::POSTMORTEM);
        foreach (['summary', 'root_cause'] as $field) {
            if (trim((string) ($postmortem[$field] ?? '')) === '') {
                throw new DomainError('postmortem_incomplete', "Post-mortem requires {$field}.", 422, ['field' => $field]);
            }
        }
        foreach ((array) ($postmortem['actions'] ?? []) as $i => $action) {
            if (trim((string) ($action['owner'] ?? '')) === '' || empty($action['deadline'])) {
                throw new DomainError('postmortem_action_incomplete', 'Every corrective action needs an owner and a deadline.', 422, ['field' => "actions.{$i}"]);
            }
        }
        $publish = (bool) ($postmortem['publish'] ?? ($incident->visibility === 'public' && ! $incident->security));
        DB::transaction(function () use ($incident, $postmortem, $publish, $context): void {
            $incident->forceFill(['state' => IncidentStateMachine::POSTMORTEM, 'postmortem' => [
                'summary' => $postmortem['summary'], 'root_cause' => $postmortem['root_cause'], 'timeline' => array_values((array) ($postmortem['timeline'] ?? [])),
                'actions' => array_values((array) ($postmortem['actions'] ?? [])), 'published' => $publish, 'published_at' => $publish ? now()->toIso8601String() : null,
            ]])->save();
            IncidentUpdate::query()->create(['incident_id' => $incident->id, 'state' => $incident->state, 'note' => 'Post-mortem zveřejněn.', 'public' => $publish, 'author_id' => $context->actorId]);
            $this->audit->record($context, 'incident.postmortem', 'succeeded', ['number' => $incident->number, 'published' => $publish, 'actions' => count($postmortem['actions'] ?? [])], 'incident', $incident->id);
        });

        return $incident;
    }

    /** Component state = worst open incident severity, else `maintenance` while a window runs, else operational. */
    public function refreshComponents(?array $only = null): void
    {
        $now = now();
        $components = StatusComponent::query()->when($only !== null, fn ($q) => $q->whereIn('key', $only))->get();
        $open = Incident::query()->whereNotIn('state', [IncidentStateMachine::RESOLVED, IncidentStateMachine::POSTMORTEM])->get(['id', 'severity', 'components']);
        $maintenance = Maintenance::query()->whereIn('state', ['approved', 'in_progress'])->where('starts_at', '<=', $now)->where('ends_at', '>=', $now)->get(['id', 'components']);
        foreach ($components as $component) {
            $state = 'operational';
            foreach ($maintenance as $window) {
                if (in_array($component->key, $window->components, true)) {
                    $state = 'maintenance';
                }
            }
            foreach ($open as $incident) {
                if (in_array($component->key, $incident->components, true)) {
                    $candidate = StatusComponent::SEVERITY_STATE[$incident->severity] ?? 'degraded';
                    if (StatusComponent::rank($candidate) > StatusComponent::rank($state)) {
                        $state = $candidate;
                    }
                }
            }
            if ($component->state !== $state) {
                $component->forceFill(['state' => $state])->save();
            }
        }
    }

    /**
     * Public status snapshot (prototype `status(cs)` shape: components with 90-day bars, open incidents, upcoming maintenance).
     *
     * @return array{overall:string,components:list<array<string,mixed>>,incidents:list<array<string,mixed>>,maintenance:list<array<string,mixed>>,generated_at:string}
     */
    public function publicStatus(int $days = 90): array
    {
        $components = StatusComponent::query()->where('public', true)->orderBy('sort')->get();
        $since = now()->subDays($days)->startOfDay();
        $history = Incident::query()->where('visibility', 'public')->where('sla_relevant', true)->where('started_at', '>=', $since)->get(['severity', 'components', 'started_at', 'resolved_at']);
        $overall = 'operational';
        $out = [];
        foreach ($components as $component) {
            $bad = [];
            $downSeconds = 0;
            foreach ($history as $incident) {
                if (! in_array($component->key, $incident->components, true) || ! in_array($incident->severity, ['p1', 'p2'], true)) {
                    continue;
                }
                $start = $incident->started_at->max($since);
                $end = $incident->resolved_at ?? now();
                $downSeconds += max(0, (int) $start->diffInSeconds($end));
                for ($d = $start->copy()->startOfDay(); $d <= $end; $d->addDay()) {
                    $idx = (int) $since->diffInDays($d);
                    if ($idx >= 0 && $idx < $days) {
                        $bad[$idx] = true;
                    }
                }
            }
            $uptime = round(max(0.0, 100 - ($downSeconds / ($days * 86400)) * 100), 3);
            if (StatusComponent::rank($component->state) > StatusComponent::rank($overall)) {
                $overall = $component->state;
            }
            $out[] = ['key' => $component->key, 'n' => $component->name, 'group' => $component->group, 'state' => $component->state, 'u' => $uptime, 'bad' => array_map('intval', array_keys($bad))];
        }
        $incidents = Incident::query()->where('visibility', 'public')->whereNotIn('state', [IncidentStateMachine::POSTMORTEM])->where(fn ($q) => $q->whereNotIn('state', [IncidentStateMachine::RESOLVED])->orWhere('resolved_at', '>=', now()->subDays(7)))->orderByDesc('started_at')->limit(20)->get();
        $maintenance = Maintenance::query()->whereIn('state', ['planned', 'approved', 'in_progress'])->where('ends_at', '>=', now())->orderBy('starts_at')->limit(20)->get();

        return [
            'overall' => $overall,
            'components' => $out,
            'incidents' => $incidents->map(fn (Incident $i) => $this->publicIncident($i))->values()->all(),
            'maintenance' => $maintenance->map(fn (Maintenance $m) => ['number' => $m->number, 'title' => $m->title, 'components' => $m->components, 'starts_at' => $m->starts_at->toIso8601String(), 'ends_at' => $m->ends_at->toIso8601String(), 'impact' => $m->impact, 'state' => $m->state])->values()->all(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /** Public projection: number, title, severity, state (+ prototype UI key), public updates only, published post-mortem. */
    public function publicIncident(Incident $incident): array
    {
        $updates = $incident->updates()->where('public', true)->get();

        return [
            'number' => $incident->number, 'title' => $incident->title, 'sev' => $incident->severity, 'state' => $incident->state, 'ui_state' => $incident->uiState(),
            'state_label' => $this->label($incident->state), 'components' => $incident->components, 'impact' => $incident->impact,
            'started_at' => $incident->started_at->toIso8601String(), 'resolved_at' => $incident->resolved_at?->toIso8601String(), 'duration' => $incident->durationLabel(),
            'hist' => $updates->map(fn (IncidentUpdate $u) => ['state' => $u->state, 'ui_state' => Incident::UI_STATES[$u->state] ?? 'vysetrovani', 'at' => $u->created_at->toIso8601String(), 'note' => $u->note])->values()->all(),
            'postmortem' => ($incident->postmortem['published'] ?? false) ? array_diff_key($incident->postmortem, ['published' => 1]) : null,
        ];
    }

    /** MTTR/MTTA and counts per severity for a period (blueprint §73.5 KPIs). */
    public function metrics(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $incidents = Incident::query()->where('started_at', '>=', $from)->where('started_at', '<=', $to)->get();
        $resolved = $incidents->filter(fn (Incident $i) => $i->resolved_at !== null);
        $bySeverity = [];
        foreach (Incident::SEVERITIES as $sev) {
            $bySeverity[$sev] = $incidents->where('severity', $sev)->count();
        }
        $mttr = $resolved->isEmpty() ? null : (int) round($resolved->avg(fn (Incident $i) => $i->durationSeconds()));
        $mtta = $incidents->isEmpty() ? null : (int) round($incidents->avg(fn (Incident $i) => max(0, (int) $i->started_at->diffInSeconds($i->detected_at))));
        $postmortemDue = $incidents->filter(fn (Incident $i) => in_array($i->severity, ['p1', 'p2'], true) && $i->state === IncidentStateMachine::RESOLVED)->count();

        return [
            'from' => $from->format(DATE_ATOM), 'to' => $to->format(DATE_ATOM), 'total' => $incidents->count(), 'open' => $incidents->filter(fn (Incident $i) => $i->isOpen())->count(),
            'by_severity' => $bySeverity, 'mttr_seconds' => $mttr, 'mtta_seconds' => $mtta, 'postmortems_pending' => $postmortemDue,
            'security' => $incidents->where('security', true)->count(),
        ];
    }

    public function label(string $state): string
    {
        return IncidentStateMachine::machine()->label($state);
    }

    private function publish(string $event, Incident $incident, array $extra): void
    {
        $payload = ['number' => $incident->number, 'title' => $incident->title, 'severity' => $incident->severity, 'components' => $incident->components] + $extra;
        // One outbox message per affected organization so NotificationRouter can mail each customer; one internal message without organization.
        $organizations = (array) ($incident->affected_organizations ?? []);
        if ($organizations === []) {
            $this->outbox->publish(GenericEvent::of($event, 'incident', $incident->id, $payload));

            return;
        }
        foreach ($organizations as $organizationId) {
            $this->outbox->publish(GenericEvent::of($event, 'incident', $incident->id, $payload, $organizationId));
        }
    }

    /** INC-YYYY-NNNN with a retry on the unique index. */
    private function withNumber(callable $create): Incident
    {
        $year = now()->format('Y');
        for ($i = 0; $i < 5; $i++) {
            $last = (int) DB::table('incidents')->where('number', 'like', "INC-{$year}-%")->selectRaw('max(cast(substr(number, 10) as integer)) as n')->value('n');
            $number = sprintf('INC-%s-%04d', $year, $last + 1 + $i);
            try {
                return DB::transaction(fn () => $create($number)); // savepoint: a taken number does not abort the outer transaction (PostgreSQL)
            } catch (QueryException $e) {
                if (! str_contains(strtolower($e->getMessage()), 'unique')) {
                    throw $e;
                }
            }
        }
        throw new DomainError('incident_number_conflict', 'Could not allocate an incident number.', 500);
    }
}
