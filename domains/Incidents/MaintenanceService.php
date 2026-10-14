<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Incidents\Models\Maintenance;
use Onhost\Domain\Incidents\Models\StatusComponent;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Planned maintenance (blueprint §73.3): scheduled with impact + rollback, approved by a
 * second person (change management), announced to affected customers with the configured
 * lead time, excluded from SLA by default. `tick()` moves windows through in_progress → completed.
 */
final class MaintenanceService
{
    public function __construct(
        private readonly OutboxPublisher $outbox,
        private readonly AuditRecorder $audit,
        private readonly IncidentService $incidents,
    ) {}

    /** Service families a window may name (every service of the family is touched). */
    public const FAMILIES = ['web', 'managed', 'cloud', 'game', 'mail', 'data', 'apps', 'penpot'];

    /** How long before the start the reminder goes out. */
    public const REMINDER_HOURS = 24;

    /** @param array{title:string,components?:list<string>,starts_at:string,ends_at:string,impact?:string,rollback?:string,affected_services?:list<string>,affected_nodes?:list<string>,affected_families?:list<string>,text_cs?:string,text_en?:string,change_ticket?:string,sla_treatment?:string} $input */
    public function schedule(array $input, CommandContext $context): Maintenance
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new DomainError('maintenance_title_required', 'Maintenance needs a title.', 422, ['field' => 'title']);
        }
        $components = array_values(array_unique(array_map('strval', (array) ($input['components'] ?? []))));
        $known = StatusComponent::query()->whereIn('key', $components)->pluck('key')->all();
        $nodes = array_values(array_unique(array_map('strval', (array) ($input['affected_nodes'] ?? []))));
        $families = array_values(array_unique(array_map('strval', (array) ($input['affected_families'] ?? []))));
        if ($nodes !== [] && Node::query()->whereIn('id', $nodes)->count() !== count($nodes)) {
            throw new DomainError('maintenance_nodes_invalid', 'Select known nodes.', 422, ['field' => 'affected_nodes']);
        }
        if (array_diff($families, self::FAMILIES)) {
            throw new DomainError('maintenance_families_invalid', 'Select known service families.', 422, ['field' => 'affected_families', 'allowed' => self::FAMILIES]);
        }
        // a window names its status components, or the nodes / families it touches (then the components may stay empty)
        if (($components === [] && $nodes === [] && $families === []) || array_diff($components, $known)) {
            throw new DomainError('maintenance_components_invalid', 'Select known status components.', 422, ['field' => 'components']);
        }
        $starts = now()->parse((string) ($input['starts_at'] ?? ''));
        $ends = now()->parse((string) ($input['ends_at'] ?? ''));
        if ($ends <= $starts) {
            throw new DomainError('maintenance_window_invalid', 'The window must end after it starts.', 422, ['field' => 'ends_at']);
        }
        if ($starts < now()->subMinutes(5)) { // a window is announced, never drawn over something that already happened
            throw new DomainError('maintenance_in_past', 'A maintenance window cannot start in the past; an outage that already happened is an incident.', 422, ['field' => 'starts_at']);
        }
        $emergency = (bool) ($input['emergency'] ?? false);
        $lead = Maintenance::leadHours();
        if ($starts < now()->addHours($lead) && ! $emergency) {
            throw new DomainError('maintenance_lead_time', "Planned maintenance must be announced at least {$lead} h ahead (or flagged as emergency).", 422, ['field' => 'starts_at', 'lead_hours' => $lead]);
        }
        if (trim((string) ($input['rollback'] ?? '')) === '') {
            throw new DomainError('maintenance_rollback_required', 'A rollback plan is required.', 422, ['field' => 'rollback']);
        }
        $services = array_values(array_unique(array_map('strval', (array) ($input['affected_services'] ?? []))));

        $maintenance = DB::transaction(fn () => $this->withNumber(fn (string $number) => Maintenance::query()->create([
            'number' => $number, 'title' => $title, 'components' => $components, 'affected_services' => $services, 'affected_nodes' => $nodes, 'affected_families' => $families,
            'texts' => array_filter(['cs' => trim((string) ($input['text_cs'] ?? '')), 'en' => trim((string) ($input['text_en'] ?? ''))], fn (string $t) => $t !== ''),
            'starts_at' => $starts, 'ends_at' => $ends, 'impact' => $input['impact'] ?? null, 'rollback' => $input['rollback'],
            'owner_id' => $context->actorId, 'change_ticket' => $input['change_ticket'] ?? null,
            // short notice is the opposite of planned: an emergency window always counts toward the SLA (H15)
            'sla_treatment' => $emergency || ($input['sla_treatment'] ?? 'excluded') === 'counted' ? 'counted' : 'excluded', 'emergency' => $emergency,
            'state' => 'planned',
        ])));
        $this->audit->record($context, 'maintenance.schedule', 'succeeded', ['number' => $maintenance->number, 'components' => $components, 'nodes' => $nodes, 'families' => $families, 'window' => [$starts->toIso8601String(), $ends->toIso8601String()], 'emergency' => (bool) ($input['emergency'] ?? false)], 'maintenance', $maintenance->id);

        return $maintenance;
    }

    /** Four-eyes: the approver must differ from the owner. Approval publishes the announcement. */
    public function approve(Maintenance $maintenance, CommandContext $context): Maintenance
    {
        if ($maintenance->state !== 'planned') {
            throw new DomainError('maintenance_not_planned', 'Only planned maintenance can be approved.', 409);
        }
        if ($context->actorType === 'user' && $context->actorId !== null && $context->actorId === $maintenance->owner_id) {
            throw new DomainError('maintenance_self_approval', 'Maintenance must be approved by a second person.', 403, ['requirement' => 'four_eyes']);
        }
        if ($maintenance->ends_at->isPast()) {
            throw new DomainError('maintenance_window_passed', 'The window is over; what was not approved before it ended was not planned maintenance.', 409);
        }
        // the approval is the announcement: a window approved less than the lead time ahead was not announced in time and counts
        $late = now()->greaterThan($maintenance->starts_at->copy()->subHours(Maintenance::leadHours()));
        $maintenance->forceFill(['state' => 'approved', 'approved_by' => $context->actorId, 'announced_at' => now()] + ($late ? ['sla_treatment' => 'counted'] : []))->save();
        $this->audit->record($context, 'maintenance.approve', 'succeeded', ['number' => $maintenance->number, 'announced_at' => now()->toIso8601String(), 'sla' => $maintenance->excludesFromSla() ? 'excluded' : 'counted', 'late' => $late], 'maintenance', $maintenance->id);
        $this->announce($maintenance, 'maintenance.scheduled');

        return $maintenance;
    }

    /**
     * Tells the customers a window concerns: only the owners of the services it touches (by node, family or id), each organization once.
     * A window that names nothing but status components keeps the old broadcast (one message without an organization).
     */
    private function announce(Maintenance $maintenance, string $event): void
    {
        $payload = ['number' => $maintenance->number, 'title' => $maintenance->title, 'components' => $maintenance->components, 'starts_at' => $maintenance->starts_at->toIso8601String(), 'ends_at' => $maintenance->ends_at->toIso8601String(), 'impact' => $maintenance->impact, 'texts' => (array) $maintenance->texts];
        if (! $maintenance->isTargeted()) {
            $this->outbox->publish(GenericEvent::of($event, 'maintenance', $maintenance->id, $payload));

            return;
        }
        foreach (MaintenanceAudience::organizationIds($maintenance) as $organizationId) {
            $this->outbox->publish(GenericEvent::of($event, 'maintenance', $maintenance->id, $payload, $organizationId));
        }
    }

    /**
     * The reminder a day before the start (once: `reminded_at` is claimed before anything is sent). A window that was announced inside
     * its last day was told about already, so the reminder is marked and not sent.
     */
    public function remind(): int
    {
        $sent = 0;
        $due = Maintenance::query()->where('state', 'approved')->whereNull('reminded_at')->where('starts_at', '>', now())->where('starts_at', '<=', now()->addHours(self::REMINDER_HOURS))->get();
        foreach ($due as $maintenance) {
            if (Maintenance::query()->whereKey($maintenance->id)->whereNull('reminded_at')->update(['reminded_at' => now()]) !== 1) {
                continue; // another run took it
            }
            if ($maintenance->announced_at !== null && $maintenance->announced_at->greaterThan($maintenance->starts_at->copy()->subHours(self::REMINDER_HOURS))) {
                continue;
            }
            $this->announce($maintenance, 'maintenance.reminder');
            $sent++;
        }

        return $sent;
    }

    public function cancel(Maintenance $maintenance, string $reason, CommandContext $context): Maintenance
    {
        if (in_array($maintenance->state, ['completed', 'cancelled'], true)) {
            throw new DomainError('maintenance_finished', 'Maintenance is already finished.', 409);
        }
        $maintenance->forceFill(['state' => 'cancelled'])->save();
        $this->incidents->refreshComponents($maintenance->components);
        $this->audit->record($context, 'maintenance.cancel', 'succeeded', ['number' => $maintenance->number, 'reason' => $reason], 'maintenance', $maintenance->id);

        return $maintenance;
    }

    public function complete(Maintenance $maintenance, CommandContext $context, ?string $note = null): Maintenance
    {
        if (! in_array($maintenance->state, ['approved', 'in_progress'], true)) {
            throw new DomainError('maintenance_not_running', 'Only approved or running maintenance can be completed.', 409);
        }
        $maintenance->forceFill(['state' => 'completed', 'completed_at' => now(), 'started_at' => $maintenance->started_at ?? now()])->save();
        $this->incidents->refreshComponents($maintenance->components);
        $this->audit->record($context, 'maintenance.complete', 'succeeded', ['number' => $maintenance->number, 'note' => $note], 'maintenance', $maintenance->id);

        return $maintenance;
    }

    /** Scheduler: start approved windows whose time came, auto-complete windows past their end; unapproved windows are skipped and flagged. */
    public function tick(): array
    {
        $now = now();
        $started = 0;
        $completed = 0;
        $unapproved = 0;
        $reminded = $this->remind();
        foreach (Maintenance::query()->where('state', 'approved')->where('starts_at', '<=', $now)->where('ends_at', '>=', $now)->get() as $m) {
            $m->forceFill(['state' => 'in_progress', 'started_at' => $now])->save();
            $started++;
        }
        foreach (Maintenance::query()->where('state', 'in_progress')->where('ends_at', '<', $now)->get() as $m) {
            $m->forceFill(['state' => 'completed', 'completed_at' => $now])->save();
            $completed++;
        }
        foreach (Maintenance::query()->where('state', 'planned')->where('starts_at', '<=', $now)->get() as $m) {
            $unapproved++;
            $this->outbox->publish(GenericEvent::of('maintenance.unapproved', 'maintenance', $m->id, ['number' => $m->number, 'title' => $m->title]));
        }
        $this->incidents->refreshComponents();

        return ['started' => $started, 'completed' => $completed, 'unapproved' => $unapproved, 'reminded' => $reminded];
    }

    private function withNumber(callable $create): Maintenance
    {
        $year = now()->format('Y');
        for ($i = 0; $i < 5; $i++) {
            $last = (int) DB::table('maintenances')->where('number', 'like', "MNT-{$year}-%")->selectRaw('max(cast(substr(number, 10) as integer)) as n')->value('n');
            try {
                return DB::transaction(fn () => $create(sprintf('MNT-%s-%04d', $year, $last + 1 + $i))); // savepoint (PostgreSQL)
            } catch (QueryException $e) {
                if (! str_contains(strtolower($e->getMessage()), 'unique')) {
                    throw $e;
                }
            }
        }
        throw new DomainError('maintenance_number_conflict', 'Could not allocate a maintenance number.', 500);
    }
}
