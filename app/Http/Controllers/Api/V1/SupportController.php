<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Support\Assistant\AssistantScope;
use Onhost\Domain\Support\Assistant\AssistantService;
use Onhost\Domain\Support\Commands\WorkOfferDecisionCommand;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\Models\TicketMessage;
use Onhost\Domain\Support\Models\WorkOffer;
use Onhost\Domain\Support\TicketService;
use Onhost\Domain\Support\TicketStateMachine;
use Onhost\Domain\Support\WorkOfferService;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/** Customer tickets and the AI assistant (panel `?tab=tickets`, `?tab=asistent`). */
final class SupportController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'support.ticket.read', CommandScope::organization($organization->id));
        $query = Ticket::query()->where('organization_id', $organization->id);
        if ($request->filled('state')) {
            $query->where('state', strtoupper((string) $request->query('state')));
        }

        return $this->api->paginate($request, $query, fn (Ticket $t) => self::ticket($t));
    }

    public function store(Request $request, TicketService $tickets): JsonResponse
    {
        $organization = $this->api->organization($request);
        $this->api->authorize($request, 'support.ticket.write', CommandScope::organization($organization->id));
        $data = $request->validate(['subject' => ['required', 'string', 'max:250'], 'body' => ['required', 'string', 'max:20000'], 'category' => ['nullable', 'string', 'max:40'], 'priority' => ['nullable', 'string', 'max:10'], 'service_id' => ['nullable', 'string'], 'domain_id' => ['nullable', 'string'], 'attachments' => ['nullable', 'array', 'max:10']]);
        $ticket = $tickets->create($data + ['channel' => 'portal'], $this->api->context($request, $organization), $organization, $this->api->user($request));

        return response()->json(['data' => self::ticket($ticket, true)], 201);
    }

    public function show(Request $request, string $ticket): JsonResponse
    {
        return response()->json(['data' => self::ticket($this->resolve($request, $ticket), true)]);
    }

    public function reply(Request $request, TicketService $tickets, string $ticket): JsonResponse
    {
        $model = $this->resolve($request, $ticket, 'support.ticket.write');
        $data = $request->validate(['body' => ['required', 'string', 'max:20000'], 'attachments' => ['nullable', 'array', 'max:10']]);
        $user = $this->api->user($request);
        $tickets->reply($model, 'customer', $user->id, $user->name, $data['body'], $this->api->context($request), 'public', (array) ($data['attachments'] ?? []));

        return response()->json(['data' => self::ticket($model->fresh(), true)]);
    }

    /** Offers of paid work on the ticket (H29): what is offered, for how much, and what was decided. */
    public function workOffers(Request $request, WorkOfferService $offers, string $ticket): JsonResponse
    {
        return response()->json(['data' => $offers->forTicket($this->resolve($request, $ticket))]);
    }

    /** The customer's answer to the price. Approving takes the right to place orders; declining only the right to write on the ticket. */
    public function decideWorkOffer(Request $request, string $ticket, string $offer): JsonResponse
    {
        $model = $this->resolve($request, $ticket);
        $data = $request->validate(['approve' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:500']]);
        if (! WorkOffer::query()->where('ticket_id', $model->id)->whereKey($offer)->exists()) {
            throw DomainError::notFound('work offer');
        }

        return $this->dispatch(new WorkOfferDecisionCommand((string) $model->organization_id, $this->idempotencyKey($request, 'ticket.work_offer.decide:'.$offer), ['offer_id' => $offer, 'approve' => (bool) $data['approve'], 'note' => $data['note'] ?? null, 'author_name' => $this->api->user($request)->name]), $this->api->context($request, Organization::query()->find($model->organization_id)));
    }

    public function resolve(Request $request, string $id, string $permission = 'support.ticket.read'): Ticket
    {
        $ticket = Ticket::query()->find($id) ?? Ticket::query()->where('number', strtoupper($id))->first();
        if ($ticket === null || $ticket->organization_id === null) {
            throw DomainError::notFound('ticket');
        }
        $this->api->authorize($request, $permission, CommandScope::organization($ticket->organization_id));

        return $ticket;
    }

    public function close(Request $request, TicketService $tickets, string $ticket): JsonResponse
    {
        $model = $this->resolve($request, $ticket, 'support.ticket.write');
        if (! $model->isOpen()) {
            throw new DomainError('ticket_not_open', 'Tiket už je vyřešený.', 409);
        }
        $tickets->transition($model, TicketStateMachine::RESOLVED, $this->api->context($request), 'Zákazník označil požadavek za vyřešený.');

        return response()->json(['data' => self::ticket($model->fresh(), true)]);
    }

    public function rate(Request $request, TicketService $tickets, string $ticket): JsonResponse
    {
        $model = $this->resolve($request, $ticket, 'support.ticket.write');
        $data = $request->validate(['score' => ['required', 'integer', 'min:1', 'max:5'], 'comment' => ['nullable', 'string', 'max:500']]);

        return response()->json(['data' => self::ticket($tickets->rate($model, (int) $data['score'], $data['comment'] ?? null, $this->api->context($request)))]);
    }

    public function assistant(Request $request, AssistantService $assistant, Authorizer $authorizer): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:4000'], 'session_id' => ['nullable', 'string', 'max:80'], 'locale' => ['nullable', 'in:cs,en']]);
        $user = $request->user() instanceof User ? $request->user() : null;
        $organization = $user ? $this->api->organization($request, false) : null;
        if ($organization !== null && $user !== null && ! AssistantScope::mayChat($organization, $user, $authorizer)) {
            throw DomainError::forbidden('Missing permission support.chat.use');
        }
        if ($organization !== null) {
            $this->api->assertTokenScope($request, 'support.chat.use');
        }
        $sessionId = $data['session_id'] ?? null;
        if ($sessionId !== null && $user !== null) {
            $sessionId = "{$user->id}:{$sessionId}"; // sessions are per user: nobody can read another user's transcript by guessing an id
        }

        return response()->json(['data' => $assistant->chat($data['text'], $organization, $user, $sessionId, $this->api->context($request, $organization), $data['locale'] ?? ($organization?->locale ?? 'cs'))]);
    }

    public static function ticket(Ticket $t, bool $withMessages = false): array
    {
        $out = [
            'id' => $t->id, 'number' => $t->number, 'subject' => $t->subject, 'category' => $t->category, 'priority' => $t->priority, 'prio' => $t->uiPriority(), 'state' => $t->state, 'ui' => $t->uiState(), 'channel' => $t->channel,
            'service_id' => $t->service_id, 'domain_id' => $t->domain_id, 'email' => $t->email, 'name' => $t->name, 'created_at' => $t->created_at?->toIso8601String(), 'updated_at' => $t->updated_at?->toIso8601String(),
            'first_response_due_at' => $t->first_response_due_at?->toIso8601String(), 'first_responded_at' => $t->first_responded_at?->toIso8601String(), 'resolution_due_at' => $t->resolution_due_at?->toIso8601String(), 'resolved_at' => $t->resolved_at?->toIso8601String(), 'closed_at' => $t->closed_at?->toIso8601String(),
            'csat_score' => $t->csat_score, 'reopen_count' => $t->reopen_count, 'escalation_level' => $t->escalation_level, 'ai_summary' => $t->ai_summary,
        ];
        if ($withMessages) {
            $out['messages'] = $t->messages()->where('visibility', 'public')->get()->map(fn (TicketMessage $m) => ['id' => $m->id, 'from' => $m->uiFrom(), 'author_type' => $m->author_type, 'author_name' => $m->author_name, 'text' => $m->body, 'attachments' => $m->attachments ?? [], 'at' => $m->created_at?->toIso8601String()])->all();
        }

        return $out;
    }
}
