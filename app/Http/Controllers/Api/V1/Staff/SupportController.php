<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Controllers\Api\V1\SupportController as CustomerSupportController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Support\Assistant\AssistantService;
use Onhost\Domain\Support\Commands\WorkOfferStaffCommand;
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
        $this->api->authorize($request, 'support.ticket.read', CommandScope::global());
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

    public function show(Request $request, AssistantService $assistant, string $ticket): JsonResponse
    {
        $this->api->authorize($request, 'support.ticket.read', CommandScope::global());
        $model = $this->find($ticket);
        $messages = $model->messages()->get()->map(fn (TicketMessage $m) => ['id' => $m->id, 'from' => $m->uiFrom(), 'author_type' => $m->author_type, 'author_name' => $m->author_name, 'visibility' => $m->visibility, 'text' => $m->body, 'attachments' => $m->attachments ?? [], 'at' => $m->created_at?->toIso8601String()])->all();

        return response()->json(['data' => CustomerSupportController::ticket($model) + ['organization_id' => $model->organization_id, 'assignee_id' => $model->assignee_id, 'queue_id' => $model->queue_id, 'required_skills' => $model->required_skills, 'messages' => $messages, 'sla_events' => $model->slaEvents()->get()->map(fn ($e) => ['kind' => $e->kind, 'due_at' => $e->due_at?->toIso8601String(), 'met' => $e->met, 'delta_minutes' => $e->delta_minutes])->all(), 'ai' => $assistant->transcriptForTicket($model), 'meta' => $model->meta]]);
    }

    public function reply(Request $request, TicketService $tickets, string $ticket): JsonResponse
    {
        $this->api->authorize($request, 'support.ticket.manage', CommandScope::global());
        $model = $this->find($ticket);
        $data = $request->validate(['body' => ['required', 'string', 'max:20000'], 'visibility' => ['nullable', 'in:public,internal'], 'macro' => ['nullable', 'string', 'max:60']]);
        $body = $data['body'];
        $macro = isset($data['macro']) ? SupportMacro::query()->where('key', $data['macro'])->first() : null;
        if ($macro !== null) {
            $body = trim(($macro->body['cs'] ?? '')."\n\n".$body);
        }
        $user = $this->api->user($request);
        $tickets->reply($model, 'staff', $user->id, $user->name, $body, $this->api->context($request), $data['visibility'] ?? 'public');
        if ($macro !== null && ! empty($macro->actions['state']) && TicketStateMachine::machine()->canTransition($model->fresh()->state, (string) $macro->actions['state'])) {
            $tickets->transition($model->fresh(), (string) $macro->actions['state'], $this->api->context($request));
        }

        return response()->json(['data' => CustomerSupportController::ticket($model->fresh(), true)]);
    }

    public function transition(Request $request, TicketService $tickets, string $ticket): JsonResponse
    {
        $this->api->authorize($request, 'support.ticket.manage', CommandScope::global());
        $data = $request->validate(['to' => ['required', 'string'], 'note' => ['nullable', 'string', 'max:2000']]);
        $model = $tickets->transition($this->find($ticket), strtoupper($data['to']), $this->api->context($request), $data['note'] ?? null);

        return response()->json(['data' => CustomerSupportController::ticket($model)]);
    }

    public function assign(Request $request, TicketService $tickets, string $ticket): JsonResponse
    {
        $this->api->authorize($request, 'support.ticket.assign', CommandScope::global());
        $data = $request->validate(['assignee_id' => ['nullable', 'string'], 'priority' => ['nullable', 'in:p1,p2,p3,p4'], 'queue' => ['nullable', 'string', 'max:40']]);
        $model = $this->find($ticket);
        if (array_key_exists('assignee_id', $data)) {
            $tickets->assign($model, $data['assignee_id'] ?: null, $this->api->context($request));
        }
        $patch = [];
        if (! empty($data['priority'])) {
            $patch['priority'] = $data['priority'];
        }
        if (! empty($data['queue'])) {
            $queue = SupportQueue::query()->where('key', $data['queue'])->first();
            if ($queue === null) {
                throw DomainError::notFound('queue');
            }
            $patch['queue_id'] = $queue->id;
        }
        if ($patch !== []) {
            $model->forceFill($patch)->save();
        }

        return response()->json(['data' => CustomerSupportController::ticket($model->fresh())]);
    }

    public function escalate(Request $request, TicketService $tickets, string $ticket): JsonResponse
    {
        $this->api->authorize($request, 'support.ticket.manage', CommandScope::global());
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:250']]);

        return response()->json(['data' => CustomerSupportController::ticket($tickets->escalate($this->find($ticket), $this->api->context($request), $data['reason']))]);
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
