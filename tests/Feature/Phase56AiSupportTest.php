<?php

declare(strict_types=1);

use App\Domains\Customer\Models\Customer;
use App\Domains\Support\Actions\AnalyseTicketAction;
use App\Domains\Support\Enums\TicketPriority;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Services\TicketService;
use App\Models\User;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── AnalyseTicketAction ────────────────────────────────────────────────────────

it('AnalyseTicketAction fills ai fields on ticket', function (): void {
    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $ticket = openTestTicket($customer, $user, 'Faktura se nezobrazuje v systému', 'Dobrý den, nemůžu najít svoji fakturu.');

    $action = app(AnalyseTicketAction::class);
    $action->handle($ticket, $user);

    $ticket->refresh();
    expect($ticket->ai_analysed_at)->not->toBeNull();
    expect($ticket->ai_classification)->toBeString()->not->toBeEmpty();
    expect($ticket->ai_sentiment)->toBeIn(['positive', 'negative', 'neutral']);
});

it('AnalyseTicketAction classifies billing tickets correctly', function (): void {
    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $ticket = openTestTicket($customer, $user, 'Problém s fakturou a platbou', 'Nemůžu zaplatit fakturu.');

    $action = app(AnalyseTicketAction::class);
    $action->handle($ticket, $user);

    $ticket->refresh();
    expect($ticket->ai_classification)->toBe('billing');
});

it('AnalyseTicketAction classifies technical tickets correctly', function (): void {
    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $ticket = openTestTicket($customer, $user, 'DNS server nefunguje', 'Moje doména nemá správné DNS záznamy.');

    $action = app(AnalyseTicketAction::class);
    $action->handle($ticket, $user);

    $ticket->refresh();
    expect($ticket->ai_classification)->toBe('technical');
});

it('AnalyseTicketAction detects negative sentiment', function (): void {
    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $ticket = openTestTicket($customer, $user, 'Server nefunguje', 'Je tu problém s chybou. Nefunguje to vůbec.');

    $action = app(AnalyseTicketAction::class);
    $action->handle($ticket, $user);

    $ticket->refresh();
    expect($ticket->ai_sentiment)->toBe('negative');
});

it('AnalyseTicketAction does not throw if ticket has no messages', function (): void {
    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $ticket = SupportTicket::create([
        'customer_id' => $customer->id,
        'subject'     => 'Empty ticket',
        'status'      => 'open',
        'priority'    => 'normal',
    ]);

    $action = app(AnalyseTicketAction::class);

    expect(fn () => $action->handle($ticket, $user))->not->toThrow(\Throwable::class);
});

// ── Panel ticket creation triggers analysis ────────────────────────────────────

it('creating a ticket via panel triggers AI analysis', function (): void {
    $user     = User::factory()->create();
    Customer::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
         ->post(route('panel.support.store'), [
             'subject' => 'Problém s fakturou a platbou',
             'message' => 'Nemůžu zaplatit fakturu v systému.',
         ])
         ->assertRedirect();

    $ticket = SupportTicket::latest('id')->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->ai_analysed_at)->not->toBeNull();
    expect($ticket->ai_classification)->toBe('billing');
});

// ── Panel ticket show includes kbArticles ─────────────────────────────────────

it('panel ticket show passes kbArticles to view', function (): void {
    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $ticket   = openTestTicket($customer, $user, 'Problém s fakturou', 'Potřebuji pomoc s fakturou.');

    $response = $this->actingAs($user)->get(route('panel.support.show', $ticket));
    $response->assertOk()->assertViewHas('kbArticles');
});

// ── Admin AI analyse endpoint ──────────────────────────────────────────────────

it('admin can trigger AI analysis on a ticket', function (): void {
    Role::findOrCreate('admin', 'web');
    $admin    = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $admin->assignRole('admin');

    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $ticket   = openTestTicket($customer, $user, 'DNS domain technický problém server', 'Nefunguje doménový server.');

    $this->actingAs($admin)
         ->post(route('admin.support.ai-analyse', $ticket))
         ->assertRedirect();

    $ticket->refresh();
    expect($ticket->ai_analysed_at)->not->toBeNull();
    expect($ticket->ai_classification)->toBe('technical');
});

it('admin ai-analyse endpoint is forbidden for regular users', function (): void {
    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $ticket   = openTestTicket($customer, $user, 'Test', 'Test message.');

    $other = User::factory()->create();
    $this->actingAs($other)
         ->post(route('admin.support.ai-analyse', $ticket))
         ->assertForbidden();
});

// ── Helper ─────────────────────────────────────────────────────────────────────

function openTestTicket(Customer $customer, User $author, string $subject, string $message): SupportTicket
{
    $service = app(TicketService::class);
    return $service->open($customer, $author, $subject, $message, TicketPriority::Normal);
}
