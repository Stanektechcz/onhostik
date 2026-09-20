<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Support\Assistant\AiProviderRegistry;
use Onhost\Domain\Support\Assistant\AssistantService;
use Onhost\Domain\Support\Models\AiRun;
use Onhost\Domain\Support\Models\Handoff;
use Onhost\Domain\Support\Models\SlaEvent;
use Onhost\Domain\Support\Models\SupportQueue;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\TicketService;
use Onhost\Domain\Support\TicketStateMachine;
use Onhost\Domain\Support\Triage;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\AiProvider;

beforeEach(function () {
    $this->seed(LegalEntitySeeder::class);
    Http::preventStrayRequests();
});

it('creates a triaged ticket with SLA clocks, routes it to the right queue and notifies both sides', function () {
    [$user, $org] = $this->customerWithOrganization();
    $this->actingAs($user, 'sanctum');
    $response = $this->postJson('/v1/tickets', ['subject' => 'Nejde mi nastavit MX záznam v DNS zóně', 'body' => 'Přidal jsem MX záznam do zóny, ale pošta stále nechodí. TTL je 3600.', 'priority' => 'vysoka'])->assertCreated();
    $ticket = Ticket::query()->findOrFail($response->json('data.id'));
    expect($ticket->number)->toMatch('/^TK-\d{4}-0001$/')->and($ticket->category)->toBe('dns')->and($ticket->priority)->toBe('p2')->and($ticket->uiPriority())->toBe('vysoka')->and($ticket->state)->toBe(TicketStateMachine::TRIAGED)->and($ticket->uiState())->toBe('otevreny');
    expect(SupportQueue::query()->find($ticket->queue_id)->key)->toBe('domains')->and($ticket->required_skills)->toContain('DNS');
    expect((int) $ticket->created_at->diffInMinutes($ticket->first_response_due_at))->toBe(60)->and((int) $ticket->created_at->diffInMinutes($ticket->resolution_due_at))->toBe(1440); // standard policy, P2
    expect($response->json('data.messages.0.from'))->toBe('zakaznik');

    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('kind', 'ticket')->exists())->toBeTrue()->and(Notification::query()->where('audience', 'customer')->where('kind', 'ticket')->exists())->toBeTrue();
    $ack = MailOutbox::query()->where('template_key', 'ticket-ack')->firstOrFail();
    expect($ack->to)->toBe(strtolower($user->email))->and($ack->subject)->toBe("Přijali jsme váš požadavek {$ticket->number}")->and($ack->vars['sla'])->toBe(60);

    $this->getJson('/v1/tickets')->assertOk()->assertHeader('X-Total-Count', '1');
    [$other] = $this->customerWithOrganization();
    $this->actingAs($other, 'sanctum');
    $this->getJson('/v1/tickets/'.$ticket->id)->assertForbidden();
});

