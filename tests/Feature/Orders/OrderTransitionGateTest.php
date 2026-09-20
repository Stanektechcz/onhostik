<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\Models\InvoiceLine;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\Models\CreditLine;
use Onhost\Domain\WalletLedger\Models\WalletHold;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * The state of an order follows its money and its lines; nobody writes it by hand. The one thing a person may do to an
 * order is cancel it — the customer while it is unpaid, staff also after the payment (what was paid comes back and the
 * document is corrected). "Paid" comes from a payment, "active" from the lines.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Queue::fake();
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
});

function orderGateConsents(): array
{
    return ['terms' => ['version' => '4.0'], 'privacy' => ['version' => '4.0'], 'withdrawal_waiver' => ['version' => '4.0'], 'dpa' => ['version' => '4.0'], 'sla' => ['version' => '4.0']];
}

function orderGatePlace(Organization $org, User $buyer, string $mode, string $key): Order
{
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'profi']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c'], 12, null, $org);
    $ctx = new CommandContext('user', $buyer->id, $org->id, null, '127.0.0.1', 'pest', 'test-session');

    return app(CheckoutService::class)->placeOrder($quote, $org, $buyer, orderGateConsents(), ['mode' => $mode], $key, $ctx)['order']->refresh();
}

it('does not let anybody declare an order paid: a support agent with an organization of their own got their services for nothing', function () {
    // a support agent is also somebody's customer — here of an organization they own themselves
    $agent = $this->staff('support_l2');
    $org = app(OrganizationService::class)->create($agent, ['name' => 'Vlastní s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK'], CommandContext::system('test'));
    $order = orderGatePlace($org, $agent, 'bank', 'gate-1');
    expect($order->state)->toBe(OrderStateMachine::PENDING_PAYMENT);

    $this->actingAs($agent, 'sanctum');
    $this->postJson("/v1/orders/{$order->id}/transition", ['to' => 'paid', 'reason' => 'zaplaceno hotově'])->assertStatus(422)->assertJsonPath('error', 'order_transition_not_offered');
    $this->postJson("/v1/staff/orders/{$order->id}/transition", ['state' => 'PAID', 'reason' => 'zaplaceno hotově'])->assertStatus(422)->assertJsonPath('error', 'order_transition_not_offered');
    app(OutboxPublisher::class)->relayPending();

    expect($order->refresh()->state)->toBe(OrderStateMachine::PENDING_PAYMENT)->and($order->paid_at)->toBeNull()
        ->and(Service::query()->where('organization_id', $order->organization_id)->count())->toBe(0);

    // …nor active, nor anything else a payment or a delivered line decides
    foreach (['provisioning', 'active', 'suspended', 'failed', 'partially_active'] as $to) {
        $this->postJson("/v1/orders/{$order->id}/transition", ['to' => $to])->assertStatus(422);
    }
    expect($order->refresh()->state)->toBe(OrderStateMachine::PENDING_PAYMENT);
});

it('asks the permission that places orders for cancelling one: a read-only member cancelled the orders of others', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $order = orderGatePlace($org, $owner, 'bank', 'gate-2');
    $viewer = $this->customer(['email' => 'ctenar@example.cz']);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $viewer->id, 'role_key' => 'viewer', 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id]);
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $viewer->id, 'state' => 'active', 'role_key' => 'viewer', 'joined_at' => now()]);

    $this->actingAs($viewer, 'sanctum');
    $this->getJson("/v1/orders/{$order->id}")->assertOk(); // reading it is theirs
    $this->postJson("/v1/orders/{$order->id}/transition", ['to' => 'cancelled'])->assertForbidden();
    expect($order->refresh()->state)->toBe(OrderStateMachine::PENDING_PAYMENT)->and(Invoice::query()->findOrFail($order->invoice_id)->state)->toBe(Invoice::ISSUED);

    // the owner may — by `to` or, as the panel store sends it, by `state`
    $this->actingAs($owner, 'sanctum');
    $this->postJson("/v1/orders/{$order->id}/transition", ['state' => 'CANCELLED', 'reason' => 'rozmyslel jsem si to'])->assertOk()->assertJsonPath('data.state', 'CANCELLED');
});

