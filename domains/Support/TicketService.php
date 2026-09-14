<?php

declare(strict_types=1);

namespace Onhost\Domain\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Support\Models\SlaEvent;
use Onhost\Domain\Support\Models\SlaPolicy;
use Onhost\Domain\Support\Models\SupportQueue;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\Models\TicketMessage;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Ticket lifecycle with separate support clocks (blueprint §68): first response,
 * next response, resolution; customer-wait pauses the clocks; breaches escalate.
 */
final class TicketService
{
    public const REOPEN_WINDOW_DAYS = 14;

    public const AUTO_CLOSE_DAYS = 7;

    public function __construct(private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox) {}

    /**
     * @param  array{subject:string, body:string, category?:?string, priority?:?string, service_id?:?string, domain_id?:?string, channel?:string, email?:?string, name?:?string, tags?:list<string>, incident_id?:?string, attachments?:list<array<string,mixed>>}  $input
     */
    public function create(array $input, CommandContext $context, ?Organization $organization = null, ?User $user = null, bool $staff = false): Ticket
    {
        $subject = trim((string) ($input['subject'] ?? ''));
        $body = trim((string) ($input['body'] ?? ''));
        if ($subject === '' || $body === '') {
            throw new DomainError('ticket_incomplete', 'Předmět i popis požadavku jsou povinné.', 422, ['field' => $subject === '' ? 'subject' : 'body']);
        }
        $email = strtolower(trim((string) ($input['email'] ?? $user?->email ?? $organization?->billing_email ?? '')));
        if ($email === '') {
            throw new DomainError('ticket_email_required', 'Pro tiket potřebujeme kontaktní e-mail.', 422, ['field' => 'email']);
        }
        $service = isset($input['service_id']) ? Service::query()->withTrashed()->find($input['service_id']) : null;
        if ($service !== null && $organization !== null && $service->organization_id !== $organization->id) {
            throw DomainError::notFound('service');
        }
        $triage = Triage::classify($subject, $body);
        $topic = (string) ($input['category'] ?? $triage['topic']);
        if (! isset(Triage::TOPICS[$topic]) && $topic !== 'ostatni') {
            $topic = $triage['topic'];
        }
        $contractual = $organization !== null && Service::query()->where('organization_id', $organization->id)->whereIn('sla_class', ['business', 'ha', 'critical'])->exists();
        $priority = Triage::priority($input['priority'] ?? null, $topic, $staff, $contractual, $subject.' '.$body);
        $policy = $this->policyFor($organization);
        $targets = $policy?->targetsFor($priority) ?? ['ack' => 60, 'first' => 240, 'next' => 480, 'resolve' => 4320];
        $queue = SupportQueue::query()->where('key', Triage::queueFor($topic))->first() ?? SupportQueue::query()->where('key', 'l1')->first();

        $ticket = $this->withNumber(fn (string $number) => Ticket::query()->create([
            'number' => $number, 'organization_id' => $organization?->id, 'user_id' => $user?->id, 'email' => $email, 'name' => $input['name'] ?? $user?->name ?? $organization?->name, 'subject' => mb_substr($subject, 0, 250),
            'category' => $topic, 'priority' => $priority, 'state' => TicketStateMachine::TRIAGED, 'channel' => (string) ($input['channel'] ?? 'portal'), 'queue_id' => $queue?->id, 'service_id' => $service?->id, 'domain_id' => $input['domain_id'] ?? null, 'incident_id' => $input['incident_id'] ?? null,
            'required_skills' => Triage::skillsFor($topic), 'tags' => array_values((array) ($input['tags'] ?? [])), 'sla_policy_id' => $policy?->id,
            'first_response_due_at' => now()->addMinutes($targets['first']), 'resolution_due_at' => now()->addMinutes($targets['resolve']), 'last_customer_message_at' => now(),
            'meta' => ['triage' => $triage, 'requested_priority' => $input['priority'] ?? null, 'targets' => $targets],
        ]));
        TicketMessage::query()->create(['ticket_id' => $ticket->id, 'author_type' => $staff ? 'staff' : 'customer', 'author_id' => $user?->id, 'author_name' => $ticket->name, 'visibility' => 'public', 'body' => $body, 'attachments' => $this->attachments($input['attachments'] ?? [])]);
        $this->audit->record($context->withScope($organization?->id), 'ticket.create', 'succeeded', ['number' => $ticket->number, 'category' => $topic, 'priority' => $priority, 'queue' => $queue?->key], 'ticket', $ticket->id);
        $this->outbox->publish(GenericEvent::of('ticket.created', 'ticket', $ticket->id, ['number' => $ticket->number, 'subject' => $ticket->subject, 'email' => $email, 'name' => $ticket->name, 'priority' => $priority, 'category' => $topic, 'queue' => $queue?->key, 'first_response_minutes' => $targets['first'], 'channel' => $ticket->channel], $organization?->id));

        return $ticket;
    }

