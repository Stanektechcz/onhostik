<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Controllers\Api\V1\SupportController as CustomerSupportController;
use App\Http\StaffReadAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Support\Assistant\AssistantService;
use Onhost\Domain\Support\Assistant\TicketReplyDrafter;
use Onhost\Domain\Support\Commands\TicketStaffCommand;
use Onhost\Domain\Support\Commands\WorkOfferStaffCommand;
use Onhost\Domain\Support\ComplaintService;
use Onhost\Domain\Support\Models\SupportMacro;
use Onhost\Domain\Support\Models\SupportQueue;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\Models\TicketMessage;
use Onhost\Domain\Support\Models\WorkOffer;
use Onhost\Domain\Support\TicketService;
use Onhost\Domain\Support\TicketStateMachine;
use Onhost\Domain\Support\Triage;
use Onhost\Domain\Support\WorkOfferService;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/** Admin `#/fronta`, `#/tiket/{id}`: queue, replies with internal notes, transitions, assignment, escalation, clusters, macros. */
final class SupportController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'staff.support.ticket.read', CommandScope::global()); // the staff key, not the customer's at global scope (TASK-0037, program IF-18)
        $query = Ticket::query();
        foreach (['state', 'priority', 'category', 'queue_id', 'assignee_id', 'organization_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, (string) $request->query($filter));
            }
        }
        if ($request->boolean('open')) {
            $query->whereNotIn('state', ['RESOLVED', 'CLOSED']);
        }
        if ($request->filled('q')) {
            $q = '%'.strtolower((string) $request->query('q')).'%';
            $query->where(fn ($w) => $w->whereRaw('lower(subject) like ?', [$q])->orWhereRaw('lower(email) like ?', [$q])->orWhere('number', 'like', strtoupper((string) $request->query('q')).'%'));
        }
        $queues = SupportQueue::query()->pluck('key', 'id');

        return $this->api->paginate($request, $query, fn (Ticket $t) => CustomerSupportController::ticket($t) + ['organization_id' => $t->organization_id, 'queue' => $queues[$t->queue_id] ?? null, 'assignee_id' => $t->assignee_id, 'required_skills' => $t->required_skills, 'sla' => ['next_response_due_at' => $t->next_response_due_at?->toIso8601String(), 'paused_minutes' => $t->paused_minutes, 'breaches' => $t->slaEvents()->where('met', false)->count()]]);
    }

    /**
     * The assistant for support and NOC, over ONE customer's account: what the customer-360 view shows, in plain language,
     * with service actions offered as buttons the console sends through the staff path. Every question is audited with
     * the organization it was asked about (`assistant.chat`).
     */
    public function assistant(Request $request, AssistantService $assistant): JsonResponse
    {
        $this->api->authorize($request, 'staff.customer.read', CommandScope::global());
        $data = $request->validate(['text' => ['required', 'string', 'max:4000'], 'organization_id' => ['required', 'string', 'max:40'], 'session_id' => ['nullable', 'string', 'max:80'], 'locale' => ['nullable', 'in:cs,en']]);
        $organization = Organization::query()->find($data['organization_id']) ?? throw DomainError::notFound('organization');

        return response()->json(['data' => $assistant->chatAsStaff($data['text'], $organization, $this->api->user($request), $data['session_id'] ?? null, $this->api->context($request, $organization), $data['locale'] ?? 'cs')]);
    }

    public function show(Request $request, AssistantService $assistant, string $ticket): JsonResponse
    {
        $this->api->authorize($request, 'staff.support.ticket.read', CommandScope::global()); // the staff key, not the customer's at global scope (TASK-0037, program IF-18)
        $model = $this->find($ticket);
        app(StaffReadAudit::class)->record($request, $this->api->context($request), 'ticket', $model->organization_id, 'ticket', $model->id, ['number' => $model->number]);
        $messages = $model->messages()->get()->map(fn (TicketMessage $m) => ['id' => $m->id, 'from' => $m->uiFrom(), 'author_type' => $m->author_type, 'author_name' => $m->author_name, 'visibility' => $m->visibility, 'text' => $m->body, 'attachments' => $m->attachments ?? [], 'at' => $m->created_at?->toIso8601String()])->all();

        return response()->json(['data' => CustomerSupportController::ticket($model) + ['organization_id' => $model->organization_id, 'assignee_id' => $model->assignee_id, 'queue_id' => $model->queue_id, 'required_skills' => $model->required_skills, 'messages' => $messages, 'sla_events' => $model->slaEvents()->get()->map(fn ($e) => ['kind' => $e->kind, 'due_at' => $e->due_at?->toIso8601String(), 'met' => $e->met, 'delta_minutes' => $e->delta_minutes])->all(), 'ai' => $assistant->transcriptForTicket($model), 'meta' => $model->meta]]);
    }

    /**
     * A reply drafted for the agent from what the platform knows — the conversation, the account facts, the health check
     * of the ticket's service. Nothing is sent: the agent reads, edits and posts it through the ordinary reply route.
     */
    public function draft(Request $request, TicketReplyDrafter $drafter, string $ticket): JsonResponse
    {
        $this->api->authorize($request, 'support.ticket.manage', CommandScope::global());
        $data = $request->validate(['hint' => ['nullable', 'string', 'max:500'], 'locale' => ['nullable', 'in:cs,en']]);

        return response()->json(['data' => $drafter->draft($this->find($ticket), $this->api->user($request), $this->api->context($request), $data['locale'] ?? 'cs', $data['hint'] ?? null)]);
    }

    /** A public answer (`visibility: internal` keeps it among staff), optionally prefixed by a macro whose state action follows. */
    public function reply(Request $request, string $ticket): JsonResponse
    {
        $this->api->authorize($request, 'support.ticket.manage', CommandScope::global()); // asked again by the bus
        $data = $request->validate(['body' => ['required', 'string', 'max:20000'], 'visibility' => ['nullable', 'in:public,internal'], 'macro' => ['nullable', 'string', 'max:60']]);

        return $this->onTicket($request, $ticket, 'reply', ['body' => $data['body'], 'visibility' => $data['visibility'] ?? 'public', 'macro' => $data['macro'] ?? null], true);
    }

    /** An internal note: never shown to the customer, no clock is touched, nothing is published. */
    public function note(Request $request, string $ticket): JsonResponse
    {
        $this->api->authorize($request, 'support.ticket.manage', CommandScope::global()); // asked again by the bus
        $data = $request->validate(['body' => ['required', 'string', 'max:20000']]);

        return $this->onTicket($request, $ticket, 'note', ['body' => $data['body']], true);
    }

    /**
     * Move the ticket to another state. The console sends `{state}`, the API documented `{to}`: both are read (`to` wins), so
     * closing and reopening from the console stopped answering 422 (readiness audit 2026-10, P1-2). The note is internal
     * unless `note_visibility: public`.
     */
    public function transition(Request $request, string $ticket): JsonResponse
    {
        $this->api->authorize($request, 'support.ticket.manage', CommandScope::global()); // asked again by the bus
        $states = implode(',', array_keys(TicketStateMachine::machine()->toArray()));
        $request->merge(array_map(fn ($v) => is_string($v) ? strtoupper($v) : $v, $request->only(['to', 'state'])));
        $data = $request->validate(['to' => ['required_without:state', 'nullable', 'string', 'in:'.$states], 'state' => ['required_without:to', 'nullable', 'string', 'in:'.$states], 'note' => ['nullable', 'string', 'max:2000'], 'note_visibility' => ['nullable', 'in:public,internal']]);

        return $this->onTicket($request, $ticket, 'transition', ['to' => $data['to'] ?? $data['state'], 'note' => $data['note'] ?? null, 'note_visibility' => $data['note_visibility'] ?? 'internal']);
    }

    /** Owner, priority and queue (support.ticket.assign). `assignee_id: null` takes the ticket from its owner; an assignee must be active support staff (422). */
    public function assign(Request $request, string $ticket): JsonResponse
    {
        $this->api->authorize($request, 'support.ticket.assign', CommandScope::global()); // asked again by the bus
        $data = $request->validate(['assignee_id' => ['nullable', 'string', 'max:40'], 'priority' => ['nullable', 'in:p1,p2,p3,p4'], 'queue' => ['nullable', 'string', 'max:40']]);
        $payload = ['priority' => $data['priority'] ?? null, 'queue' => $data['queue'] ?? null];
        if ($request->exists('assignee_id')) {
            $payload['assignee_id'] = $data['assignee_id'] ?? null;
        }

        return $this->onTicket($request, $ticket, 'assign', $payload);
    }

    public function escalate(Request $request, string $ticket): JsonResponse
    {
        $this->api->authorize($request, 'support.ticket.manage', CommandScope::global()); // asked again by the bus
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:250']]);

        return $this->onTicket($request, $ticket, 'escalate', ['reason' => $data['reason']]);
    }

    /** L-15: the ticket is a complaint — confirmed to the customer at once, decided within 30 days of their claim. */
    public function complaint(Request $request, string $ticket): JsonResponse
    {
        $this->api->authorize($request, 'support.ticket.manage', CommandScope::global()); // asked again by the bus

        return $this->onComplaint($request, $ticket, 'complaint.open', []);
    }

    /** L-15: the decision on a complaint (accepted | partially_accepted | rejected) and how it was handled — confirmed to the customer. */
    public function resolveComplaint(Request $request, string $ticket): JsonResponse
    {
        $this->api->authorize($request, 'support.ticket.manage', CommandScope::global());
        $data = $request->validate(['outcome' => ['required', 'string', Rule::in(ComplaintService::OUTCOMES)], 'resolution' => ['required', 'string', 'min:10', 'max:4000']]);

        return $this->onComplaint($request, $ticket, 'complaint.resolve', $data);
    }

    /** @param array<string, mixed> $payload */
    private function onComplaint(Request $request, string $ticket, string $op, array $payload): JsonResponse
    {
        $answer = (array) $this->onTicket($request, $ticket, $op, $payload)->getData(true);

        return response()->json($answer + ['complaint' => ComplaintService::present($this->find($ticket))]);
    }

    /**
     * One ticket write through the bus (TicketStaffCommand). Without an `Idempotency-Key` the key carries the ticket's present
     * version: a double click on the same ticket is one write, the next deliberate change on a changed ticket is a new one.
     *
     * @param  array<string, mixed>  $payload
     */
    private function onTicket(Request $request, string $ticket, string $op, array $payload, bool $withMessages = false): JsonResponse
    {
        $model = $this->find($ticket);
        $header = $request->headers->get('Idempotency-Key');
        $prefix = "ticket.{$op}:{$model->id}".(is_string($header) && $header !== '' ? '' : ':'.$this->versionOf($model));
        $command = new TicketStaffCommand($this->idempotencyKey($request, $prefix), ['op' => $op, 'ticket_id' => $model->id] + $payload);
        $this->api->assertTokenScope($request, $command->permission());
        $this->bus->dispatch($command, $this->api->context($request));

        return response()->json(['data' => CustomerSupportController::ticket($model->fresh() ?? $model, $withMessages)]);
    }

    private function versionOf(Ticket $ticket): string
    {
        return substr(hash('sha256', implode('|', [$ticket->state, $ticket->priority, $ticket->queue_id, $ticket->assignee_id, $ticket->escalation_level, $ticket->updated_at?->format('U.u'), $ticket->messages()->count()])), 0, 16);
    }

    /** Offers of paid work on a ticket (H29). */
    public function workOffers(Request $request, WorkOfferService $offers, string $ticket): JsonResponse
    {
        $this->api->authorize($request, 'support.ticket.manage', CommandScope::global());

        return response()->json(['data' => $offers->forTicket($this->find($ticket))]);
    }

    /** Offer work outside the plan with its price; nothing is billed until the customer approves it and the work is marked done. */
    public function proposeWork(Request $request, string $ticket): JsonResponse
    {
        $data = $request->validate(['scope' => ['required', 'string', 'max:24'], 'description' => ['required', 'string', 'min:10', 'max:1000'], 'price_net' => ['required', 'numeric', 'gt:0'], 'minutes' => ['nullable', 'integer', 'min:1', 'max:100000']]);

        return $this->dispatch(new WorkOfferStaffCommand($this->idempotencyKey($request, 'ticket.work_offer.propose'), ['op' => 'propose', 'ticket_id' => $this->find($ticket)->id, 'price_net' => (string) $data['price_net']] + $data), $this->api->context($request), 201);
    }

    public function withdrawWork(Request $request, string $ticket, string $offer): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        return $this->dispatch(new WorkOfferStaffCommand($this->idempotencyKey($request, 'ticket.work_offer.withdraw:'.$offer), ['op' => 'withdraw', 'offer_id' => $this->offerOf($ticket, $offer), 'reason' => $data['reason']]), $this->api->context($request));
    }

    /** The work is done: bill the approved price. Refused with 409 `work_offer_not_approved` for anything the customer did not approve. */
    public function completeWork(Request $request, string $ticket, string $offer): JsonResponse
    {
        return $this->dispatch(new WorkOfferStaffCommand($this->idempotencyKey($request, 'ticket.work_offer.complete:'.$offer), ['op' => 'complete', 'offer_id' => $this->offerOf($ticket, $offer)]), $this->api->context($request));
    }

    private function offerOf(string $ticket, string $offer): string
    {
        if (! WorkOffer::query()->where('ticket_id', $this->find($ticket)->id)->whereKey($offer)->exists()) {
            throw DomainError::notFound('work offer');
        }

        return $offer;
    }

    public function clusters(Request $request, TicketService $tickets): JsonResponse
    {
        $this->api->authorize($request, 'support.queue.manage', CommandScope::global());

        return response()->json(['data' => $tickets->clusters(), 'topics' => array_map(fn ($k, $t) => ['id' => $k, 'label' => $t['label']], array_keys(Triage::TOPICS), Triage::TOPICS)]);
    }

    public function macros(Request $request): JsonResponse
    {
        $this->api->authorize($request, 'support.ticket.manage', CommandScope::global());

        return response()->json(['data' => SupportMacro::query()->orderBy('category')->get()->map(fn (SupportMacro $m) => ['key' => $m->key, 'name' => $m->name, 'category' => $m->category, 'body' => $m->body, 'actions' => $m->actions])->all(), 'queues' => SupportQueue::query()->get()->map(fn (SupportQueue $q) => ['id' => $q->id, 'key' => $q->key, 'name' => $q->name, 'skills' => $q->skills, 'open' => Ticket::query()->where('queue_id', $q->id)->whereNotIn('state', ['RESOLVED', 'CLOSED'])->count()])->all()]);
    }

    public function sla(Request $request, TicketService $tickets): JsonResponse
    {
        $this->api->authorize($request, 'support.queue.manage', CommandScope::global());

        return response()->json(['data' => $tickets->tick($this->api->context($request))]);
    }

    private function find(string $id): Ticket
    {
        $ticket = Ticket::query()->find($id) ?? Ticket::query()->where('number', strtoupper($id))->first();
        if ($ticket === null) {
            throw DomainError::notFound('ticket');
        }

        return $ticket;
    }
}
