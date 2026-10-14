<?php

declare(strict_types=1);

use Database\Seeders\SupportSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Support\Assistant\AiProviderRegistry;
use Onhost\Domain\Support\Models\AiRun;
use Onhost\Domain\Support\Models\SlaEvent;
use Onhost\Domain\Support\Models\SlaPolicy;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\TicketStateMachine;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\AiProvider;

require_once __DIR__.'/../../Support/E2E/E2EHelpers.php';

/*
 * E7 — a support ticket between a customer and support, touching only the real HTTP routes.
 *
 * The customer opens a ticket about their web hosting (with an attachment's metadata, a password typed into the text) → support L1
 * sees the queue in the console navigation and in GET /v1/staff/tickets → L2 assigns it (L1 may not) → a reply is drafted for the agent
 * (the password never reaches the model, the model's own words are masked) and posted → the customer reads it and answers → the clocks
 * are measured against the SLA policy (time is moved with Carbon::setTestNow): first response met, next response and a second ticket's
 * first response breached → the scheduler route escalates and tells the desk → support resolves and closes, the customer rates and
 * reopens within 14 days, and not after them.
 *
 * Only the edges are doubles (mail via the outbox, the language model, the clock). Every staff write is a bus command with an audit row;
 * the outbox events are relayed through the NotificationRouter. Asserts do not depend on row order (SQLite and PostgreSQL alike).
 */

const E2E_SUPPORT_SECRET = 'Tajne123';
const E2E_SUPPORT_START = '2026-10-05 09:00:00'; // a Monday: the standard policy counts wall-clock minutes either way

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse(E2E_SUPPORT_START, 'UTC'));
    e2eSeedPlatform();
    $this->seed(SupportSeeder::class);
    LaravelNotification::fake(); // the verification mail of the sign-up (the platform's own notifications are rows, not this facade)
    Http::preventStrayRequests();
});

afterEach(function () {
    Carbon::setTestNow();
    app(AiProviderRegistry::class)->override(null);
});

/** A model that writes down what it was given and answers with a password of its own. @param array<int,mixed> $seen */
function e2eSupportLlm(array &$seen): AiProvider
{
    return new class($seen) implements AiProvider
    {
        /** @param array<int,mixed> $seen */
        public function __construct(private array &$seen) {}

        public static function providerKey(): string
        {
            return 'fake';
        }

        public function chat(array $messages, array $tools = [], array $options = []): array
        {
            $this->seen[] = ['messages' => $messages, 'tools' => $tools];

            return ['content' => "Dobrý den,\nzkontrolovali jsme službu shop.cz. Heslo: ".E2E_SUPPORT_SECRET." prosím nikomu neposílejte.\nONhost podpora", 'tool_calls' => [], 'usage' => ['input_tokens' => 200, 'output_tokens' => 30], 'model' => 'fake-1', 'finish_reason' => 'stop'];
        }

        public function embeddings(array $inputs, array $options = []): array
        {
            return [];
        }

        public function moderate(string $input): array
        {
            return ['flagged' => false, 'categories' => []];
        }
    };
}

/** Switch the test client to another person (a customer session and a staff token do not mix): fresh session, fresh guards. */
function e2eSupportActAs(object $test, User $user): void
{
    $test->flushSession();
    app('auth')->forgetGuards();
    $test->actingAs($user, 'sanctum');
}

/** @return array{0:User,1:Organization,2:string,3:Service} the signed-up customer, their organization, the password and a web hosting */
function e2eSupportWorld(object $test): array
{
    [$user, $org, $password] = e2eSignUp($test, 'jana@shop.test', 'Shop s.r.o.');

    return [$user, $org, $password, featureWebService($org, 'aapanel')];
}

function e2eSupportRelay(): void
{
    app(OutboxPublisher::class)->relayPending();
}