    /** @param 'customer'|'staff'|'ai'|'system' $authorType */
    public function reply(Ticket $ticket, string $authorType, ?string $authorId, ?string $authorName, string $body, CommandContext $context, string $visibility = 'public', array $attachments = []): TicketMessage
    {
        $body = trim($body);
        if ($body === '') {
            throw new DomainError('ticket_message_empty', 'Zpráva nesmí být prázdná.', 422, ['field' => 'body']);
        }
        if ($ticket->state === TicketStateMachine::CLOSED && $authorType === 'customer') {
            if ($ticket->closed_at !== null && $ticket->closed_at->diffInDays(now()) > self::REOPEN_WINDOW_DAYS) {
                throw new DomainError('ticket_closed', 'Tiket je uzavřený déle než 14 dní; založte prosím nový.', 409);
            }
        }
        $message = TicketMessage::query()->create(['ticket_id' => $ticket->id, 'author_type' => $authorType, 'author_id' => $authorId, 'author_name' => $authorName, 'visibility' => $visibility, 'body' => $body, 'attachments' => $this->attachments($attachments)]);

        if ($visibility === 'internal') {
            $this->audit->record($context->withScope($ticket->organization_id), 'ticket.note', 'succeeded', ['number' => $ticket->number], 'ticket', $ticket->id);

            return $message;
        }
        if ($authorType === 'customer') {
            $this->resumeClocks($ticket);
            $targets = (array) data_get($ticket->meta, 'targets', []);
            $patch = ['last_customer_message_at' => now(), 'next_response_due_at' => now()->addMinutes((int) ($targets['next'] ?? 480))];
            if (! $ticket->isOpen()) {
                $patch += ['state' => TicketStateMachine::OPEN, 'reopen_count' => $ticket->reopen_count + 1, 'resolved_at' => null, 'closed_at' => null, 'resolution_due_at' => now()->addMinutes((int) ($targets['resolve'] ?? 4320))];
            } elseif (in_array($ticket->state, [TicketStateMachine::WAITING_CUSTOMER, TicketStateMachine::TRIAGED, TicketStateMachine::NEW], true)) {
                $patch['state'] = TicketStateMachine::OPEN;
            }
            $ticket->forceFill($patch)->save();
        } else {
            $patch = ['last_staff_message_at' => now(), 'next_response_due_at' => null];
            if ($ticket->first_responded_at === null) {
                $patch['first_responded_at'] = now();
                $this->measure($ticket, 'first_response', $ticket->first_response_due_at);
            } elseif ($ticket->next_response_due_at !== null) {
                $this->measure($ticket, 'next_response', $ticket->next_response_due_at);
            }
            if ($ticket->isOpen()) {
                $patch['state'] = TicketStateMachine::WAITING_CUSTOMER;
                $patch['waiting_since'] = now();
            }
            $ticket->forceFill($patch)->save();
        }
        $this->audit->record($context->withScope($ticket->organization_id), 'ticket.reply', 'succeeded', ['number' => $ticket->number, 'author_type' => $authorType, 'state' => $ticket->state], 'ticket', $ticket->id);
        $this->outbox->publish(GenericEvent::of('ticket.replied', 'ticket', $ticket->id, ['number' => $ticket->number, 'subject' => $ticket->subject, 'email' => $ticket->email, 'author_type' => $authorType, 'state' => $ticket->state, 'excerpt' => mb_substr($body, 0, 300)], $ticket->organization_id));

        return $message;
    }

