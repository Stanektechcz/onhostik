<?php

declare(strict_types=1);

use App\Domains\Ai\Services\AiAssistantService;
use App\Domains\Support\Actions\GenerateKbFromTicketAction;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Services\TicketService;
use App\Jobs\AnalyzeTicketWithAiJob;
use App\Models\KbArticle;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Job dispatch ───────────────────────────────────────────────────────────────

it('AnalyzeTicketWithAiJob is dispatched when a ticket is opened', function (): void {
    Queue::fake();

    $user     = customerUser();
    $customer = $user->customer;

    app(TicketService::class)->open($customer, $user, 'Test dotaz', 'Toto je testovací zpráva.');

    Queue::assertPushed(AnalyzeTicketWithAiJob::class);
});

// ── AI analysis via sync queue ─────────────────────────────────────────────────

it('ticket gets ai_classification after job runs via sync queue', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $ticket = app(TicketService::class)->open(
        $customer,
        $user,
        'Faktura nefunguje',
        'Nemohu zaplatit fakturu, platba se vrací.',
    );

    $ticket->refresh();

    expect($ticket->ai_classification)->not->toBeNull()
        ->and($ticket->ai_sentiment)->not->toBeNull()
        ->and($ticket->ai_draft)->not->toBeNull()
        ->and($ticket->ai_analysed_at)->not->toBeNull();
});

it('ticket ai_classification is one of the allowed labels', function (): void {
    $user   = customerUser();
    $ticket = app(TicketService::class)->open(
        $user->customer,
        $user,
        'DNS nastavení domény',
        'Jak nastavím DNS záznamy pro svou doménu?',
    );

    $ticket->refresh();

    expect($ticket->ai_classification)
        ->toBeIn(['billing', 'technical', 'account', 'sales', 'general', '']);
});

it('ticket ai_sentiment is one of the allowed labels', function (): void {
    $user   = customerUser();
    $ticket = app(TicketService::class)->open(
        $user->customer,
        $user,
        'Problém se serverem',
        'Server nefunguje, data jsou nedostupná!',
    );

    $ticket->refresh();

    expect($ticket->ai_sentiment)
        ->toBeIn(['positive', 'neutral', 'negative', '']);
});

it('ticket ai_draft contains a reply suggestion', function (): void {
    $user   = customerUser();
    $ticket = app(TicketService::class)->open(
        $user->customer,
        $user,
        'Zapomenuté heslo',
        'Jak si mohu obnovit heslo?',
    );

    $ticket->refresh();

    expect($ticket->ai_draft)->not->toBeEmpty();
});

// ── AiAssistantService::analyzeTicket() directly ──────────────────────────────

it('analyzeTicket() can be called directly and fills ticket ai fields', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $ticket = SupportTicket::factory()->for($customer, 'customer')->create([
        'subject' => 'Testovací ticket pro AI',
    ]);
    $ticket->messages()->create([
        'user_id'  => $user->id,
        'is_staff' => false,
        'message'  => 'Toto je testovací zpráva pro AI analýzu.',
    ]);

    app(AiAssistantService::class)->analyzeTicket($ticket, $user);

    $ticket->refresh();

    expect($ticket->ai_classification)->not->toBeNull()
        ->and($ticket->ai_sentiment)->not->toBeNull()
        ->and($ticket->ai_draft)->not->toBeNull()
        ->and($ticket->ai_analysed_at)->not->toBeNull();
});

it('analyzeTicket() does not crash on ticket with no messages', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->for($user->customer, 'customer')->create([
        'subject' => 'Ticket bez zpráv',
    ]);

    // Should not throw
    app(AiAssistantService::class)->analyzeTicket($ticket, $user);

    $ticket->refresh();

    expect($ticket->ai_analysed_at)->not->toBeNull();
});

// ── Admin view: AI panel ───────────────────────────────────────────────────────

