<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Billing\Models\DunningCase;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\Models\TicketMessage;
use Onhost\Domain\Support\Models\WorkOffer;
use Onhost\Domain\Support\TicketStateMachine;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use Tests\TestCase;

/*
 * Work outside the plan needs an approved price (Brain card H29). Support covers the infrastructure; administering
 * the customer's system, their application or custom development is offered on the ticket with a price. The customer
 * approves it, and only then can it be billed — for exactly that price, once.
 */

beforeEach(function () {
    $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

/** A write with a key of its own: a header set with withHeader() stays for every later request of the test. */
function workOfferPost(TestCase $test, string $uri, array $data = []): TestResponse
{
    return $test->withHeader('Idempotency-Key', (string) Str::ulid())->postJson($uri, $data);
}

/** A ticket opened by the customer; returns its id. */
function workOfferTicket(TestCase $test, User $owner): string
{
    $test->actingAs($owner, 'sanctum');

    return (string) workOfferPost($test, '/v1/tickets', ['subject' => 'WordPress po aktualizaci pluginu hlásí chybu 500', 'body' => 'Po aktualizaci pluginu e-shop nejede, potřebujeme to opravit.'])->assertCreated()->json('data.id');
}

it('bills paid work only after the customer approved its price, for exactly that price, once', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'majitel@eshop.test'], ['country' => 'CZ']);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', $this->contextFor($owner, $org), bankProvider: 'comgate');
    $ticketId = workOfferTicket($this, $owner);
    $agent = $this->staff('support_l2');
    $before = app(WalletService::class)->spendable($org, 'CZK')->minor;
    $offers = "/v1/staff/tickets/{$ticketId}/work-offers";

    // support offers the repair of the customer's application: outside the plan, so with a price
    $this->actingAs($agent, 'sanctum');
    workOfferPost($this, $offers, ['scope' => 'infrastructure', 'description' => 'Oprava uzlu po výpadku disku', 'price_net' => '500'])->assertStatus(422)->assertJsonPath('error', 'work_in_scope'); // the infrastructure is what the plan pays for
    $offer = workOfferPost($this, $offers, ['scope' => 'application', 'description' => 'Oprava WordPressu po aktualizaci pluginu: návrat verze a kontrola e-shopu', 'price_net' => '1500', 'minutes' => 90])->assertCreated()->json('data');
    expect($offer['state'])->toBe('proposed')->and($offer['price_net']['minor'])->toBe(150000)->and($offer['valid_until'])->not->toBeNull();

    // work that nobody approved cannot be billed
    workOfferPost($this, "{$offers}/{$offer['id']}/complete")->assertStatus(409)->assertJsonPath('error', 'work_offer_not_approved');
    expect(Invoice::query()->where('organization_id', $org->id)->exists())->toBeFalse()
        ->and(app(WalletService::class)->spendable($org, 'CZK')->minor)->toBe($before);

    // the customer hears about it: in the ticket, by the ordinary reply mail, and as a decision waiting for them
    app(OutboxPublisher::class)->relayPending();
    expect(TicketMessage::query()->where('ticket_id', $ticketId)->where('author_type', 'staff')->orderByDesc('created_at')->first()->body)->toContain('bez DPH')->toContain('nic neúčtujeme');
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'ticket.work_offer.proposed')->sole()->severity)->toBe('warn')
        ->and(MailOutbox::query()->where('template_key', 'ticket-reply')->where('to', 'majitel@eshop.test')->exists())->toBeTrue()
        ->and(Ticket::query()->findOrFail($ticketId)->state)->toBe(TicketStateMachine::WAITING_CUSTOMER);

    // a member who may only write tickets reads the offer and cannot commit the organization's money
    $contact = User::factory()->create(['email' => 'kontakt@eshop.test']);
    app(OrganizationService::class)->attachMember($org, $contact, 'support_contact', CommandContext::system('test'), true);
    $this->actingAs($contact, 'sanctum');
    expect($this->getJson("/v1/tickets/{$ticketId}/work-offers")->assertOk()->json('data.0.id'))->toBe($offer['id']);
    workOfferPost($this, "/v1/tickets/{$ticketId}/work-offers/{$offer['id']}/decision", ['approve' => true])->assertForbidden();
    expect(WorkOffer::query()->findOrFail($offer['id'])->state)->toBe(WorkOffer::PROPOSED);

    // another organization does not see the offer at all
    [$stranger] = $this->customerWithOrganization();
    $this->actingAs($stranger, 'sanctum');
    $this->getJson("/v1/tickets/{$ticketId}/work-offers")->assertForbidden();
    workOfferPost($this, "/v1/tickets/{$ticketId}/work-offers/{$offer['id']}/decision", ['approve' => true])->assertForbidden();

    // the owner approves: nothing is charged yet, the ticket goes back to support
    $this->actingAs($owner, 'sanctum');
    workOfferPost($this, "/v1/tickets/{$ticketId}/work-offers/{$offer['id']}/decision", ['approve' => true])->assertOk()->assertJsonPath('data.state', 'approved');
    $approved = WorkOffer::query()->findOrFail($offer['id']);
    expect($approved->decided_by)->toBe($owner->id)->and($approved->decided_at)->not->toBeNull()
        ->and(app(WalletService::class)->spendable($org, 'CZK')->minor)->toBe($before)
        ->and(Ticket::query()->findOrFail($ticketId)->state)->toBe(TicketStateMachine::OPEN)
        ->and(AuditEvent::query()->where('action', 'ticket.work_offer.approve')->where('resource_id', $offer['id'])->where('actor_id', $owner->id)->exists())->toBeTrue();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('event', 'ticket.work_offer.approved')->exists())->toBeTrue();

    // done: the approved price is billed — 1 500 Kč + 21 % VAT from credit — and a second "done" bills nothing more
    $this->actingAs($agent, 'sanctum');
    $done = workOfferPost($this, "{$offers}/{$offer['id']}/complete")->assertOk()->json('data');
    expect($done['state'])->toBe('completed')->and($done['payment'])->toBe('wallet');
    $invoice = Invoice::query()->findOrFail($done['invoice_id']);
    expect($invoice->organization_id)->toBe($org->id)->and($invoice->subtotal_minor)->toBe(150000)->and($invoice->total_minor)->toBe(181500)->and($invoice->meta['work_offer_id'])->toBe($offer['id'])
        ->and(app(WalletService::class)->spendable($org, 'CZK')->minor)->toBe($before - 181500);
    workOfferPost($this, "{$offers}/{$offer['id']}/complete")->assertOk()->assertJsonPath('data.invoice_id', $invoice->id);
    expect(Invoice::query()->where('organization_id', $org->id)->count())->toBe(1)
        ->and(app(WalletService::class)->spendable($org, 'CZK')->minor)->toBe($before - 181500);
    // … and it can no longer be withdrawn
    workOfferPost($this, "{$offers}/{$offer['id']}/withdraw", ['reason' => 'omyl v zadání'])->assertStatus(409)->assertJsonPath('error', 'work_offer_not_open');
});