    public function transition(Ticket $ticket, string $to, CommandContext $context, ?string $note = null): Ticket
    {
        TicketStateMachine::machine()->assertTransition($ticket->state, $to);
        $from = $ticket->state;
        $patch = ['state' => $to];
        if ($to === TicketStateMachine::WAITING_CUSTOMER) {
            $patch['waiting_since'] = now();
        }
        if ($from === TicketStateMachine::WAITING_CUSTOMER && $to !== TicketStateMachine::CLOSED) {
            $this->resumeClocks($ticket);
        }
        if ($to === TicketStateMachine::RESOLVED) {
            $patch['resolved_at'] = now();
            $this->measure($ticket, 'resolution', $ticket->resolution_due_at);
        }
        if ($to === TicketStateMachine::CLOSED) {
            $patch['closed_at'] = now();
        }
        if ($to === TicketStateMachine::OPEN && in_array($from, [TicketStateMachine::RESOLVED, TicketStateMachine::CLOSED], true)) {
            $patch['reopen_count'] = $ticket->reopen_count + 1;
            $patch['resolved_at'] = null;
            $patch['closed_at'] = null;
        }
        $ticket->forceFill($patch)->save();
        if ($note !== null && trim($note) !== '') {
            TicketMessage::query()->create(['ticket_id' => $ticket->id, 'author_type' => 'system', 'author_name' => 'ONhost', 'visibility' => 'public', 'body' => $note]);
        }
        $this->audit->record($context->withScope($ticket->organization_id), 'ticket.transition', 'succeeded', ['number' => $ticket->number, 'from' => $from, 'to' => $to, 'note' => $note], 'ticket', $ticket->id);
        $this->outbox->publish(GenericEvent::of('ticket.'.strtolower($to), 'ticket', $ticket->id, ['number' => $ticket->number, 'subject' => $ticket->subject, 'email' => $ticket->email, 'from' => $from, 'note' => $note], $ticket->organization_id));

        return $ticket;
    }

    public function assign(Ticket $ticket, ?string $assigneeId, CommandContext $context): Ticket
    {
        $ticket->forceFill(['assignee_id' => $assigneeId, 'state' => $ticket->state === TicketStateMachine::TRIAGED || $ticket->state === TicketStateMachine::NEW ? TicketStateMachine::OPEN : $ticket->state])->save();
        $this->audit->record($context->withScope($ticket->organization_id), 'ticket.assign', 'succeeded', ['number' => $ticket->number, 'assignee' => $assigneeId], 'ticket', $ticket->id);

        return $ticket;
    }

    public function escalate(Ticket $ticket, CommandContext $context, string $reason): Ticket
    {
        $queue = $ticket->queue_id ? SupportQueue::query()->find($ticket->queue_id) : null;
        $next = $queue?->escalates_to ? SupportQueue::query()->where('key', $queue->escalates_to)->first() : null;
        $ticket->forceFill(['escalation_level' => $ticket->escalation_level + 1, 'queue_id' => $next?->id ?? $ticket->queue_id, 'assignee_id' => null])->save();
        if ($ticket->state !== TicketStateMachine::ESCALATED && TicketStateMachine::machine()->canTransition($ticket->state, TicketStateMachine::ESCALATED)) {
            $ticket->forceFill(['state' => TicketStateMachine::ESCALATED])->save();
        }
        TicketMessage::query()->create(['ticket_id' => $ticket->id, 'author_type' => 'system', 'author_name' => 'ONhost', 'visibility' => 'internal', 'body' => "Eskalace na úroveň {$ticket->escalation_level}".($next ? " ({$next->name})" : '').": {$reason}"]);
        $this->audit->record($context->withScope($ticket->organization_id), 'ticket.escalate', 'succeeded', ['number' => $ticket->number, 'level' => $ticket->escalation_level, 'reason' => $reason], 'ticket', $ticket->id);
        $this->outbox->publish(GenericEvent::of('ticket.escalated', 'ticket', $ticket->id, ['number' => $ticket->number, 'level' => $ticket->escalation_level, 'reason' => $reason, 'queue' => $next?->key], $ticket->organization_id));

        return $ticket;
    }