/** The console's boot object of a rendered surface (`window.ONHOST = {...};`). */
function e2eSupportBoot(string $html): array
{
    preg_match('~<script>window\.ONHOST = (\{.*?\});</script>~s', $html, $m);

    return json_decode($m[1] ?? 'null', true, flags: JSON_THROW_ON_ERROR) ?? [];
}

it('carries a ticket from the customer through support and back: clocks, breaches, closing and reopening', function () {
    [$customer, $org, , $service] = e2eSupportWorld($this);
    $this->getJson('/v1/staff/tickets')->assertForbidden(); // a customer is not on the staff routes
    $l1 = $this->staff('support_l1', ['name' => 'Petr Podpora']);
    $l2 = $this->staff('support_l2', ['name' => 'Jana Vedoucí']);

    // ── the customer opens a ticket about the service; an attachment (metadata) rides along, a dangerous type does not ──
    $attachment = ['name' => 'chyba.png', 'size' => 2048, 'mime' => 'image/png', 'sha256' => str_repeat('a', 64), 'scanned' => true];
    $body = ['subject' => 'Web hlásí nezabezpečené spojení', 'body' => 'Dobrý den, na shop.cz se ukazuje varování o certifikátu. FTP heslo je '.E2E_SUPPORT_SECRET.' kdyby bylo třeba.', 'service_id' => $service->id, 'attachments' => [$attachment]];
    $this->withHeaders(e2eHeaders('tk-bad'))->postJson('/v1/tickets', ['attachments' => [['name' => 'x.sh', 'mime' => 'application/x-sh', 'size' => 10]]] + $body)->assertStatus(422)->assertJsonPath('error', 'attachment_type_forbidden');
    expect(Ticket::query()->count())->toBe(0);

    $created = $this->withHeaders(e2eHeaders('tk-open'))->postJson('/v1/tickets', $body)->assertCreated()
        ->assertJsonPath('data.state', TicketStateMachine::TRIAGED)->assertJsonPath('data.service_id', $service->id)->assertJsonPath('data.messages.0.attachments.0.name', 'chyba.png');
    $ticket = Ticket::query()->findOrFail($created->json('data.id'));
    $policy = SlaPolicy::query()->findOrFail($ticket->sla_policy_id);
    $targets = $policy->targetsFor($ticket->priority);
    $t0 = Carbon::now();
    expect($ticket->organization_id)->toBe($org->id)->and($ticket->user_id)->toBe($customer->id)->and($ticket->first_response_due_at->equalTo($t0->copy()->addMinutes($targets['first'])))->toBeTrue()
        ->and($ticket->resolution_due_at->equalTo($t0->copy()->addMinutes($targets['resolve'])))->toBeTrue();
    expect(AuditEvent::query()->where('action', 'ticket.create')->where('resource_id', $ticket->id)->where('actor_id', $customer->id)->where('result', 'succeeded')->count())->toBe(1)
        ->and(OutboxMessage::query()->where('name', 'ticket.created')->where('aggregate_id', $ticket->id)->count())->toBe(1);
    e2eSupportRelay();
    expect(Notification::query()->where('event', 'ticket.created')->where('audience', 'internal')->exists())->toBeTrue() // the desk hears of it
        ->and(MailOutbox::query()->where('template_key', 'ticket-ack')->where('to', 'jana@shop.test')->exists())->toBeTrue() // the customer gets the acknowledgement
        ->and($this->getJson('/v1/tickets')->assertOk()->assertHeader('X-Total-Count', '1')->json('data.0.number'))->toBe($ticket->number);

    // ── support L1 finds it: the console shows the support section, the queue lists it, opening it is audited ──
    e2eSupportActAs($this, $l1);
    $boot = e2eSupportBoot($this->actingAs($l1)->get('/sprava')->assertOk()->getContent());
    $nav = collect($boot['user']['nav'])->keyBy('key');
    expect($nav)->toHaveKeys(['queue', 'ticket'])->and($nav['queue']['section'])->toBe('support')->and($nav['queue']['label']['cs'])->toBe('Fronta tiketů')
        ->and($nav->pluck('key')->all())->not->toContain('billing-settings');
    e2eSupportActAs($this, $l1);
    $listed = $this->getJson('/v1/staff/tickets?open=1&organization_id='.$org->id)->assertOk()->assertHeader('X-Total-Count', '1');
    expect($listed->json('data.0.id'))->toBe($ticket->id)->and($listed->json('data.0.queue'))->not->toBeNull();
    $seen = $this->getJson("/v1/staff/tickets/{$ticket->number}")->assertOk()->assertJsonPath('data.messages.0.attachments.0.name', 'chyba.png');
    expect($seen->json('data.messages.0.text'))->toContain('shop.cz')
        ->and(AuditEvent::query()->where('action', 'staff.read.ticket')->where('resource_id', $ticket->id)->where('actor_id', $l1->id)->exists())->toBeTrue();

    // ── L1 answers and resolves, it does not assign (L2 does); a note stays inside ──
    $this->withHeaders(e2eHeaders('assign-l1'))->postJson("/v1/staff/tickets/{$ticket->id}/assign", ['assignee_id' => $l1->id])->assertForbidden();
    $this->withHeaders(e2eHeaders('note'))->postJson("/v1/staff/tickets/{$ticket->id}/notes", ['body' => 'Zákazník vložil heslo do tiketu, nikam ho nekopírovat.'])->assertOk();
    e2eSupportActAs($this, $l2);
    $this->withHeaders(e2eHeaders('assign-l2'))->postJson("/v1/staff/tickets/{$ticket->id}/assign", ['assignee_id' => $l1->id])->assertOk()->assertJsonPath('data.state', TicketStateMachine::OPEN);
    expect($ticket->fresh()->assignee_id)->toBe($l1->id);
    e2eSupportRelay();
    expect(Notification::query()->where('event', 'ticket.assigned')->where('user_id', $l1->id)->where('audience', 'internal')->exists())->toBeTrue();

    // ── 20 minutes in, a reply is drafted: the password is masked on its way to the model and on its way back; nothing is sent by drafting ──
    Carbon::setTestNow($t0->copy()->addMinutes(20));
    e2eSupportActAs($this, $l1);
    $messagesBefore = $ticket->messages()->count();
    $sent = [];
    app(AiProviderRegistry::class)->override(e2eSupportLlm($sent));
    $draft = $this->withHeaders(e2eHeaders('draft'))->postJson("/v1/staff/tickets/{$ticket->id}/draft")->assertOk()->json('data');
    expect($sent)->toHaveCount(1)->and($sent[0]['tools'])->toBe([])
        ->and(json_encode($sent[0]['messages'], JSON_UNESCAPED_UNICODE))->toContain('[skryto]')->not->toContain(E2E_SUPPORT_SECRET)->not->toContain('nekopírovat') // the internal note stays inside
        ->and($draft['source'])->toBe('llm')->and($draft['draft'])->toContain('zkontrolovali jsme')->not->toContain(E2E_SUPPORT_SECRET)
        ->and($ticket->messages()->count())->toBe($messagesBefore)->and($ticket->fresh()->first_responded_at)->toBeNull()
        ->and(json_encode(AiRun::query()->where('ticket_id', $ticket->id)->firstOrFail()->transcript, JSON_UNESCAPED_UNICODE))->not->toContain(E2E_SUPPORT_SECRET)
        ->and(AuditEvent::query()->where('action', 'support.ticket.draft')->where('resource_id', $ticket->id)->exists())->toBeTrue();

    // the agent posts the (masked) draft: first response measured within the target, state waits for the customer
    $this->withHeaders(e2eHeaders('reply'))->postJson("/v1/staff/tickets/{$ticket->id}/messages", ['body' => $draft['draft']])->assertOk()->assertJsonPath('data.state', TicketStateMachine::WAITING_CUSTOMER);
    $ticket->refresh();
    $first = SlaEvent::query()->where('ticket_id', $ticket->id)->where('kind', 'first_response')->sole();
    expect($ticket->first_responded_at?->equalTo(Carbon::now()))->toBeTrue()->and($first->met)->toBeTrue()->and($first->due_at->equalTo($t0->copy()->addMinutes($targets['first'])))->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'ticket.staff.reply')->where('result', 'succeeded')->where('actor_id', $l1->id)->count())->toBe(1) // through the bus
        ->and(AuditEvent::query()->where('action', 'ticket.staff.assign')->where('result', 'succeeded')->where('actor_id', $l2->id)->count())->toBe(1);
    e2eSupportRelay();
    expect(MailOutbox::query()->where('template_key', 'ticket-reply')->where('to', 'jana@shop.test')->exists())->toBeTrue()
        ->and(Notification::query()->where('event', 'ticket.replied')->where('audience', 'customer')->exists())->toBeTrue();

    // ── the customer reads the answer (never the internal note) and writes back 10 minutes later ──
    Carbon::setTestNow($t0->copy()->addMinutes(30));
    e2eSupportActAs($this, $customer);
    $texts = collect($this->getJson("/v1/tickets/{$ticket->id}")->assertOk()->json('data.messages'))->pluck('text')->all();
    expect($texts)->toHaveCount(2)->and(implode(' ', $texts))->not->toContain('nekopírovat')->and($texts[1])->toContain('zkontrolovali jsme');
    $this->withHeaders(e2eHeaders('customer-reply'))->postJson("/v1/tickets/{$ticket->id}/messages", ['body' => 'Děkuji, certifikát stále hlásí chybu.'])->assertOk()->assertJsonPath('data.state', TicketStateMachine::OPEN);
    $ticket->refresh();
    expect($ticket->next_response_due_at->equalTo(Carbon::now()->addMinutes($targets['next'])))->toBeTrue()
        ->and($ticket->last_customer_message_at->equalTo(Carbon::now()))->toBeTrue();
    e2eSupportRelay();
    expect(Notification::query()->where('event', 'ticket.replied')->where('audience', 'internal')->exists())->toBeTrue(); // "Reakce zákazníka" for the desk

    // a second, unanswered ticket of the same customer
    Carbon::setTestNow($t0->copy()->addMinutes(35));
    $second = Ticket::query()->findOrFail($this->withHeaders(e2eHeaders('tk-second'))->postJson('/v1/tickets', ['subject' => 'Kde najdu fakturu za hosting', 'body' => 'Potřebuji doklad.'])->assertCreated()->json('data.id'));
    $secondDue = $second->first_response_due_at;

    // ── time passes: the next response of the first ticket and the first response of the second are late; the scheduler route measures, escalates, tells the desk ──
    Carbon::setTestNow($ticket->next_response_due_at->copy()->max($secondDue)->addMinute());
    e2eSupportActAs($this, $this->staff('support_manager'));
    expect($this->withHeaders(e2eHeaders('tick'))->postJson('/v1/staff/tickets/sla-tick')->assertOk()->json('data.breached'))->toBeGreaterThanOrEqual(2);
    $next = SlaEvent::query()->where('ticket_id', $ticket->id)->where('kind', 'next_response')->sole();
    $late = SlaEvent::query()->where('ticket_id', $second->id)->where('kind', 'first_response')->sole();
    expect($next->met)->toBeFalse()->and($late->met)->toBeFalse()->and($late->due_at->equalTo($secondDue))->toBeTrue()->and($late->delta_minutes)->toBeGreaterThanOrEqual(1)
        ->and($ticket->fresh()->state)->toBe(TicketStateMachine::ESCALATED)->and($ticket->fresh()->escalation_level)->toBe(1)
        ->and(OutboxMessage::query()->where('name', 'ticket.sla_breached')->get()->pluck('payload.kind')->all())->toContain('next_response', 'first_response')
        ->and(OutboxMessage::query()->where('name', 'ticket.escalated')->count())->toBeGreaterThanOrEqual(2);
    e2eSupportRelay();
    expect(Notification::query()->where('event', 'ticket.sla_breached')->where('audience', 'internal')->where('title', 'like', "SLA porušeno · {$ticket->number}%")->exists())->toBeTrue()
        ->and(Notification::query()->where('event', 'ticket.sla_breached')->where('audience', 'customer')->exists())->toBeFalse(); // a breach is the desk's business, not mail to the customer

    // the same tick again measures nothing twice
    $this->withHeaders(e2eHeaders('tick-again'))->postJson('/v1/staff/tickets/sla-tick')->assertOk()->assertJsonPath('data.breached', 0);

    // ── the customer closes the second one himself (it is only RESOLVED for them); support resolves and closes the first, the customer rates ──
    e2eSupportActAs($this, $customer);
    $this->withHeaders(e2eHeaders('customer-close'))->postJson("/v1/tickets/{$second->id}/close")->assertOk()->assertJsonPath('data.state', TicketStateMachine::RESOLVED);
    e2eSupportActAs($this, $l1);
    $this->withHeaders(e2eHeaders('resolve'))->postJson("/v1/staff/tickets/{$ticket->id}/transition", ['to' => 'RESOLVED', 'note' => 'Certifikát jsme obnovili.', 'note_visibility' => 'public'])->assertOk()->assertJsonPath('data.state', TicketStateMachine::RESOLVED);
    $this->withHeaders(e2eHeaders('close'))->postJson("/v1/staff/tickets/{$ticket->id}/transition", ['to' => 'CLOSED'])->assertOk()->assertJsonPath('data.state', TicketStateMachine::CLOSED);
    $resolution = SlaEvent::query()->where('ticket_id', $ticket->id)->where('kind', 'resolution')->sole();
    expect($resolution->met)->toBeTrue()->and($ticket->fresh()->closed_at)->not->toBeNull();
    e2eSupportRelay();
    expect(Notification::query()->where('event', 'ticket.resolved')->where('audience', 'customer')->exists())->toBeTrue();
    e2eSupportActAs($this, $customer);
    $this->withHeaders(e2eHeaders('csat'))->postJson("/v1/tickets/{$ticket->id}/csat", ['score' => 5, 'comment' => 'Rychlé.'])->assertOk()->assertJsonPath('data.csat_score', 5);

    // ── reopened by a reply within 14 days; closed again, a reply after the window is refused ──
    Carbon::setTestNow(Carbon::now()->addDays(3));
    $this->withHeaders(e2eHeaders('reopen'))->postJson("/v1/tickets/{$ticket->id}/messages", ['body' => 'Chyba je zpět.'])->assertOk()->assertJsonPath('data.state', TicketStateMachine::OPEN)->assertJsonPath('data.reopen_count', 1);
    expect($ticket->fresh()->closed_at)->toBeNull();
    e2eSupportActAs($this, $l1);
    foreach (['RESOLVED', 'CLOSED'] as $to) {
        $this->withHeaders(e2eHeaders('again-'.$to))->postJson("/v1/staff/tickets/{$ticket->id}/transition", ['to' => $to])->assertOk()->assertJsonPath('data.state', $to);
    }
    Carbon::setTestNow(Carbon::now()->addDays(15));
    e2eSupportActAs($this, $customer);
    $this->withHeaders(e2eHeaders('too-late'))->postJson("/v1/tickets/{$ticket->id}/messages", ['body' => 'Ještě jedna věc.'])->assertStatus(409)->assertJsonPath('error', 'ticket_closed');
    expect($ticket->fresh()->state)->toBe(TicketStateMachine::CLOSED);

    // every staff write left one succeeded audit row through the bus, under the person who made it
    $audited = AuditEvent::query()->where('result', 'succeeded')->whereIn('actor_id', [$l1->id, $l2->id])->whereIn('action', ['ticket.staff.reply', 'ticket.staff.note', 'ticket.staff.assign', 'ticket.staff.transition'])->count();
    expect($audited)->toBe(7); // reply, note, assign, resolve, close, resolve, close
});