it('runs the conversation: staff reply pauses clocks, customer reply resumes, resolve measures SLA, CSAT', function () {
    [$user, $org] = $this->customerWithOrganization();
    $tickets = app(TicketService::class);
    $ticket = $tickets->create(['subject' => 'Faktura za VPS', 'body' => 'Potřebuji doklad s IČO.'], $this->contextFor($user, $org), $org, $user);
    expect($ticket->category)->toBe('fakturace')->and($ticket->priority)->toBe('p3');

    $staff = $this->staff('support_l1');
    $this->actingAs($staff, 'sanctum');
    $this->postJson("/v1/staff/tickets/{$ticket->id}/messages", ['body' => 'Interní poznámka', 'visibility' => 'internal'])->assertOk();
    expect($ticket->fresh()->state)->toBe(TicketStateMachine::TRIAGED)->and($ticket->fresh()->first_responded_at)->toBeNull(); // notes are not responses
    $this->travel(20)->minutes();
    $this->postJson("/v1/staff/tickets/{$ticket->id}/messages", ['body' => 'Doklad s IČO jsme přegenerovali, najdete jej v sekci Fakturace.', 'macro' => 'billing-invoice-where'])->assertOk()->assertJsonPath('data.state', TicketStateMachine::WAITING_CUSTOMER);
    $ticket->refresh();
    expect($ticket->first_responded_at)->not->toBeNull()->and($ticket->waiting_since)->not->toBeNull();
    // the customer hears about it: the event says who wrote, and the outbox must not mask that (`author_type` starts with "auth")
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'customer')->where('event', 'ticket.replied')->where('organization_id', $org->id)->sole()->title)->toBe("Odpověď podpory · {$ticket->number}")
        ->and(MailOutbox::query()->where('template_key', 'ticket-reply')->where('to', strtolower($user->email))->exists())->toBeTrue()
        ->and(Notification::query()->where('audience', 'internal')->where('event', 'ticket.replied')->exists())->toBeFalse();
    $first = SlaEvent::query()->where('ticket_id', $ticket->id)->where('kind', 'first_response')->firstOrFail();
    expect($first->met)->toBeTrue()->and($first->delta_minutes)->toBeLessThan(0);
    expect($ticket->messages()->where('visibility', 'public')->count())->toBe(2)->and($ticket->messages()->where('visibility', 'internal')->count())->toBe(1);

    $this->travel(3)->days(); // customer waited 3 days: not counted against the resolution clock
    $this->actingAs($user, 'sanctum');
    $this->getJson("/v1/tickets/{$ticket->id}")->assertOk()->assertJsonCount(2, 'data.messages'); // internal note hidden
    $this->postJson("/v1/tickets/{$ticket->id}/messages", ['body' => 'Díky, už ho vidím. Můžeme uzavřít.'])->assertOk()->assertJsonPath('data.state', TicketStateMachine::OPEN);
    $ticket->refresh();
    expect($ticket->paused_minutes)->toBeGreaterThanOrEqual(3 * 24 * 60 - 1)->and($ticket->resolution_due_at->greaterThan($ticket->created_at->copy()->addMinutes(4320 + 3 * 24 * 60 - 5)))->toBeTrue();

    $this->actingAs($staff, 'sanctum');
    $this->postJson("/v1/staff/tickets/{$ticket->id}/transition", ['to' => 'RESOLVED', 'note' => 'Vyřešeno.'])->assertOk()->assertJsonPath('data.ui', 'vyreseny');
    expect(SlaEvent::query()->where('ticket_id', $ticket->id)->where('kind', 'resolution')->value('met'))->toBeTrue();
    $this->actingAs($user, 'sanctum');
    $this->postJson("/v1/tickets/{$ticket->id}/csat", ['score' => 5, 'comment' => 'Rychlé.'])->assertOk()->assertJsonPath('data.csat_score', 5);
    expect(OutboxMessage::query()->whereIn('name', ['ticket.replied', 'ticket.resolved'])->count())->toBe(3); // staff reply, customer reply, resolution (internal notes publish nothing)
});

it('escalates on SLA breach and auto-closes resolved tickets after seven days', function () {
    [$user, $org] = $this->customerWithOrganization();
    $tickets = app(TicketService::class);
    $ticket = $tickets->create(['subject' => 'Server nejede, výpadek 502', 'body' => 'Web vrací 502 už hodinu, nedostupné.'], $this->contextFor($user, $org), $org, $user);
    expect($ticket->category)->toBe('dostupnost')->and(SupportQueue::query()->find($ticket->queue_id)->key)->toBe('l2');

    $this->travel(5)->hours();
    $stats = $tickets->tick();
    expect($stats['breached'])->toBe(1);
    $ticket->refresh();
    expect($ticket->state)->toBe(TicketStateMachine::ESCALATED)->and($ticket->escalation_level)->toBe(1)->and(SupportQueue::query()->find($ticket->queue_id)->key)->toBe('l3');
    expect(OutboxMessage::query()->where('name', 'ticket.sla_breached')->exists())->toBeTrue()->and(OutboxMessage::query()->where('name', 'ticket.escalated')->exists())->toBeTrue();
    expect($tickets->tick()['breached'])->toBe(0); // recorded once

    $tickets->transition($ticket, TicketStateMachine::RESOLVED, $this->contextFor($this->staff('support_l2')), 'Opraveno.');
    $this->travel(8)->days();
    expect($tickets->tick()['closed'])->toBe(1)->and($ticket->fresh()->state)->toBe(TicketStateMachine::CLOSED);
});

