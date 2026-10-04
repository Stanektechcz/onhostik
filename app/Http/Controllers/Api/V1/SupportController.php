<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Support\Assistant\AssistantScope;
use Onhost\Domain\Support\Assistant\AssistantService;
use Onhost\Domain\Support\Commands\WorkOfferDecisionCommand;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\Models\TicketMessage;
use Onhost\Domain\Support\Models\WorkOffer;
use Onhost\Domain\Support\TicketService;
use Onhost\Domain\Support\TicketStateMachine;
use Onhost\Domain\Support\TicketVisibility;
use Onhost\Domain\Support\WorkOfferService;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/** Customer tickets and the AI assistant (panel `?tab=tickets`, `?tab=asistent`). */
final class SupportController extends ApiController
{
    public function index(Request $request, TicketVisibility $visibility): JsonResponse
    {
        $organization = $this->api->organization($request);
        // TASK-0043 (permission program S1-09, D19): the tickets of the services, projects and billing the person may read — a
        // project role reads its project's tickets (it read none), nobody reads another project's or, without invoices, billing's
        $this->assertReadsTickets($request, $visibility, (string) $organization->id, TicketVisibility::READ);
        $query = $visibility->query($this->api->user($request), (string) $organization->id);
        if ($request->filled('state')) {
            $query->where('state', strtoupper((string) $request->query('state')));
        }

        return $this->api->paginate($request, $query, fn (Ticket $t) => self::ticket($t));
    }

    public function store(Request $request, TicketService $tickets): JsonResponse
    {
        $organization = $this->api->organization($request);
        // TASK-0043 (S1-09): a ticket about a service is written at that service — a project developer opens one about their own
        // service; a ticket about the organization at large still takes the key at the organization
        $serviceId = $request->input('service_id');
        $service = is_string($serviceId) && $serviceId !== '' ? Service::query()->where('organization_id', $organization->id)->find($serviceId) : null;
        $this->api->authorize($request, TicketVisibility::WRITE, $service !== null ? CommandScope::resource($service->id, $organization->id, $service->project_id) : CommandScope::organization($organization->id));
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
        $model = $this->resolve($request, $ticket, TicketVisibility::WRITE);
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

    public function resolve(Request $request, string $id, string $permission = TicketVisibility::READ): Ticket
    {
        $ticket = Ticket::query()->find($id) ?? Ticket::query()->where('number', strtoupper($id))->first();
        if ($ticket === null || $ticket->organization_id === null) {
            throw DomainError::notFound('ticket');
        }
        // TASK-0043 (S1-09): somebody who reads no ticket of the organization is refused as before; one who reads some of them
        // does not learn that a ticket they may not read exists (another project's, billing's without invoices)
        $visibility = app(TicketVisibility::class);
        $this->assertReadsTickets($request, $visibility, (string) $ticket->organization_id, $permission);
        if (! $visibility->may($this->api->user($request), $ticket, $permission)) {
            throw DomainError::notFound('ticket');
        }

        return $ticket;
    }

    /** The old answer to somebody with no ticket right in the organization at all: 403, then the token scope as before. */
    private function assertReadsTickets(Request $request, TicketVisibility $visibility, string $organizationId, string $permission): void
    {
        if (! $visibility->holdsAnywhere($this->api->user($request), $organizationId, $permission)) {
            throw DomainError::forbidden("Missing permission {$permission}");
        }
        $this->api->assertTokenScope($request, $permission);
    }

    public function close(Request $request, TicketService $tickets, string $ticket): JsonResponse
    {
        $model = $this->resolve($request, $ticket, TicketVisibility::WRITE);
        if (! $model->isOpen()) {
            throw new DomainError('ticket_not_open', 'Tiket už je vyřešený.', 409);
        }
        $tickets->transition($model, TicketStateMachine::RESOLVED, $this->api->context($request), 'Zákazník označil požadavek za vyřešený.', 'public'); // the customer's own words: public (TicketService made a transition note internal by default, TASK-0054)

        return response()->json(['data' => self::ticket($model->fresh(), true)]);
    }

    public function rate(Request $request, TicketService $tickets, string $ticket): JsonResponse
    {
        $model = $this->resolve($request, $ticket, TicketVisibility::WRITE);
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
