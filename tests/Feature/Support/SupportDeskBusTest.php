<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Support\Models\SlaPolicy;
use Onhost\Domain\Support\Models\SupportMacro;
use Onhost\Domain\Support\Models\SupportQueue;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\SlaClock;
use Onhost\Domain\Support\TicketService;
use Onhost\Domain\Support\TicketStateMachine;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * TASK-0054 — readiness audit 2026-10, P1-2 and P1-3: the staff ticket writes go through the bus, the assignee is checked,
 * every field change is audited, the desk's settings can be managed, and the SLA clocks honour business hours.
 */

beforeEach(function () {
    $this->seed(LegalEntitySeeder::class);
    Http::preventStrayRequests();
});

function sdbTicket(object $test, string $subject = 'Faktura za VPS', string $body = 'Potřebuji doklad s IČO.'): Ticket
{
    return (function () use ($subject, $body) {
        [$user, $org] = $this->customerWithOrganization();

        return app(TicketService::class)->create(['subject' => $subject, 'body' => $body], $this->contextFor($user, $org), $org, $user);
    })->call($test);
}

it('accepts the console transition body {state} as well as {to} (P1-2)', function () {
    $ticket = sdbTicket($this);
    $this->actingAs($this->staff('support_l1'), 'sanctum');

    $this->postJson("/v1/staff/tickets/{$ticket->id}/transition", ['state' => 'RESOLVED'])->assertOk()->assertJsonPath('data.state', TicketStateMachine::RESOLVED);
    $this->postJson("/v1/staff/tickets/{$ticket->id}/transition", ['state' => 'open'])->assertOk()->assertJsonPath('data.state', TicketStateMachine::OPEN);
    $this->postJson("/v1/staff/tickets/{$ticket->id}/transition", ['to' => 'RESOLVED', 'state' => 'CLOSED'])->assertOk()->assertJsonPath('data.state', TicketStateMachine::RESOLVED); // `to` wins
    $this->postJson("/v1/staff/tickets/{$ticket->id}/transition", [])->assertStatus(422);
    $this->postJson("/v1/staff/tickets/{$ticket->id}/transition", ['state' => 'NONSENSE'])->assertStatus(422);

    expect(AuditEvent::query()->where('action', 'ticket.staff.transition')->where('result', 'succeeded')->count())->toBe(3); // through the bus
});

it('keeps a transition note internal unless it is asked to be public, and only a public note travels with the event', function () {
    $ticket = sdbTicket($this);
    $this->actingAs($this->staff('support_l1'), 'sanctum');

    $this->postJson("/v1/staff/tickets/{$ticket->id}/transition", ['state' => 'WAITING_INTERNAL', 'note' => 'Čekáme na odpověď správce sítě.'])->assertOk();
    $this->postJson("/v1/staff/tickets/{$ticket->id}/transition", ['to' => 'RESOLVED', 'note' => 'Doklad je v sekci Fakturace.', 'note_visibility' => 'public'])->assertOk();

    expect($ticket->messages()->where('body', 'Čekáme na odpověď správce sítě.')->value('visibility'))->toBe('internal')
        ->and($ticket->messages()->where('body', 'Doklad je v sekci Fakturace.')->value('visibility'))->toBe('public');
    expect(OutboxMessage::query()->where('name', 'ticket.waiting_internal')->sole()->payload['note'])->toBeNull()
        ->and(OutboxMessage::query()->where('name', 'ticket.resolved')->sole()->payload['note'])->toBe('Doklad je v sekci Fakturace.');

    $customer = $ticket->user_id ? User::query()->find($ticket->user_id) : null;
    $this->actingAs($customer, 'sanctum');
    $texts = collect($this->getJson("/v1/tickets/{$ticket->id}")->assertOk()->json('data.messages'))->pluck('text')->all();
    expect($texts)->not->toContain('Čekáme na odpověď správce sítě.')->and($texts)->toContain('Doklad je v sekci Fakturace.');
});

it('writes an internal note through the bus without touching the clocks or publishing anything', function () {
    $ticket = sdbTicket($this);
    $this->actingAs($this->staff('support_l1'), 'sanctum');

    $this->postJson("/v1/staff/tickets/{$ticket->id}/notes", ['body' => 'Zákazník volal, je netrpělivý.'])->assertOk()->assertJsonPath('data.state', TicketStateMachine::TRIAGED);

    $ticket->refresh();
    expect($ticket->messages()->where('visibility', 'internal')->value('body'))->toBe('Zákazník volal, je netrpělivý.')
        ->and($ticket->first_responded_at)->toBeNull()
        ->and(OutboxMessage::query()->where('name', 'ticket.replied')->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'ticket.staff.note')->where('result', 'succeeded')->exists())->toBeTrue();
});

