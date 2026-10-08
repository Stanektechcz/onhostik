<?php

declare(strict_types=1);

namespace Onhost\Domain\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * L-15 (TASK-0145): a complaint (reklamace) about a digital service, as the complaints procedure (resources/legal/2026-10/complaints.md)
 * and § 19 of the Consumer Protection Act require it:
 *
 *  · the claim is confirmed on a durable medium at once — the date it was made, what is claimed, the deadline (mail
 *    `complaint-received`, a mandatory legal notice);
 *  · it is decided within 30 days of the day the customer made it (`complaint_due_at`; the ticket's resolution clock is never later);
 *  · the decision is confirmed again — the date, how it was handled, or why it was refused (mail `complaint-resolved`);
 *  · support is told five days before the deadline and on the day it is missed, once each (TicketService::tick).
 *
 * A ticket becomes a complaint when the customer files it under the topic `reklamace`, or when support marks it — counted from
 * the ticket's first message, the day the customer actually made the claim. Each step happens once.
 */
final class ComplaintService
{
    /** § 19 (3) ZOS: a complaint is decided within thirty days of when it was made */
    public const DAYS = 30;

    /** support is told this many days before the deadline */
    public const WARN_DAYS = 5;

    public const OUTCOMES = ['accepted', 'partially_accepted', 'rejected'];

    public function __construct(private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox) {}

    public function open(Ticket $ticket, CommandContext $context): Ticket
    {
        return DB::transaction(function () use ($ticket, $context) {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($ticket->isComplaint()) {
                return $ticket; // confirmed once
            }
            $received = CarbonImmutable::make($ticket->created_at) ?? CarbonImmutable::now();
            $due = $received->addDays(self::DAYS);
            $current = CarbonImmutable::make($ticket->resolution_due_at);
            $resolutionDue = $current === null || $current->greaterThan($due) ? $due : $current;
            $ticket->forceFill(['complaint_received_at' => $received, 'complaint_due_at' => $due, 'resolution_due_at' => $resolutionDue,
                'tags' => array_values(array_unique(array_merge((array) $ticket->tags, [Triage::COMPLAINT_TOPIC])))])->save();
            $this->audit->record($context->withScope($ticket->organization_id), 'ticket.complaint.open', 'succeeded', ['number' => $ticket->number, 'received_at' => $received->toIso8601String(), 'due_at' => $due->toIso8601String()], 'ticket', $ticket->id);
            $this->outbox->publish(GenericEvent::of('ticket.complaint.received', 'ticket', $ticket->id, ['number' => $ticket->number, 'subject' => $ticket->subject, 'email' => $ticket->email,
                'received_at' => $received->toIso8601String(), 'due_at' => $due->toIso8601String()], $ticket->organization_id));

            return $ticket;
        }, 3);
    }

    public function resolve(Ticket $ticket, string $outcome, string $resolution, CommandContext $context): Ticket
    {
        if (! in_array($outcome, self::OUTCOMES, true)) {
            throw new DomainError('complaint_outcome_unknown', 'Name how the complaint was decided.', 422, ['field' => 'outcome', 'offered' => self::OUTCOMES]);
        }
        $resolution = trim($resolution);
        if (mb_strlen($resolution) < 10) {
            throw new DomainError('complaint_resolution_required', 'Say how the complaint was handled, or why it was refused.', 422, ['field' => 'resolution']);
        }

        return DB::transaction(function () use ($ticket, $outcome, $resolution, $context) {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if (! $ticket->isComplaint()) {
                throw new DomainError('ticket_not_a_complaint', 'This ticket is not a complaint; mark it as one first.', 409);
            }
            if ($ticket->complaint_resolved_at !== null) {
                throw new DomainError('complaint_already_resolved', 'The complaint was decided already; its confirmation went out.', 409, ['resolved_at' => self::iso($ticket->complaint_resolved_at)]);
            }
            $at = CarbonImmutable::now();
            $ticket->forceFill(['complaint_resolved_at' => $at, 'complaint_outcome' => $outcome, 'meta' => array_merge((array) $ticket->meta, ['complaint' => ['resolution' => mb_substr($resolution, 0, 4000), 'resolved_by' => $context->actorId]])])->save();
            $this->audit->record($context->withScope($ticket->organization_id), 'ticket.complaint.resolve', 'succeeded', ['number' => $ticket->number, 'outcome' => $outcome, 'late' => $at->greaterThan(CarbonImmutable::make($ticket->complaint_due_at) ?? $at)], 'ticket', $ticket->id);
            $this->outbox->publish(GenericEvent::of('ticket.complaint.resolved', 'ticket', $ticket->id, ['number' => $ticket->number, 'subject' => $ticket->subject, 'email' => $ticket->email,
                'received_at' => self::iso($ticket->complaint_received_at), 'resolved_at' => $at->toIso8601String(), 'outcome' => $outcome, 'resolution' => mb_substr($resolution, 0, 4000)], $ticket->organization_id));

            return $ticket;
        }, 3);
    }

    /**
     * Open complaints whose deadline comes within WARN_DAYS (told once) or has passed (told once). Called by TicketService::tick.
     *
     * @return array{due_soon:int, overdue:int}
     */
    public function watch(): array
    {
        $out = ['due_soon' => 0, 'overdue' => 0];
        $open = Ticket::query()->whereNotNull('complaint_due_at')->whereNull('complaint_resolved_at')->where('complaint_due_at', '<=', now()->addDays(self::WARN_DAYS))->get();
        foreach ($open as $ticket) {
            $kind = (CarbonImmutable::make($ticket->complaint_due_at) ?? CarbonImmutable::now())->isPast() ? 'overdue' : 'due_soon';
            if (data_get($ticket->meta, "complaint.told.{$kind}") !== null) {
                continue;
            }
            $ticket->forceFill(['meta' => array_replace_recursive((array) $ticket->meta, ['complaint' => ['told' => [$kind => now()->toIso8601String()]]])])->save();
            $this->outbox->publish(GenericEvent::of("ticket.complaint.{$kind}", 'ticket', $ticket->id, ['number' => $ticket->number, 'subject' => $ticket->subject, 'due_at' => self::iso($ticket->complaint_due_at)], $ticket->organization_id));
            $out[$kind]++;
        }

        return $out;
    }

    /** @return array{received_at:?string, due_at:?string, resolved_at:?string, outcome:?string}|null */
    public static function present(Ticket $ticket): ?array
    {
        return ! $ticket->isComplaint() ? null : ['received_at' => self::iso($ticket->complaint_received_at), 'due_at' => self::iso($ticket->complaint_due_at),
            'resolved_at' => self::iso($ticket->complaint_resolved_at), 'outcome' => $ticket->complaint_outcome === null ? null : (string) $ticket->complaint_outcome];
    }

    private static function iso(mixed $value): ?string
    {
        return CarbonImmutable::make($value)?->toIso8601String();
    }
}
