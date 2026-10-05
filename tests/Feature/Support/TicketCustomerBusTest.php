<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\TicketStateMachine;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * TASK-0098: a customer opens and answers a ticket through the CommandBus (TicketCustomerCommand), not straight in TicketService
 * from the controller. The bus adds its own audit row of the command, idempotency and the permission check at the scope the
 * ticket concerns; TicketService keeps writing the rows and events it wrote (ticket.create / ticket.reply, ticket.created /
 * ticket.replied). What the customer wrote never lands in an audit row.
 */

beforeEach(function () {
    $this->seed(LegalEntitySeeder::class);
    Http::preventStrayRequests();
});

it('opens a customer ticket through the bus with the same audit rows and event, and keeps its text out of the audit', function () {
    [$user, $org] = $this->customerWithOrganization();
    $this->actingAs($user, 'sanctum');

    $id = $this->postJson('/v1/tickets', ['subject' => 'Heslo k FTP nefunguje', 'body' => 'Moje heslo je Tajne-Heslo-123 a nejde to.', 'priority' => 'normalni'])
        ->assertCreated()->assertJsonPath('data.subject', 'Heslo k FTP nefunguje')->json('data.id');
    $ticket = Ticket::query()->findOrFail($id);

    $bus = AuditEvent::query()->where('action', 'ticket.customer.create')->where('result', 'succeeded')->sole();
    expect($bus->organization_id)->toBe($org->id)->and($bus->actor_id)->toBe($user->id)
        ->and(AuditEvent::query()->where('action', 'ticket.create')->where('resource_id', $ticket->id)->where('result', 'succeeded')->count())->toBe(1)
        ->and(OutboxMessage::query()->where('name', 'ticket.created')->where('aggregate_id', $ticket->id)->count())->toBe(1)
        ->and($ticket->channel)->toBe('portal')->and($ticket->user_id)->toBe($user->id)
        ->and($ticket->messages()->sole()->author_type)->toBe('customer');
    expect(json_encode(AuditEvent::query()->pluck('detail')->all()))->not->toContain('Tajne-Heslo-123')->not->toContain('Heslo k FTP');

    // the same request again under the same key is the same ticket, not a second one
    $this->withHeader('Idempotency-Key', 'tcb-open-1')->postJson('/v1/tickets', ['subject' => 'Druhý', 'body' => 'Jiný dotaz.'])->assertCreated();
    $this->withHeader('Idempotency-Key', 'tcb-open-1')->postJson('/v1/tickets', ['subject' => 'Druhý', 'body' => 'Jiný dotaz.'])->assertCreated();
    expect(Ticket::query()->where('organization_id', $org->id)->count())->toBe(2);
});

it('answers a ticket through the bus with the same audit row and event; a second message is a new reply', function () {
    [$user, $org] = $this->customerWithOrganization();
    $this->actingAs($user, 'sanctum');
    $id = $this->postJson('/v1/tickets', ['subject' => 'Pomalý web', 'body' => 'Web se načítá deset sekund.'])->assertCreated()->json('data.id');

    $this->postJson("/v1/tickets/{$id}/messages", ['body' => 'Doplňuji: hlavně ráno.'])->assertOk()->assertJsonPath('data.state', TicketStateMachine::OPEN);
    $this->postJson("/v1/tickets/{$id}/messages", ['body' => 'A ještě večer.'])->assertOk();

    $ticket = Ticket::query()->findOrFail($id);
    expect(AuditEvent::query()->where('action', 'ticket.customer.reply')->where('result', 'succeeded')->where('organization_id', $org->id)->count())->toBe(2)
        ->and(AuditEvent::query()->where('action', 'ticket.reply')->where('resource_id', $id)->count())->toBe(2)
        ->and(OutboxMessage::query()->where('name', 'ticket.replied')->where('aggregate_id', $id)->count())->toBe(2)
        ->and($ticket->messages()->where('author_type', 'customer')->count())->toBe(3);
    expect(json_encode(AuditEvent::query()->where('action', 'like', 'ticket.customer.%')->pluck('detail')->all()))->not->toContain('hlavně ráno');
});

it('refuses through the bus what the person may not write, and a guest of the organization learns nothing from the bus', function () {
    [$user, $org] = $this->customerWithOrganization();
    $guest = User::query()->create(['email' => 'tcb-guest@bus.test', 'name' => 'Host', 'password' => 'Correct-Horse-Battery-9', 'state' => 'active']);
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $guest->id, 'state' => 'active', 'role_key' => 'guest', 'joined_at' => now()]);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $guest->id, 'role_key' => 'guest', 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id]);

    $this->actingAs($guest, 'sanctum');
    $this->withHeader('X-Organization', $org->id)->postJson('/v1/tickets', ['subject' => 'Cizí', 'body' => 'Nesmím.'])->assertForbidden();
    expect(Ticket::query()->count())->toBe(0)->and(AuditEvent::query()->where('action', 'ticket.create')->count())->toBe(0);
});
