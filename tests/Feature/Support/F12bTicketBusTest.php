<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Access\ServiceAccessService;
use Onhost\Domain\Support\Assistant\AssistantScope;
use Onhost\Domain\Support\Assistant\AssistantService;
use Onhost\Domain\Support\Models\Handoff;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\TicketService;
use Onhost\Domain\Support\TicketStateMachine;
use Onhost\Domain\Support\Triage;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * F12b (after TASK-0098): closing and rating a ticket, and the ticket the assistant opens when it hands a conversation to a
 * human, went to TicketService past the CommandBus — no permission check of the command, no idempotency, no audit row of the
 * command. They go through TicketCustomerCommand now; TicketService keeps writing the rows and events it wrote. What the
 * customer wrote (a CSAT comment, the transcript) never lands in an audit row.
 */

beforeEach(function () {
    $this->seed(LegalEntitySeeder::class);
    Http::preventStrayRequests();
});

it('closes a ticket through the bus with the same audit row and event, and a ticket closed again after a reopen closes again', function () {
    [$user, $org] = $this->customerWithOrganization();
    $this->actingAs($user, 'sanctum');
    $id = $this->postJson('/v1/tickets', ['subject' => 'Pomalý web', 'body' => 'Web se načítá deset sekund.'])->assertCreated()->json('data.id');

    $this->postJson("/v1/tickets/{$id}/close")->assertOk()->assertJsonPath('data.state', TicketStateMachine::RESOLVED);
    $this->postJson("/v1/tickets/{$id}/close")->assertStatus(409)->assertJsonPath('error', 'ticket_not_open'); // the ticket changed: a new request, refused

    expect(AuditEvent::query()->where('action', 'ticket.customer.close')->where('result', 'succeeded')->where('organization_id', $org->id)->where('actor_id', $user->id)->count())->toBe(1)
        ->and(OutboxMessage::query()->where('name', 'ticket.resolved')->where('aggregate_id', $id)->count())->toBe(1)
        ->and(Ticket::query()->findOrFail($id)->messages()->where('visibility', 'public')->where('body', 'like', '%označil požadavek za vyřešený%')->count())->toBe(1);

    // reopened by the customer's next message, then closed again: the second close is not the first one replayed
    $this->postJson("/v1/tickets/{$id}/messages", ['body' => 'Zase to nejde.'])->assertOk();
    expect(Ticket::query()->findOrFail($id)->isOpen())->toBeTrue();
    $this->postJson("/v1/tickets/{$id}/close")->assertOk()->assertJsonPath('data.state', TicketStateMachine::RESOLVED);
    expect(AuditEvent::query()->where('action', 'ticket.customer.close')->where('result', 'succeeded')->count())->toBe(2);
});

it('rates a resolved ticket through the bus with the same audit row, and keeps the comment out of the audit', function () {
    [$user, $org] = $this->customerWithOrganization();
    $ticket = app(TicketService::class)->create(['subject' => 'DNS', 'body' => 'Nejde doména.'], $this->contextFor($user, $org), $org, $user);
    app(TicketService::class)->transition($ticket, TicketStateMachine::RESOLVED, $this->contextFor($this->staff('support_l2')), 'Opraveno.');
    $this->actingAs($user, 'sanctum');

    $this->postJson("/v1/tickets/{$ticket->id}/csat", ['score' => 4, 'comment' => 'Rychlé, heslo bylo Tajne-Heslo-456.'])->assertOk()->assertJsonPath('data.csat_score', 4);

    expect(AuditEvent::query()->where('action', 'ticket.customer.rate')->where('result', 'succeeded')->where('organization_id', $org->id)->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'ticket.csat')->where('resource_id', $ticket->id)->count())->toBe(1)
        ->and($ticket->refresh()->csat_comment)->toContain('Rychlé');
    expect(json_encode(AuditEvent::query()->pluck('detail')->all()))->not->toContain('Tajne-Heslo-456');
});

it('refuses to close or rate through the bus what the person may not write', function () {
    [$user, $org] = $this->customerWithOrganization();
    $ticket = app(TicketService::class)->create(['subject' => 'Fakturace', 'body' => 'Dotaz.'], $this->contextFor($user, $org), $org, $user);
    $reader = User::query()->create(['email' => 'f12b-reader@bus.test', 'name' => 'Čtenář', 'password' => 'Correct-Horse-Battery-9', 'state' => 'active']);
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $reader->id, 'state' => 'active', 'role_key' => 'viewer', 'joined_at' => now()]);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $reader->id, 'role_key' => 'viewer', 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id]);
    $this->actingAs($reader, 'sanctum');

    $status = $this->withHeader('X-Organization', $org->id)->postJson("/v1/tickets/{$ticket->id}/close")->status();
    expect($status)->toBeIn([403, 404])->and($ticket->refresh()->isOpen())->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'ticket.customer.close')->where('result', 'succeeded')->count())->toBe(0);
});