    public function rate(Ticket $ticket, int $score, ?string $comment, CommandContext $context): Ticket
    {
        if ($ticket->isOpen()) {
            throw new DomainError('ticket_not_resolved', 'Hodnotit lze až vyřešený tiket.', 409);
        }
        if ($score < 1 || $score > 5) {
            throw new DomainError('csat_invalid', 'Hodnocení je 1–5.', 422, ['field' => 'score']);
        }
        $ticket->forceFill(['csat_score' => $score, 'csat_comment' => $comment ? mb_substr($comment, 0, 500) : null])->save();
        $this->audit->record($context->withScope($ticket->organization_id), 'ticket.csat', 'succeeded', ['number' => $ticket->number, 'score' => $score], 'ticket', $ticket->id);

        return $ticket;
    }

    /** Scheduler: breaches (escalate + alert) and auto-close of resolved tickets. @return array{breached:int, closed:int} */
    public function tick(?CommandContext $context = null): array
    {
        $context ??= CommandContext::system('support sla');
        $breached = 0;
        $open = Ticket::query()->whereNotIn('state', [TicketStateMachine::RESOLVED, TicketStateMachine::CLOSED])->get();
        foreach ($open as $ticket) {
            foreach (['first_response' => $ticket->first_responded_at === null ? $ticket->first_response_due_at : null, 'next_response' => $ticket->next_response_due_at, 'resolution' => $ticket->resolution_due_at] as $kind => $due) {
                if ($due === null || $ticket->state === TicketStateMachine::WAITING_CUSTOMER || $due > now()) {
                    continue;
                }
                if (SlaEvent::query()->where('ticket_id', $ticket->id)->where('kind', $kind)->where('met', false)->exists()) {
                    continue;
                }
                SlaEvent::query()->create(['ticket_id' => $ticket->id, 'kind' => $kind, 'due_at' => $due, 'met' => false, 'measured_at' => now(), 'delta_minutes' => (int) $due->diffInMinutes(now())]);
                $this->outbox->publish(GenericEvent::of('ticket.sla_breached', 'ticket', $ticket->id, ['number' => $ticket->number, 'kind' => $kind, 'priority' => $ticket->priority, 'due_at' => $due->toIso8601String()], $ticket->organization_id));
                $breached++;
                if ($kind !== 'resolution' || $ticket->escalation_level === 0) {
                    $this->escalate($ticket, $context, "SLA {$kind} breached");
                }
            }
        }
        $closed = 0;
        foreach (Ticket::query()->where('state', TicketStateMachine::RESOLVED)->where('resolved_at', '<', now()->subDays(self::AUTO_CLOSE_DAYS))->get() as $ticket) {
            $this->transition($ticket, TicketStateMachine::CLOSED, $context, 'Tiket byl automaticky uzavřen 7 dní po vyřešení.');
            $closed++;
        }

        return ['breached' => $breached, 'closed' => $closed];
    }

    /** Topic clusters for the admin queue (mirrors the prototype's `clusters()`). @return list<array<string,mixed>> */
    public function clusters(): array
    {
        $tickets = Ticket::query()->get();
        $total = max(1, $tickets->count());
        $out = [];
        foreach ($tickets->groupBy(fn (Ticket $t) => $t->category ?? 'ostatni') as $topic => $group) {
            $open = $group->filter(fn (Ticket $t) => $t->uiState() === 'otevreny')->count();
            $ages = $group->filter(fn (Ticket $t) => $t->isOpen())->map(fn (Ticket $t) => $t->created_at?->diffInHours(now()) ?? 0);
            $customers = $group->groupBy(fn (Ticket $t) => $t->organization_id ?? $t->email)->map->count()->sortDesc();
            $out[] = [
                'id' => $topic, 'label' => Triage::TOPICS[$topic]['label'] ?? 'Ostatní', 'count' => $group->count(), 'open' => $open, 'waiting' => $group->filter(fn (Ticket $t) => $t->uiState() === 'ceka')->count(), 'solved' => $group->filter(fn (Ticket $t) => $t->uiState() === 'vyreseny')->count(),
                'high' => $group->filter(fn (Ticket $t) => $t->uiPriority() === 'vysoka')->count(), 'avgAge' => $ages->count() ? round($ages->avg(), 1) : 0, 'share' => round($group->count() / $total * 100), 'topCustomer' => $customers->keys()->first(), 'topCustomerCount' => $customers->first() ?? 0,
                'ids' => $group->pluck('number')->all(), 'last' => $group->max('created_at')?->toIso8601String(),
            ];
        }
        usort($out, fn ($a, $b) => $b['open'] <=> $a['open'] ?: $b['count'] <=> $a['count']);

        return $out;
    }