it('lets L2 assign but not L1, and publishes ticket.assigned to the new owner', function () {
    $ticket = sdbTicket($this);
    $agent = $this->staff('support_l1');

    $this->actingAs($this->staff('support_l1'), 'sanctum');
    $this->postJson("/v1/staff/tickets/{$ticket->id}/assign", ['assignee_id' => $agent->id])->assertForbidden();
    expect($ticket->fresh()->assignee_id)->toBeNull();

    $this->actingAs($this->staff('support_l2'), 'sanctum');
    $this->postJson("/v1/staff/tickets/{$ticket->id}/assign", ['assignee_id' => $agent->id])->assertOk()->assertJsonPath('data.state', TicketStateMachine::OPEN);
    expect($ticket->fresh()->assignee_id)->toBe($agent->id);

    $event = OutboxMessage::query()->where('name', 'ticket.assigned')->sole();
    expect($event->payload['assignee_id'])->toBe($agent->id)->and($event->payload['previous_assignee_id'])->toBeNull();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'ticket.assigned')->where('user_id', $agent->id)->where('audience', 'internal')->exists())->toBeTrue();

    $audit = AuditEvent::query()->where('action', 'ticket.assign')->latest('created_at')->firstOrFail();
    expect($audit->detail['changes']['assignee_id'])->toBe(['from' => null, 'to' => $agent->id]);

    // taken away again: audited, published, nobody notified
    $this->postJson("/v1/staff/tickets/{$ticket->id}/assign", ['assignee_id' => null])->assertOk();
    expect($ticket->fresh()->assignee_id)->toBeNull()->and(OutboxMessage::query()->where('name', 'ticket.assigned')->count())->toBe(2);
});

it('refuses an assignee who is not active support staff (422)', function () {
    $ticket = sdbTicket($this);
    [$customer] = $this->customerWithOrganization();
    $finance = $this->staff('billing_operator');
    $gone = $this->staff('support_l1', ['state' => 'disabled']);
    $this->actingAs($this->staff('support_l2'), 'sanctum');

    foreach ([$customer->id, $finance->id, $gone->id, 'usr_does_not_exist'] as $id) {
        $this->postJson("/v1/staff/tickets/{$ticket->id}/assign", ['assignee_id' => $id])->assertStatus(422)->assertJsonPath('error', 'assignee_invalid');
    }
    expect($ticket->fresh()->assignee_id)->toBeNull()->and(OutboxMessage::query()->where('name', 'ticket.assigned')->exists())->toBeFalse();
});

it('audits a priority and a queue change with the old and new values and moves the clocks to the new priority', function () {
    $ticket = sdbTicket($this);
    expect($ticket->priority)->toBe('p3');
    $this->actingAs($this->staff('support_l2'), 'sanctum');

    $this->postJson("/v1/staff/tickets/{$ticket->id}/assign", ['priority' => 'p1', 'queue' => 'l2'])->assertOk();

    $ticket->refresh();
    $l2 = SupportQueue::query()->where('key', 'l2')->value('id');
    expect($ticket->priority)->toBe('p1')->and($ticket->queue_id)->toBe($l2)
        ->and((int) $ticket->created_at->diffInMinutes($ticket->first_response_due_at))->toBe(30); // standard P1
    $audit = AuditEvent::query()->where('action', 'ticket.route')->sole();
    expect($audit->detail['changes']['priority'])->toBe(['from' => 'p3', 'to' => 'p1'])
        ->and($audit->detail['changes']['queue_id']['to'])->toBe($l2)
        ->and($audit->before_hash)->not->toBeNull()->and($audit->after_hash)->not->toBe($audit->before_hash);

    $this->postJson("/v1/staff/tickets/{$ticket->id}/assign", ['queue' => 'no-such-queue'])->assertStatus(422)->assertJsonPath('error', 'queue_unknown');
    $this->actingAs($this->staff('support_l1'), 'sanctum');
    $this->postJson("/v1/staff/tickets/{$ticket->id}/assign", ['priority' => 'p4'])->assertForbidden();
    expect($ticket->fresh()->priority)->toBe('p1');
});