it('answers from the rule bank with live facts, proposes confirm-only actions and hands over to a human on request', function () {
    [$user, $org] = $this->customerWithOrganization();
    Invoice::query()->create(['legal_entity' => 'onhost-cz', 'series' => 'FV', 'type' => 'invoice', 'number' => 'FV-2026-0777', 'organization_id' => $org->id, 'currency' => 'CZK', 'state' => Invoice::OVERDUE, 'subtotal_minor' => 100000, 'discount_minor' => 0, 'tax_minor' => 21000, 'total_minor' => 121000, 'paid_minor' => 0, 'buyer' => [], 'seller' => [], 'tax_summary' => [], 'issued_at' => now()->subDays(20), 'due_at' => now()->subDays(6), 'meta' => ['postpaid' => true]]);
    $this->actingAs($user, 'sanctum');

    $first = $this->postJson('/v1/assistant/chat', ['text' => 'Kde najdu fakturu a proč mám nedoplatek?', 'session_id' => 'chat-1'])->assertOk()->json('data');
    expect($first['ai'])->toBeTrue()->and($first['topic'])->toBe('fakturace')->and($first['confident'])->toBeTrue()->and($first['handoff'])->toBeNull();
    expect($first['text'])->toContain('Fakturac')->toContain('1 210 Kč');
    expect(collect($first['facts'])->pluck('k')->all())->toContain('Neuhrazené doklady');
    $pay = collect($first['actions'])->firstWhere('kind', 'pay');
    expect($pay['confirm'])->toBeTrue()->and($pay['label'])->toBe('Zaplatit FV-2026-0777');
    expect(Invoice::query()->where('number', 'FV-2026-0777')->value('state'))->toBe(Invoice::OVERDUE); // proposed, never executed

    $second = $this->postJson('/v1/assistant/chat', ['text' => 'Chci mluvit s člověkem prosím', 'session_id' => 'chat-1'])->assertOk()->json('data');
    expect($second['handoff']['reason'])->toBe('user_request')->and($second['handoff']['number'])->toStartWith('TK-')->and($second['text'])->toContain($second['handoff']['number']);
    $ticket = Ticket::query()->findOrFail($second['handoff']['ticket_id']);
    expect($ticket->channel)->toBe('ai')->and($ticket->tags)->toContain('ai-handoff')->and($ticket->ai_summary)->toContain('Fakturace')->and($ticket->messages()->first()->body)->toContain('USER: Kde najdu fakturu');
    expect(Handoff::query()->where('ticket_id', $ticket->id)->exists())->toBeTrue()->and(AiRun::query()->where('session_id', "{$user->id}:chat-1")->count())->toBe(2);
    expect(OutboxMessage::query()->where('name', 'ticket.handoff')->exists())->toBeTrue();

    $security = $this->postJson('/v1/assistant/chat', ['text' => 'Myslím, že nám někdo hacknul server a unikla data', 'session_id' => 'chat-2'])->assertOk()->json('data');
    expect($security['handoff']['reason'])->toBe('security')->and(Ticket::query()->findOrFail($security['handoff']['ticket_id'])->priority)->toBe('p2');
});