    private function policyFor(?Organization $organization): ?SlaPolicy
    {
        $class = 'standard';
        if ($organization !== null) {
            $classes = Service::query()->where('organization_id', $organization->id)->pluck('sla_class')->unique()->all();
            $class = in_array('ha', $classes, true) || in_array('critical', $classes, true) ? 'ha' : (in_array('business', $classes, true) ? 'business' : 'standard');
        }

        return SlaPolicy::query()->where('key', $class)->first() ?? SlaPolicy::query()->where('key', 'standard')->first();
    }

    private function measure(Ticket $ticket, string $kind, ?\DateTimeInterface $due): void
    {
        if ($due === null) {
            return;
        }
        $dueAt = Carbon::instance($due)->addMinutes($ticket->paused_minutes);
        SlaEvent::query()->create(['ticket_id' => $ticket->id, 'kind' => $kind, 'due_at' => $dueAt, 'met' => now() <= $dueAt, 'measured_at' => now(), 'delta_minutes' => (int) now()->diffInMinutes($dueAt, false) * -1]);
    }

    /** Customer replied after waiting: the time waited does not count against the clocks. */
    private function resumeClocks(Ticket $ticket): void
    {
        if ($ticket->waiting_since === null) {
            return;
        }
        $paused = (int) $ticket->waiting_since->diffInMinutes(now());
        $ticket->forceFill([
            'paused_minutes' => $ticket->paused_minutes + $paused, 'waiting_since' => null,
            'resolution_due_at' => $ticket->resolution_due_at?->copy()->addMinutes($paused), 'first_response_due_at' => $ticket->first_responded_at === null ? $ticket->first_response_due_at?->copy()->addMinutes($paused) : $ticket->first_response_due_at,
        ])->save();
    }

    private function attachments(array $attachments): array
    {
        $out = [];
        foreach ($attachments as $a) {
            if (! is_array($a) || empty($a['name'])) {
                continue;
            }
            $mime = (string) ($a['mime'] ?? 'application/octet-stream');
            if (preg_match('#^(application/(x-msdownload|x-sh|javascript)|text/x-php)#', $mime)) {
                throw new DomainError('attachment_type_forbidden', "Příloha {$a['name']} má nepovolený typ.", 422, ['field' => 'attachments']);
            }
            if ((int) ($a['size'] ?? 0) > 25 * 1024 * 1024) {
                throw new DomainError('attachment_too_large', "Příloha {$a['name']} přesahuje 25 MB.", 422, ['field' => 'attachments']);
            }
            $out[] = ['name' => mb_substr((string) $a['name'], 0, 190), 'size' => (int) ($a['size'] ?? 0), 'mime' => $mime, 'path' => $a['path'] ?? null, 'sha256' => $a['sha256'] ?? null, 'scanned' => (bool) ($a['scanned'] ?? false)];
        }

        return $out;
    }

    /** TK-YYYY-NNNN with a retry on the unique index (no sequence table needed). */
    private function withNumber(callable $create): Ticket
    {
        $year = now()->format('Y');
        for ($i = 0; $i < 5; $i++) {
            $last = (int) DB::table('support_tickets')->where('number', 'like', "TK-{$year}-%")->selectRaw('max(cast(substr(number, 9) as integer)) as n')->value('n');
            $number = sprintf('TK-%s-%04d', $year, $last + 1 + $i);
            try {
                return DB::transaction(fn () => $create($number)); // savepoint (PostgreSQL)
            } catch (QueryException $e) {
                if (! str_contains(strtolower($e->getMessage()), 'unique')) {
                    throw $e;
                }
            }
        }
        throw new DomainError('ticket_number_conflict', 'Could not allocate a ticket number.', 500);
    }
}