it('admin support-show page renders AI analysis panel when ticket has been analysed', function (): void {
    $admin    = adminUser();
    $user     = customerUser();
    $customer = $user->customer;

    $ticket = SupportTicket::factory()->for($customer, 'customer')->create([
        'subject'           => 'Testovací lístek',
        'ai_classification' => 'billing',
        'ai_sentiment'      => 'negative',
        'ai_draft'          => 'Vážený zákazníku, prověřili jsme váš dotaz.',
        'ai_analysed_at'    => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.support.show', $ticket))
        ->assertOk()
        ->assertSee('Billing')
        ->assertSee('Negativní')
        ->assertSee('Analysováno');
});

it('admin support-show shows "pending analysis" when ticket has no AI data yet', function (): void {
    $admin    = adminUser();
    $user     = customerUser();
    $customer = $user->customer;

    $ticket = SupportTicket::factory()->for($customer, 'customer')->create([
        'subject' => 'Nový lístek bez AI',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.support.show', $ticket))
        ->assertOk()
        ->assertSee('Analýza nebyla spuštěna');
});

// ── GenerateKbFromTicketAction ─────────────────────────────────────────────────

it('GenerateKbFromTicketAction creates a draft KB article from closed ticket', function (): void {
    $admin    = adminUser();
    $user     = customerUser();
    $customer = $user->customer;

    $ticket = SupportTicket::factory()->for($customer, 'customer')->create([
        'subject'           => 'Jak nastavit DNS záznamy',
        'ai_classification' => 'technical',
    ]);
    $ticket->messages()->create([
        'user_id'  => $user->id,
        'is_staff' => false,
        'message'  => 'Chci vědět, jak nakonfigurovat DNS záznamy pro svou doménu.',
    ]);

    $article = app(GenerateKbFromTicketAction::class)->handle($ticket, $admin);

    expect($article)->toBeInstanceOf(KbArticle::class)
        ->and($article->is_published)->toBeFalse()
        ->and($article->title)->toBe('Jak nastavit DNS záznamy')
        ->and($article->body)->not->toBeEmpty();
});

it('GenerateKbFromTicketAction returns null when ticket has no messages', function (): void {
    $admin  = adminUser();
    $ticket = SupportTicket::factory()->for(customerUser()->customer, 'customer')->create([
        'subject' => 'Ticket bez zpráv',
    ]);

    $result = app(GenerateKbFromTicketAction::class)->handle($ticket, $admin);

    expect($result)->toBeNull();
});

it('GenerateKbFromTicketAction uses ai_classification as article category', function (): void {
    $admin    = adminUser();
    $user     = customerUser();
    $customer = $user->customer;

    $ticket = SupportTicket::factory()->for($customer, 'customer')->create([
        'subject'           => 'Otázka k faktuře',
        'ai_classification' => 'billing',
    ]);
    $ticket->messages()->create([
        'user_id'  => $user->id,
        'is_staff' => false,
        'message'  => 'Jak funguje fakturace za domény?',
    ]);

    $article = app(GenerateKbFromTicketAction::class)->handle($ticket, $admin);

    expect($article?->category)->toBe('billing');
});

// ── Admin KB draft route ───────────────────────────────────────────────────────

it('admin can trigger KB article generation from ticket', function (): void {
    $admin    = adminUser();
    $user     = customerUser();
    $customer = $user->customer;

    $ticket = SupportTicket::factory()->for($customer, 'customer')->create([
        'subject'           => 'Jak obnovit doménu',
        'ai_classification' => 'general',
    ]);
    $ticket->messages()->create([
        'user_id'  => $user->id,
        'is_staff' => false,
        'message'  => 'Jak mohu obnovit expirovanou doménu?',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.support.kb-draft', $ticket))
        ->assertRedirect();

    expect(KbArticle::where('title', 'Jak obnovit doménu')->exists())->toBeTrue();
});

// ── Panel AI chatbot endpoint ──────────────────────────────────────────────────

it('panel ai chat endpoint returns JSON reply for authenticated customer', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->postJson(route('panel.ai.chat'), ['message' => 'Jak si obnovím heslo?'])
        ->assertOk()
        ->assertJsonStructure(['reply'])
        ->assertJsonPath('reply', fn ($v) => is_string($v) && strlen($v) > 0);
});

it('panel ai chat endpoint rejects guests', function (): void {
    $this->postJson(route('panel.ai.chat'), ['message' => 'test'])
        ->assertUnauthorized();
});

it('panel ai chat endpoint validates message is required', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->postJson(route('panel.ai.chat'), [])
        ->assertUnprocessable();
});

it('panel ai chat endpoint rejects messages shorter than 3 characters', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->postJson(route('panel.ai.chat'), ['message' => 'hi'])
        ->assertUnprocessable();
});