it('replies and escalates through the bus', function () {
    $ticket = sdbTicket($this);
    $this->actingAs($this->staff('support_l1'), 'sanctum');

    $this->postJson("/v1/staff/tickets/{$ticket->id}/messages", ['body' => 'Doklad posíláme.', 'macro' => 'resolved-confirm'])->assertOk()->assertJsonPath('data.state', TicketStateMachine::RESOLVED);
    $this->postJson("/v1/staff/tickets/{$ticket->id}/messages", ['body' => 'x', 'macro' => 'no-such-macro'])->assertStatus(422)->assertJsonPath('error', 'macro_unknown');
    $this->postJson("/v1/staff/tickets/{$ticket->id}/transition", ['state' => 'OPEN'])->assertOk();
    $this->postJson("/v1/staff/tickets/{$ticket->id}/escalate", ['reason' => 'Potřeba přístupu k uzlu'])->assertOk()->assertJsonPath('data.state', TicketStateMachine::ESCALATED);

    expect(AuditEvent::query()->whereIn('action', ['ticket.staff.reply', 'ticket.staff.escalate'])->where('result', 'succeeded')->count())->toBe(2)
        ->and(AuditEvent::query()->where('action', 'ticket.staff.reply')->where('result', 'failed')->count())->toBe(1);
});

it('manages macros under support.queue.manage', function () {
    $this->actingAs($this->staff('support_l2'), 'sanctum');
    $this->postJson('/v1/staff/support/macros', ['key' => 'dns-propagation', 'name' => 'Propagace DNS', 'body' => ['cs' => 'Změny DNS se projeví do 24 hodin.']])->assertForbidden();
    $this->getJson('/v1/staff/support/macros')->assertForbidden();

    $this->actingAs($this->staff('support_manager'), 'sanctum');
    $created = $this->postJson('/v1/staff/support/macros', ['key' => 'dns-propagation', 'name' => 'Propagace DNS', 'category' => 'dns', 'body' => ['cs' => 'Změny DNS se projeví do 24 hodin.'], 'actions' => ['state' => 'WAITING_CUSTOMER']])->assertCreated()->json('data');
    expect($created['key'])->toBe('dns-propagation')->and($created['actions'])->toBe(['state' => 'WAITING_CUSTOMER']);
    expect(collect($this->getJson('/v1/staff/support/macros')->assertOk()->json('data'))->pluck('key'))->toContain('dns-propagation');

    $this->postJson('/v1/staff/support/macros', ['key' => 'dns-propagation', 'name' => 'Znovu', 'body' => ['cs' => 'Text']])->assertStatus(422)->assertJsonPath('error', 'support_key_taken');
    $this->postJson('/v1/staff/support/macros', ['key' => 'bad-state', 'name' => 'X', 'body' => ['cs' => 'Text'], 'actions' => ['state' => 'GONE']])->assertStatus(422);
    $this->postJson('/v1/staff/support/macros', ['key' => 'no-body', 'name' => 'X'])->assertStatus(422);

    $this->patchJson("/v1/staff/support/macros/{$created['id']}", ['name' => 'Propagace DNS (24 h)'])->assertOk()->assertJsonPath('data.name', 'Propagace DNS (24 h)');
    $this->patchJson("/v1/staff/support/macros/{$created['id']}", ['key' => 'renamed'])->assertStatus(422)->assertJsonPath('error', 'support_key_immutable');
    $audit = AuditEvent::query()->where('action', 'support.macro.update')->where('resource_id', $created['id'])->latest('created_at')->firstOrFail();
    expect($audit->detail['changes']['name'])->toBe(['from' => 'Propagace DNS', 'to' => 'Propagace DNS (24 h)']);

    // an agent uses it
    $ticket = sdbTicket($this, 'Doména nesměruje', 'Změnil jsem DNS a web stále neukazuje na nový server.');
    $this->actingAs($this->staff('support_l1'), 'sanctum');
    $this->postJson("/v1/staff/tickets/{$ticket->id}/messages", ['body' => 'Zkontrolujte prosím zítra.', 'macro' => 'dns-propagation'])->assertOk();
    expect($ticket->messages()->where('author_type', 'staff')->value('body'))->toStartWith('Změny DNS se projeví do 24 hodin.');

    $this->actingAs($this->staff('support_manager'), 'sanctum');
    $this->deleteJson("/v1/staff/support/macros/{$created['id']}")->assertOk()->assertJsonPath('data.deleted', true);
    expect(SupportMacro::query()->whereKey($created['id'])->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'support.macro.delete')->where('resource_id', $created['id'])->exists())->toBeTrue();
});