it('bills nothing for a declined, withdrawn or expired offer, and invoices approved work when the credit does not cover it', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'majitel@eshop.test'], ['country' => 'CZ']); // no credit at all
    $ticketId = workOfferTicket($this, $owner);
    $agent = $this->staff('support_l2');
    $offers = "/v1/staff/tickets/{$ticketId}/work-offers";
    $decision = fn (string $id) => "/v1/tickets/{$ticketId}/work-offers/{$id}/decision";
    $propose = function (string $price = '800') use ($offers, $agent): string {
        $this->actingAs($agent, 'sanctum');

        return (string) workOfferPost($this, $offers, ['scope' => 'administration', 'description' => 'Nastavení zálohování databáze na serveru zákazníka', 'price_net' => $price])->assertCreated()->json('data.id');
    };

    $declined = $propose();
    $this->actingAs($owner, 'sanctum');
    workOfferPost($this, $decision($declined), ['approve' => false, 'note' => 'Vyřešíme si sami.'])->assertOk()->assertJsonPath('data.state', 'declined');
    workOfferPost($this, $decision($declined), ['approve' => true])->assertStatus(409)->assertJsonPath('error', 'work_offer_not_open'); // a no is not turned into a yes; support makes a new offer

    $withdrawn = $propose();
    workOfferPost($this, "{$offers}/{$withdrawn}/withdraw", ['reason' => 'zákazník problém vyřešil sám'])->assertOk()->assertJsonPath('data.state', 'withdrawn');

    $expired = $propose();
    $this->travel(15)->days();
    $this->actingAs($owner, 'sanctum');
    expect($this->getJson("/v1/tickets/{$ticketId}/work-offers")->assertOk()->json('data.2.state'))->toBe('expired');
    workOfferPost($this, $decision($expired), ['approve' => true])->assertStatus(409)->assertJsonPath('error', 'work_offer_not_open'); // an old price is not a price
    $this->artisan('onhost:support:sla')->assertSuccessful();
    expect(WorkOffer::query()->findOrFail($expired)->state)->toBe(WorkOffer::EXPIRED);

    $this->actingAs($agent, 'sanctum');
    foreach ([$declined, $withdrawn, $expired] as $id) {
        workOfferPost($this, "{$offers}/{$id}/complete")->assertStatus(409)->assertJsonPath('error', 'work_offer_not_approved');
    }
    expect(Invoice::query()->where('organization_id', $org->id)->count())->toBe(0)->and(DB::table('ledger_transactions')->where('organization_id', $org->id)->count())->toBe(0);

    // approved work with an empty wallet: the work was agreed and done, so it is invoiced with the usual due date
    $approved = $propose('2000');
    $this->actingAs($owner, 'sanctum');
    workOfferPost($this, $decision($approved), ['approve' => true])->assertOk();
    $this->actingAs($agent, 'sanctum');
    $done = workOfferPost($this, "{$offers}/{$approved}/complete")->assertOk()->json('data');
    $invoice = Invoice::query()->findOrFail($done['invoice_id']);
    expect($done['payment'])->toBe('invoice')->and($invoice->subtotal_minor)->toBe(200000)->and($invoice->due_at?->isFuture())->toBeTrue()
        ->and(DunningCase::query()->where('invoice_id', $invoice->id)->exists())->toBeTrue()
        ->and(DB::table('ledger_transactions')->where('organization_id', $org->id)->count())->toBe(0);
});