it('gives a paid order back when staff cancel it: the reservation, the lines and the document', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'x@mailinator.com'], ['type' => 'company', 'billing_email' => 'x@mailinator.com']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('20000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $order = orderGatePlace($org, $owner, 'wallet', 'gate-3'); // a new account with a disposable mailbox: paid, documented, held for the review
    expect($order->state)->toBe(OrderStateMachine::PAID)->and($order->meta['review']['state'])->toBe('pending');
    $statement = Invoice::query()->findOrFail($order->invoice_id);
    expect($statement->type)->toBe('statement')->and($statement->state)->toBe(Invoice::PAID);
    $available = fn () => app(WalletService::class)->balances($org, 'CZK')['available']->minor;
    expect($available())->toBe(2000000 - (int) $order->total_minor);

    // the customer cannot: it is paid
    $this->actingAs($owner, 'sanctum');
    $this->postJson("/v1/orders/{$order->id}/transition", ['to' => 'cancelled'])->assertForbidden();

    $staff = $this->staff('support_l2');
    $this->actingAs($staff, 'sanctum');
    $this->postJson("/v1/staff/orders/{$order->id}/transition", ['state' => 'CANCELLED'])->assertStatus(422)->assertJsonPath('error', 'reason_required'); // a paid order is not cancelled without saying why
    $this->withHeader('Idempotency-Key', 'gate-3-cancel')->postJson("/v1/staff/orders/{$order->id}/transition", ['state' => 'CANCELLED', 'reason' => 'Zákazník požádal o zrušení telefonicky'])->assertOk()->assertJsonPath('data.state', 'CANCELLED');
    $this->flushHeaders();

    // the money is the customer's again, and the statement no longer says they bought something
    expect($available())->toBe(2000000)->and(WalletHold::query()->findOrFail($order->wallet_hold_id)->state)->toBe('released');
    $statement->refresh();
    $note = Invoice::query()->where('corrects_invoice_id', $statement->id)->where('type', 'credit_note')->firstOrFail();
    expect($statement->state)->toBe(Invoice::CREDITED)->and($statement->credited_minor)->toBe($statement->total_minor)
        ->and($note->total_minor)->toBe(-$statement->total_minor)->and($note->tax_minor)->toBe(-$statement->tax_minor)->and($note->meta['reason'])->toContain($order->number)->toContain('telefonicky')
        ->and(InvoiceLine::query()->where('invoice_id', $note->id)->whereNull('corrects_line_id')->count())->toBe(0)
        ->and($order->items()->pluck('state')->unique()->all())->toBe(['refunded']);
    // no ledger entry was needed: the money never left the credit
    expect(app(LedgerService::class)->balance(LedgerService::vatAccount('CZK'), 'CZK')->minor)->toBe(0);

    app(OutboxPublisher::class)->relayPending();
    expect(Service::query()->where('organization_id', $org->id)->count())->toBe(0); // and nothing was provisioned
    $told = Notification::query()->where('organization_id', $org->id)->where('title', "Objednávka {$order->number} byla zrušena")->firstOrFail();
    expect($told->body)->toContain($note->number)->toContain('kredit');

    // once: a second cancellation has nothing to cancel, and the document cannot be credited again
    $this->withHeader('Idempotency-Key', 'gate-3-again')->postJson("/v1/staff/orders/{$order->id}/transition", ['state' => 'CANCELLED', 'reason' => 'ještě jednou'])->assertStatus(409)->assertJsonPath('error', 'order_not_cancellable');
    $this->flushHeaders();
    expect(Invoice::query()->where('corrects_invoice_id', $statement->id)->count())->toBe(1);
});

it('takes a cancelled postpaid order off the customer\'s debt — revenue and VAT included', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'y@mailinator.com'], ['type' => 'company', 'billing_email' => 'y@mailinator.com', 'billing_mode' => 'postpaid']);
    CreditLine::query()->create(['organization_id' => $org->id, 'currency' => 'CZK', 'limit_minor' => 5000000, 'risk_hold_minor' => 0, 'state' => 'approved']);
    $order = orderGatePlace($org, $owner, 'postpaid', 'gate-4');
    expect($order->state)->toBe(OrderStateMachine::PAID)->and($order->meta['review']['state'] ?? null)->toBe('pending');
    $invoice = Invoice::query()->findOrFail($order->invoice_id);
    $ledger = app(LedgerService::class);
    $receivable = fn () => $ledger->balance(LedgerService::receivableAccount($org->id, 'CZK'), 'CZK')->minor;
    $vat = fn () => $ledger->balance(LedgerService::vatAccount('CZK'), 'CZK')->minor;
    expect($invoice->type)->toBe('invoice')->and($invoice->state)->toBe(Invoice::ISSUED)->and($receivable())->toBe($invoice->total_minor)->and($vat())->toBe($invoice->tax_minor)->and($invoice->tax_minor)->toBeGreaterThan(0);

    $this->actingAs($this->staff('support_l2'), 'sanctum');
    $this->withHeader('Idempotency-Key', 'gate-4-reject')->postJson("/v1/staff/orders/{$order->id}/review", ['decision' => 'reject', 'reason' => 'Podezřelá objednávka'])->assertOk();
    $this->flushHeaders();

    // the customer owes nothing for an order that was never delivered; the VAT is not owed to the state either
    $invoice->refresh();
    expect($order->refresh()->state)->toBe(OrderStateMachine::CANCELLED)->and($invoice->state)->toBe(Invoice::CREDITED)->and($invoice->outstanding()->minor)->toBe(0)
        ->and($receivable())->toBe(0)->and($vat())->toBe(0)
        ->and($ledger->balance(LedgerService::revenueAccount('credit_note', 'CZK'), 'CZK')->minor)->toBe(-($invoice->total_minor - $invoice->tax_minor))
        ->and($ledger->verifyInvariant()['balanced'])->toBeTrue();
});

it('does not cancel an order whose services run; those are cancelled themselves', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $order = orderGatePlace($org, $owner, 'bank', 'gate-5');
    $order->forceFill(['state' => OrderStateMachine::ACTIVE, 'paid_at' => now()])->save();
    $this->actingAs($this->staff('support_l2'), 'sanctum');
    $this->postJson("/v1/staff/orders/{$order->id}/transition", ['state' => 'CANCELLED', 'reason' => 'na přání zákazníka'])->assertStatus(409)->assertJsonPath('error', 'order_not_cancellable');
    expect($order->refresh()->state)->toBe(OrderStateMachine::ACTIVE);

    // an order that runs without a payment and a tax document is what the old door produced: the doctor names it
    Artisan::call('onhost:doctor', ['--json' => true]);
    $checks = collect(json_decode(trim(Artisan::output()), true)['checks'])->keyBy('check');
    expect($checks['every order being delivered was paid and documented']['status'])->toBe('WARN')->and($checks['every order being delivered was paid and documented']['detail'])->toContain($order->number)
        ->and($checks['no document is credited for more than it was issued for']['status'])->toBe('OK');
});