it('keeps support routes to the roles that hold the keys and the ticket to its own organization', function () {
    [$customer, , , $service] = e2eSupportWorld($this);
    $ticket = Ticket::query()->findOrFail($this->withHeaders(e2eHeaders('tk'))->postJson('/v1/tickets', ['subject' => 'Web nejede', 'body' => 'Nic se nenačítá.', 'service_id' => $service->id])->assertCreated()->json('data.id'));
    $count = AuditEvent::query()->where('resource_id', $ticket->id)->count();

    // staff without the support keys: no queue, no detail, no writes, no navigation item
    foreach (['sales', 'billing_operator'] as $role) {
        $person = $this->staff($role);
        e2eSupportActAs($this, $person);
        $this->getJson('/v1/staff/tickets')->assertForbidden();
        $this->getJson("/v1/staff/tickets/{$ticket->id}")->assertForbidden();
        $this->withHeaders(e2eHeaders('r-'.$role))->postJson("/v1/staff/tickets/{$ticket->id}/messages", ['body' => 'Dobrý den'])->assertForbidden();
        $this->withHeaders(e2eHeaders('t-'.$role))->postJson("/v1/staff/tickets/{$ticket->id}/transition", ['to' => 'RESOLVED'])->assertForbidden();
        $this->withHeaders(e2eHeaders('d-'.$role))->postJson("/v1/staff/tickets/{$ticket->id}/draft")->assertForbidden();
        $boot = e2eSupportBoot($this->actingAs($person)->get('/sprava')->assertOk()->getContent());
        expect(array_column($boot['user']['nav'], 'key'))->not->toContain('queue')->not->toContain('ticket');
    }
    $fresh = $ticket->fresh();
    expect($fresh->state)->toBe(TicketStateMachine::TRIAGED)->and($fresh->messages()->count())->toBe(1)
        ->and(AuditEvent::query()->where('resource_id', $ticket->id)->where('action', 'like', 'ticket.staff.%')->where('result', 'succeeded')->count())->toBe(0)
        ->and(AuditEvent::query()->where('resource_id', $ticket->id)->count())->toBeGreaterThanOrEqual($count); // refused attempts are on record too, never writes

    // another organization's customer: the ticket does not exist for them, by id and by number, on every route
    [$stranger, $otherOrg] = $this->customerWithOrganization(['email' => 'cizi@other.test']);
    e2eSupportActAs($this, $stranger);
    $this->getJson('/v1/tickets')->assertOk()->assertHeader('X-Total-Count', '0');
    foreach ([$ticket->id, $ticket->number] as $ref) {
        $this->getJson("/v1/tickets/{$ref}")->assertNotFound();
        $this->withHeaders(e2eHeaders('s-reply'))->postJson("/v1/tickets/{$ref}/messages", ['body' => 'Cizí zpráva'])->assertNotFound();
        $this->withHeaders(e2eHeaders('s-close'))->postJson("/v1/tickets/{$ref}/close")->assertNotFound();
        $this->getJson("/v1/tickets/{$ref}/work-offers")->assertNotFound();
    }
    $this->getJson('/v1/tickets/TK-2026-9999')->assertNotFound(); // what does not exist answers the same as what belongs to somebody else
    // and a ticket cannot be pointed at somebody else's service
    $this->withHeaders(e2eHeaders('s-open'))->postJson('/v1/tickets', ['subject' => 'Cizí služba', 'body' => 'Zkouška', 'service_id' => $service->id], ['X-Organization' => $otherOrg->id])->assertNotFound();
    expect(Ticket::query()->count())->toBe(1)->and($ticket->fresh()->messages()->count())->toBe(1)->and($ticket->fresh()->state)->toBe(TicketStateMachine::TRIAGED);

    e2eSupportActAs($this, $customer);
    $this->getJson("/v1/tickets/{$ticket->id}")->assertOk()->assertJsonPath('data.number', $ticket->number);
});
