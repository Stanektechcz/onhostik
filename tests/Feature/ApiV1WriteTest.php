<?php

declare(strict_types=1);

use App\Domains\Billing\Models\Invoice;
use App\Domains\Customer\Models\Customer;
use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// ────────────────────────────────────────────────────────────────────────
// Helpers
// ────────────────────────────────────────────────────────────────────────

function apiUser(): User
{
    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user, ['*']);
    return $user;
}

// ────────────────────────────────────────────────────────────────────────
// Support tickets — store
// ────────────────────────────────────────────────────────────────────────

it('POST /api/v1/support/tickets creates a ticket', function (): void {
    apiUser();

    $this->postJson(route('api.v1.support.tickets.store'), [
        'subject' => 'Testovací tiket',
        'message' => 'Toto je testovací zpráva pro tiket.',
    ])->assertStatus(201)
      ->assertJsonPath('data.subject', 'Testovací tiket')
      ->assertJsonPath('data.status', 'open');
});

it('POST /api/v1/support/tickets validates required fields', function (): void {
    apiUser();

    $this->postJson(route('api.v1.support.tickets.store'), [])
         ->assertStatus(422)
         ->assertJsonValidationErrors(['subject', 'message']);
});

it('POST /api/v1/support/tickets rejects short message', function (): void {
    apiUser();

    $this->postJson(route('api.v1.support.tickets.store'), [
        'subject' => 'Ok subject',
        'message' => 'short',
    ])->assertStatus(422)
      ->assertJsonValidationErrors(['message']);
});

// ────────────────────────────────────────────────────────────────────────
// Support tickets — show
// ────────────────────────────────────────────────────────────────────────

it('GET /api/v1/support/tickets/{ticket} returns ticket with messages', function (): void {
    $user     = apiUser();
    $customer = $user->customer;

    $ticket = SupportTicket::factory()->create([
        'customer_id' => $customer->id,
        'subject'     => 'Detail tiket',
        'status'      => TicketStatus::Open,
    ]);

    $this->getJson(route('api.v1.support.tickets.show', $ticket))
         ->assertOk()
         ->assertJsonPath('data.subject', 'Detail tiket')
         ->assertJsonStructure(['data' => ['id', 'subject', 'status', 'messages']]);
});

it('GET /api/v1/support/tickets/{ticket} returns 403 for another customer ticket', function (): void {
    apiUser();

    $other    = Customer::factory()->create();
    $ticket   = SupportTicket::factory()->create(['customer_id' => $other->id]);

    $this->getJson(route('api.v1.support.tickets.show', $ticket))
         ->assertStatus(403);
});

// ────────────────────────────────────────────────────────────────────────
// Support tickets — reply
// ────────────────────────────────────────────────────────────────────────

it('POST /api/v1/support/tickets/{ticket}/reply adds a reply', function (): void {
    $user     = apiUser();
    $customer = $user->customer;

    $ticket = SupportTicket::factory()->create([
        'customer_id' => $customer->id,
        'status'      => TicketStatus::Open,
    ]);

    $this->postJson(route('api.v1.support.tickets.reply', $ticket), [
        'message' => 'Toto je odpověď zákazníka na tiket.',
    ])->assertStatus(201)
      ->assertJsonStructure(['data' => ['id', 'message', 'created_at']]);
});

it('POST /api/v1/support/tickets/{ticket}/reply returns 422 on closed ticket', function (): void {
    $user     = apiUser();
    $customer = $user->customer;

    $ticket = SupportTicket::factory()->create([
        'customer_id' => $customer->id,
        'status'      => TicketStatus::Closed,
    ]);

    $this->postJson(route('api.v1.support.tickets.reply', $ticket), [
        'message' => 'Pokus o odpověď na uzavřený tiket.',
    ])->assertStatus(422);
});

// ────────────────────────────────────────────────────────────────────────
// Support tickets — close
// ────────────────────────────────────────────────────────────────────────

it('POST /api/v1/support/tickets/{ticket}/close closes the ticket', function (): void {
    $user     = apiUser();
    $customer = $user->customer;

    $ticket = SupportTicket::factory()->create([
        'customer_id' => $customer->id,
        'status'      => TicketStatus::Open,
    ]);

    $this->postJson(route('api.v1.support.tickets.close', $ticket))
         ->assertOk()
         ->assertJsonPath('data.status', 'closed');

    expect($ticket->fresh()->status)->toBe(TicketStatus::Closed);
});

it('POST /api/v1/support/tickets/{ticket}/close returns 422 if already closed', function (): void {
    $user     = apiUser();
    $customer = $user->customer;

    $ticket = SupportTicket::factory()->create([
        'customer_id' => $customer->id,
        'status'      => TicketStatus::Closed,
    ]);

    $this->postJson(route('api.v1.support.tickets.close', $ticket))
         ->assertStatus(422);
});

// ────────────────────────────────────────────────────────────────────────
// Credit topup
// ────────────────────────────────────────────────────────────────────────

it('POST /api/v1/billing/credit/topup creates a topup invoice', function (): void {
    apiUser();

    $this->postJson(route('api.v1.billing.credit.topup'), ['amount' => 500])
         ->assertStatus(201)
         ->assertJsonStructure(['data' => ['invoice_id', 'invoice_number', 'amount', 'currency', 'pay_url']]);
});

it('POST /api/v1/billing/credit/topup validates amount', function (): void {
    apiUser();

    $this->postJson(route('api.v1.billing.credit.topup'), ['amount' => 0])
         ->assertStatus(422)
         ->assertJsonValidationErrors(['amount']);
});

// ────────────────────────────────────────────────────────────────────────
// Token abilities — write:tickets enforcement
// ────────────────────────────────────────────────────────────────────────