it('uses the configured LLM provider through the AiProvider contract with read-only tools and falls back to rules when it fails', function () {
    [$user, $org] = $this->customerWithOrganization();
    $fake = new class implements AiProvider
    {
        public int $calls = 0;

        public static function providerKey(): string
        {
            return 'fake';
        }

        public function chat(array $messages, array $tools = [], array $options = []): array
        {
            $this->calls++;
            if ($this->calls === 1) {
                return ['content' => null, 'tool_calls' => [['id' => 'call_1', 'name' => 'get_account_facts', 'arguments' => []]], 'usage' => ['input_tokens' => 120, 'output_tokens' => 10], 'model' => 'fake-1', 'finish_reason' => 'tool_calls'];
            }
            $toolResult = collect($messages)->last(fn ($m) => ($m['role'] ?? '') === 'tool')['content'] ?? '[]';

            return ['content' => 'Vidím '.count(json_decode($toolResult, true)).' faktů o účtu. Restart navrhuji potvrdit v panelu.', 'tool_calls' => [], 'usage' => ['input_tokens' => 200, 'output_tokens' => 40], 'model' => 'fake-1', 'finish_reason' => 'stop'];
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
    app(AiProviderRegistry::class)->override($fake);
    $reply = app(AssistantService::class)->chat('Server je pomalý, co s tím?', $org, $user, 'llm-1', $this->contextFor($user, $org));
    expect($reply['text'])->toStartWith('Vidím')->and($reply['topic'])->toBe('vykon');
    $run = AiRun::query()->where('session_id', 'llm-1')->firstOrFail();
    expect($run->provider)->toBe('fake')->and($run->model)->toBe('fake-1')->and($run->input_tokens)->toBe(320)->and($run->tools_called[0]['tool'])->toBe('get_account_facts');

    app(AiProviderRegistry::class)->override(new class implements AiProvider
    {
        public static function providerKey(): string
        {
            return 'broken';
        }

        public function chat(array $messages, array $tools = [], array $options = []): array
        {
            throw new ProviderException('ai', ProviderErrorCode::TRANSIENT, 'upstream down');
        }

        public function embeddings(array $inputs, array $options = []): array
        {
            return [];
        }

        public function moderate(string $input): array
        {
            return ['flagged' => false, 'categories' => []];
        }
    });
    $fallback = app(AssistantService::class)->chat('Jak si nastavím DNS záznam pro doménu?', $org, $user, 'llm-2', $this->contextFor($user, $org));
    expect($fallback['topic'])->toBe('dns')->and($fallback['text'])->toContain('dvou krocích');
    expect(AiRun::query()->where('session_id', 'llm-2')->value('provider'))->toBe('rules');
});

it('classifies topics deterministically without diacritics and caps customer priority', function () {
    expect(Triage::classify('VÝPADEK! Server spadl a nejede')['topic'])->toBe('dostupnost');
    expect(Triage::classify('Potřebuji obnovit zálohu snapshotu')['topic'])->toBe('zalohy');
    expect(Triage::priority('p1', 'objednavka', false, false))->toBe('p2');
    expect(Triage::priority('p1', 'objednavka', true, false))->toBe('p1');
    expect(Triage::priority('nizka', 'dostupnost', false, true, 'web je nedostupny, vypadek'))->toBe('p1');
    expect(Triage::priority(null, 'bezpecnost', false, false))->toBe('p2');
});

it('attaches a ticket to the customer\'s own domain only', function () {
    [$user, $org] = $this->customerWithOrganization();
    [, $other] = $this->customerWithOrganization();
    $foreign = Domain::query()->create(['organization_id' => $other->id, 'fqdn_ascii' => 'cizi-domena.cz', 'fqdn_unicode' => 'cizi-domena.cz', 'tld' => 'cz', 'state' => 'ACTIVE', 'expires_at' => now()->addYear()]);
    $own = Domain::query()->create(['organization_id' => $org->id, 'fqdn_ascii' => 'moje-domena.cz', 'fqdn_unicode' => 'moje-domena.cz', 'tld' => 'cz', 'state' => 'ACTIVE', 'expires_at' => now()->addYear()]);
    $this->actingAs($user, 'sanctum');
    $body = ['subject' => 'Nejde mi nastavit DNS záznam', 'body' => 'Potřebuji poradit s MX záznamem u domény.'];
    $this->postJson('/v1/tickets', $body + ['domain_id' => $foreign->id])->assertNotFound();
    expect(Ticket::query()->count())->toBe(0);
    expect($this->postJson('/v1/tickets', $body + ['domain_id' => $own->id])->assertCreated()->json('data.domain_id'))->toBe($own->id);
});