it('opens the assistant\'s handoff ticket through the bus with the same rows and event, and keeps the transcript out of the audit', function () {
    [$user, $org] = $this->customerWithOrganization();
    $this->actingAs($user, 'sanctum');

    $answer = $this->postJson('/v1/assistant/chat', ['text' => 'Chci mluvit s člověkem, moje číslo zakázky je ZK-998877', 'session_id' => 'f12b-1'])->assertOk()->json('data');

    expect($answer['handoff']['reason'])->toBe('user_request');
    $ticket = Ticket::query()->findOrFail($answer['handoff']['ticket_id']);
    $bus = AuditEvent::query()->where('action', 'ticket.customer.handoff')->where('result', 'succeeded')->sole();
    expect($bus->organization_id)->toBe($org->id)->and($bus->actor_id)->toBe($user->id)
        ->and($ticket->channel)->toBe('ai')->and($ticket->tags)->toContain('ai-handoff', 'user_request')->and($ticket->ai_summary)->not->toBeEmpty()
        ->and(AuditEvent::query()->where('action', 'ticket.create')->where('resource_id', $ticket->id)->count())->toBe(1)
        ->and(OutboxMessage::query()->where('name', 'ticket.created')->where('aggregate_id', $ticket->id)->count())->toBe(1)
        ->and(OutboxMessage::query()->where('name', 'ticket.handoff')->where('aggregate_id', $ticket->id)->count())->toBe(1)
        ->and(Handoff::query()->where('ticket_id', $ticket->id)->where('reason', 'user_request')->count())->toBe(1);
    expect(json_encode(AuditEvent::query()->pluck('detail')->all()))->not->toContain('ZK-998877');
});

it('answers a guest who asks for a human with the friendly refusal, not a 403, and dispatches nothing', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $shop = featureWebService($org, 'aapanel');
    $guest = $this->customer(['email' => 'f12b-guest@example.cz']);
    app(OrganizationService::class)->attachMember($org, $guest, 'guest', CommandContext::system('test'), true);
    app(ServiceAccessService::class)->share($org, $shop, 'f12b-guest@example.cz', ['view', 'assistant'], $this->contextFor($owner, $org, 'totp'));
    $this->actingAs($guest, 'sanctum');

    $human = $this->postJson('/v1/assistant/chat', ['text' => 'Chci mluvit s člověkem', 'session_id' => 'f12b-g'], ['X-Organization' => $org->id])->assertOk()->json('data');

    expect($human['handoff'])->toBeNull()->and($human['text'])->toContain('majitele služby')
        ->and(Ticket::query()->where('organization_id', $org->id)->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', 'ticket.customer.handoff')->count())->toBe(0);
});

it('degrades to the friendly refusal when the bus refuses the handoff the assistant expected to open', function () {
    [, $org] = $this->customerWithOrganization();
    $contact = $this->customer(['email' => 'f12b-contact@example.cz']);
    app(OrganizationService::class)->attachMember($org, $contact, 'support_contact', CommandContext::system('test'), true);
    $scope = AssistantScope::for($org, $contact, app(Authorizer::class)); // read while the person could still write tickets
    PolicyBinding::query()->where('principal_type', 'user')->where('principal_id', $contact->id)->delete();
    app(Authorizer::class)->flush();

    $answer = app(AssistantService::class)->chat('Chci mluvit s člověkem', $org, $contact, 'f12b-d', $this->contextFor($contact, $org), 'cs', $scope);

    expect($answer['handoff'])->toBeNull()->and($answer['text'])->toContain('nemohu vaším jménem založit tiket')
        ->and(Ticket::query()->where('organization_id', $org->id)->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', 'ticket.customer.handoff')->where('result', 'denied')->count())->toBe(1);
});

it('keeps a ticket category inside the known topics whatever the customer sends', function () {
    [$user, $org] = $this->customerWithOrganization();
    $this->actingAs($user, 'sanctum');

    $id = $this->postJson('/v1/tickets', ['subject' => 'Faktura', 'body' => 'Kde najdu fakturu?', 'category' => 'vip-<b>queue</b>'])->assertCreated()->json('data.id');

    $category = Ticket::query()->findOrFail($id)->category;
    expect(array_merge(array_keys(Triage::TOPICS), ['ostatni']))->toContain($category);
});
