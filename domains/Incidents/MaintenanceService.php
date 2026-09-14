<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Incidents\Models\Maintenance;
use Onhost\Domain\Incidents\Models\StatusComponent;
use Onhost\Domain\Services\Models\Service;
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

    /** @param array{title:string,components:list<string>,starts_at:string,ends_at:string,impact?:string,rollback?:string,affected_services?:list<string>,change_ticket?:string,sla_treatment?:string} $input */
    public function schedule(array $input, CommandContext $context): Maintenance
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new DomainError('maintenance_title_required', 'Maintenance needs a title.', 422, ['field' => 'title']);
        }
        $components = array_values(array_unique(array_map('strval', (array) ($input['components'] ?? []))));
        $known = StatusComponent::query()->whereIn('key', $components)->pluck('key')->all();
        if ($components === [] || array_diff($components, $known)) {
            throw new DomainError('maintenance_components_invalid', 'Select known status components.', 422, ['field' => 'components']);
        }
        $starts = now()->parse((string) ($input['starts_at'] ?? ''));
        $ends = now()->parse((string) ($input['ends_at'] ?? ''));
        if ($ends <= $starts) {
            throw new DomainError('maintenance_window_invalid', 'The window must end after it starts.', 422, ['field' => 'ends_at']);
        }
        $lead = (int) config('onhost.status.maintenance_lead_hours', 48);
        if ($starts < now()->addHours($lead) && ! ($input['emergency'] ?? false)) {
            throw new DomainError('maintenance_lead_time', "Planned maintenance must be announced at least {$lead} h ahead (or flagged as emergency).", 422, ['field' => 'starts_at', 'lead_hours' => $lead]);
        }
        if (trim((string) ($input['rollback'] ?? '')) === '') {
            throw new DomainError('maintenance_rollback_required', 'A rollback plan is required.', 422, ['field' => 'rollback']);
        }
        $services = array_values(array_unique(array_map('strval', (array) ($input['affected_services'] ?? []))));

        $maintenance = DB::transaction(fn () => $this->withNumber(fn (string $number) => Maintenance::query()->create([
            'number' => $number, 'title' => $title, 'components' => $components, 'affected_services' => $services,
            'starts_at' => $starts, 'ends_at' => $ends, 'impact' => $input['impact'] ?? null, 'rollback' => $input['rollback'],
            'owner_id' => $context->actorId, 'change_ticket' => $input['change_ticket'] ?? null,
            'sla_treatment' => ($input['sla_treatment'] ?? 'excluded') === 'counted' ? 'counted' : 'excluded',
            'state' => 'planned',
        ])));
        $this->audit->record($context, 'maintenance.schedule', 'succeeded', ['number' => $maintenance->number, 'components' => $components, 'window' => [$starts->toIso8601String(), $ends->toIso8601String()], 'emergency' => (bool) ($input['emergency'] ?? false)], 'maintenance', $maintenance->id);

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
        $maintenance->forceFill(['state' => 'approved', 'approved_by' => $context->actorId])->save();
        $this->audit->record($context, 'maintenance.approve', 'succeeded', ['number' => $maintenance->number], 'maintenance', $maintenance->id);
        $payload = ['number' => $maintenance->number, 'title' => $maintenance->title, 'components' => $maintenance->components, 'starts_at' => $maintenance->starts_at->toIso8601String(), 'ends_at' => $maintenance->ends_at->toIso8601String(), 'impact' => $maintenance->impact];
        $organizations = $maintenance->affected_services ? Service::query()->whereIn('id', $maintenance->affected_services)->pluck('organization_id')->unique() : collect();
        if ($organizations->isEmpty()) {
            $this->outbox->publish(GenericEvent::of('maintenance.scheduled', 'maintenance', $maintenance->id, $payload));
        }
        foreach ($organizations as $organizationId) {
            $this->outbox->publish(GenericEvent::of('maintenance.scheduled', 'maintenance', $maintenance->id, $payload, $organizationId));
        }

        return $maintenance;
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

        return ['started' => $started, 'completed' => $completed, 'unapproved' => $unapproved];
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