it('POST /api/v1/support/tickets returns 403 when token lacks write:tickets', function (): void {
    $user = User::factory()->create();
    Customer::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user, ['read']); // no write:tickets

    $this->postJson(route('api.v1.support.tickets.store'), [
        'subject' => 'Test ability',
        'message' => 'Zpráva pro ověření ability.',
    ])->assertStatus(403)
      ->assertJsonPath('error', 'Token nemá oprávnění write:tickets.');
});

it('POST /api/v1/support/tickets/{ticket}/reply returns 403 when token lacks write:tickets', function (): void {
    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user, ['read']);

    $ticket = SupportTicket::factory()->create(['customer_id' => $customer->id, 'status' => TicketStatus::Open]);

    $this->postJson(route('api.v1.support.tickets.reply', $ticket), ['message' => 'Odpověď'])
         ->assertStatus(403)
         ->assertJsonPath('error', 'Token nemá oprávnění write:tickets.');
});

it('POST /api/v1/support/tickets/{ticket}/close returns 403 when token lacks write:tickets', function (): void {
    $user     = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user, ['read']);

    $ticket = SupportTicket::factory()->create(['customer_id' => $customer->id, 'status' => TicketStatus::Open]);

    $this->postJson(route('api.v1.support.tickets.close', $ticket))
         ->assertStatus(403)
         ->assertJsonPath('error', 'Token nemá oprávnění write:tickets.');
});

it('POST /api/v1/support/tickets succeeds with write:tickets ability', function (): void {
    $user = User::factory()->create();
    Customer::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user, ['read', 'write:tickets']);

    $this->postJson(route('api.v1.support.tickets.store'), [
        'subject' => 'Ticket s write ability',
        'message' => 'Zpráva pro ověření write:tickets ability.',
    ])->assertStatus(201);
});

// ────────────────────────────────────────────────────────────────────────
// Token abilities — write:credit enforcement
// ────────────────────────────────────────────────────────────────────────

it('POST /api/v1/billing/credit/topup returns 403 when token lacks write:credit', function (): void {
    $user = User::factory()->create();
    Customer::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user, ['read']); // no write:credit

    $this->postJson(route('api.v1.billing.credit.topup'), ['amount' => 500])
         ->assertStatus(403)
         ->assertJsonPath('error', 'Token nemá oprávnění write:credit.');
});

it('POST /api/v1/billing/credit/topup succeeds with write:credit ability', function (): void {
    $user = User::factory()->create();
    Customer::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user, ['read', 'write:credit']);

    $this->postJson(route('api.v1.billing.credit.topup'), ['amount' => 500])
         ->assertStatus(201)
         ->assertJsonStructure(['data' => ['invoice_id', 'amount']]);
});

// ────────────────────────────────────────────────────────────────────────
// Token CRUD — abilities in store + JSON destroy
// ────────────────────────────────────────────────────────────────────────

it('POST /api/v1/tokens creates token with requested abilities including read', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'manage:tokens']);

    $response = $this->postJson(route('api.v1.tokens.store'), [
        'name'      => 'Test abilities token',
        'abilities' => ['write:tickets', 'write:credit'],
    ])->assertStatus(201)
      ->assertJsonStructure(['token', 'name', 'abilities', 'created_at']);

    $abilities = $response->json('abilities');
    expect($abilities)->toContain('read')
        ->and($abilities)->toContain('write:tickets')
        ->and($abilities)->toContain('write:credit');
});

it('POST /api/v1/tokens adds read ability even when not requested', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'manage:tokens']);

    $response = $this->postJson(route('api.v1.tokens.store'), [
        'name'      => 'Token without read',
        'abilities' => ['write:tickets'],
    ])->assertStatus(201);

    expect($response->json('abilities'))->toContain('read');
});

it('POST /api/v1/tokens rejects invalid ability', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'manage:tokens']);

    $this->postJson(route('api.v1.tokens.store'), [
        'name'      => 'Bad abilities',
        'abilities' => ['admin:everything'],
    ])->assertStatus(422)
      ->assertJsonValidationErrors(['abilities.0']);
});

it('DELETE /api/v1/tokens/{id} returns JSON response', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'manage:tokens']);

    $token = $user->createToken('To be deleted', ['read']);
    $tokenId = $token->accessToken->id;

    $this->deleteJson(route('api.v1.tokens.destroy', $tokenId))
         ->assertOk()
         ->assertJsonPath('message', 'Token byl odstraněn.');
});

it('DELETE /api/v1/tokens/{id} returns 404 for non-existent token', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'manage:tokens']);

    $this->deleteJson(route('api.v1.tokens.destroy', 99999))
         ->assertStatus(404)
         ->assertJsonPath('error', 'Token nenalezen.');
});

// ────────────────────────────────────────────────────────────────────────
// Monitors
// ────────────────────────────────────────────────────────────────────────

it('GET /api/v1/monitors returns monitors for customer services', function (): void {
    $user     = apiUser();
    $customer = $user->customer;

    $service = Service::factory()->create(['customer_id' => $customer->id]);
    Monitor::factory()->create(['service_id' => $service->id, 'name' => 'Test monitor']);

    $this->getJson(route('api.v1.monitors.index'))
         ->assertOk()
         ->assertJsonPath('data.0.name', 'Test monitor')
         ->assertJsonStructure(['data' => [['id', 'name', 'type', 'status', 'uptime_percent']]]);
});

it('GET /api/v1/monitors returns empty array for customer with no services', function (): void {
    apiUser();

    $this->getJson(route('api.v1.monitors.index'))
         ->assertOk()
         ->assertJsonPath('data', []);
});