it('manages queues and refuses to break escalation or remove a queue tickets use', function () {
    $this->actingAs($this->staff('support_manager'), 'sanctum');
    $queue = $this->postJson('/v1/staff/support/queues', ['key' => 'cloud', 'name' => 'Cloud a VPS', 'skills' => ['PROXMOX'], 'escalates_to' => 'l3'])->assertCreated()->json('data');
    expect($queue['state'])->toBe('active')->and($queue['open'])->toBe(0);

    $this->postJson('/v1/staff/support/queues', ['key' => 'loop', 'name' => 'Smyčka', 'skills' => [], 'escalates_to' => 'loop'])->assertStatus(422)->assertJsonPath('error', 'queue_escalation_invalid');
    $this->patchJson("/v1/staff/support/queues/{$queue['id']}", ['escalates_to' => 'nowhere'])->assertStatus(422);
    $this->patchJson("/v1/staff/support/queues/{$queue['id']}", ['skills' => ['PROXMOX', 'NETWORK']])->assertOk()->assertJsonPath('data.skills', ['PROXMOX', 'NETWORK']);

    $this->deleteJson('/v1/staff/support/queues/l3')->assertStatus(409)->assertJsonPath('error', 'support_config_in_use'); // l2 and cloud escalate to it
    $this->deleteJson("/v1/staff/support/queues/{$queue['id']}")->assertOk();
    expect(SupportQueue::query()->where('key', 'cloud')->exists())->toBeFalse();
});

it('counts SLA due times inside business hours across a weekend', function () {
    $this->actingAs($this->staff('support_manager'), 'sanctum');
    $hours = ['days' => [1, 2, 3, 4, 5], 'from' => '08:00', 'to' => '18:00', 'tz' => 'Europe/Prague'];
    $this->patchJson('/v1/staff/support/sla-policies/standard', ['business_hours_only' => true, 'business_hours' => ['days' => [1, 2, 3, 4, 5], 'from' => '18:00', 'to' => '08:00', 'tz' => 'Europe/Prague']])->assertStatus(422)->assertJsonPath('error', 'business_hours_invalid');
    $policy = $this->patchJson('/v1/staff/support/sla-policies/standard', ['business_hours_only' => true, 'business_hours' => $hours])->assertOk()->json('data');
    expect($policy['business_hours_only'])->toBeTrue()->and($policy['version'])->toBe(2);
    $this->deleteJson('/v1/staff/support/sla-policies/standard')->assertStatus(409);

    // Friday 9 October 2026, 17:00 in Prague (15:00 UTC): one working hour left this week
    $this->travelTo(Carbon::parse('2026-10-09 17:00', 'Europe/Prague'));
    $ticket = sdbTicket($this);
    expect($ticket->priority)->toBe('p3'); // standard P3: first response 240 min, resolution 4320 min

    // 60 min on Friday + 180 min from Monday 08:00 → Monday 12 October 11:00 Prague
    expect($ticket->first_response_due_at->copy()->setTimezone('Europe/Prague')->format('Y-m-d H:i'))->toBe('2026-10-12 11:00');
    // 60 min Friday + 7 × 600 min (Mon–Fri, Mon–Tue) + 60 min → Wednesday 21 October 09:00 Prague
    expect($ticket->resolution_due_at->copy()->setTimezone('Europe/Prague')->format('Y-m-d H:i'))->toBe('2026-10-21 09:00');

    // a weekend hour is no SLA breach
    $this->travelTo(Carbon::parse('2026-10-11 20:00', 'Europe/Prague'));
    expect(app(TicketService::class)->tick()['breached'])->toBe(0);

    // a policy without business hours keeps counting wall-clock minutes
    SlaPolicy::query()->where('key', 'standard')->update(['business_hours_only' => false]);
    $wall = sdbTicket($this);
    expect((int) $wall->created_at->diffInMinutes($wall->first_response_due_at))->toBe(240);
});

it('adds business minutes from outside the window and over a day boundary', function () {
    $hours = ['days' => [1, 2, 3, 4, 5], 'from' => '08:00', 'to' => '18:00', 'tz' => 'Europe/Prague'];
    $saturday = Carbon::parse('2026-10-10 10:00', 'Europe/Prague')->utc();
    $evening = Carbon::parse('2026-10-13 17:30', 'Europe/Prague')->utc();
    $early = Carbon::parse('2026-10-13 06:00', 'Europe/Prague')->utc();

    expect(SlaClock::addBusinessMinutes($saturday, 30, $hours)->setTimezone('Europe/Prague')->format('D H:i'))->toBe('Mon 08:30')
        ->and(SlaClock::addBusinessMinutes($evening, 60, $hours)->setTimezone('Europe/Prague')->format('D H:i'))->toBe('Wed 08:30')
        ->and(SlaClock::addBusinessMinutes($early, 15, $hours)->setTimezone('Europe/Prague')->format('D H:i'))->toBe('Tue 08:15')
        ->and(SlaClock::addBusinessMinutes($saturday, 30, $hours)->getTimezone()->getName())->toBe('UTC')
        ->and(SlaClock::window(['days' => [], 'from' => '08:00', 'to' => '18:00', 'tz' => 'Europe/Prague']))->toBeNull()
        ->and(SlaClock::window(['days' => [1], 'from' => '08:00', 'to' => '18:00', 'tz' => 'Mars/Olympus']))->toBeNull();
});
