<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Request;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentRefund;
use Onhost\Domain\Payments\PaymentProviderRegistry;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\PaymentProvider;

/*
 * L-09 (docs/legal/LEGAL_REVIEW_2026-10.md): "credit is not paid out" (owner decision G-R4) has a statutory exception. A consumer
 * who is owed money by law — a price reduction or a refund for a defective digital service, a contract the provider ended without
 * the customer's breach, a paid order that cannot be delivered — gets it back the way they paid. Finance does it behind a fresh
 * step-up (four eyes above the approval threshold), with the customer's claim on record (a ticket), for the payment of an ORDER
 * only (a top-up stays credit):
 *
 *  · money not given back yet: a credit note of the order's document, paid out to the card or the account (as G6 does);
 *  · money a credit note already put on the credit (the settlement of a failed order, a provider's cancellation): moved from the
 *    credit to the original payment method — never more than that credit note returned, nor than the credit still has.
 */

/** A card gateway that confirms every refund at once and counts the calls — nothing leaves the test. */
final class L09CardGateway implements PaymentProvider
{
    public static int $refunds = 0;

    public static function providerKey(): string
    {
        return 'l09card';
    }

    public function supportedMethods(): array
    {
        return ['card'];
    }

    public function createPaymentIntent(Money $amount, array $input): array
    {
        throw new LogicException('not used');
    }

    public function getPaymentStatus(string $providerId): array
    {
        throw new LogicException('not used');
    }

    public function capture(string $providerId, ?Money $amount = null): array
    {
        throw new LogicException('not used');
    }

    public function cancel(string $providerId): array
    {
        throw new LogicException('not used');
    }

    public function refund(string $providerId, Money $amount, string $idempotencyKey, ?string $reason = null): array
    {
        self::$refunds++;

        return ['provider_refund_id' => 'l09-rf-'.self::$refunds, 'state' => 'succeeded', 'raw' => []];
    }

    public function verifyWebhook(Request $request): array
    {
        throw new LogicException('not used');
    }

    public function reconcile(string $periodStart, string $periodEnd): array
    {
        return [];
    }
}

beforeEach(function () {
    $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]);
    L09CardGateway::$refunds = 0;
    $registry = new PaymentProviderRegistry(app());
    $registry->register('l09card', L09CardGateway::class);
    app()->instance(PaymentProviderRegistry::class, $registry);
});

/**
 * A consumer's order (two lines, 605 + 121 CZK gross), its statement, and the card payment of the order that paid it.
 *
 * @return array{0:Order, 1:Invoice, 2:PaymentIntent, 3:?Service}
 */
function l09PaidOrder(Organization $org, string $class = 'b2c', ?string $serviceState = null, string $purpose = 'order'): array
{
    $service = $serviceState === null ? null : Service::query()->create(['organization_id' => $org->id, 'family' => 'web', 'product_key' => 'web-pro', 'name' => 'l09-web-'.uniqid(), 'state' => $serviceState]);
    $placed = now()->subDays(40);
    $order = Order::query()->create(['number' => 'ON-L9'.substr(uniqid(), -6), 'organization_id' => $org->id, 'state' => OrderStateMachine::ACTIVE, 'currency' => 'CZK', 'subtotal_minor' => 60000, 'tax_minor' => 12600, 'total_minor' => 72600,
        'payment_mode' => 'gateway', 'idempotency_key' => 'l09-order-'.uniqid(), 'placed_at' => $placed, 'paid_at' => $placed, 'meta' => ['customer_class' => $class]]);
    foreach ([['web-pro', 'Webhosting Pro', 50000, 10500, 60500], ['backup', 'Zálohy navíc', 10000, 2100, 12100]] as [$sku, $name, $net, $tax, $total]) {
        OrderItem::query()->create(['order_id' => $order->id, 'sku' => $sku, 'product_key' => $sku, 'name' => $name, 'qty' => 1, 'unit_net_minor' => $net, 'tax_rate' => '21', 'tax_minor' => $tax, 'total_minor' => $total, 'period' => 'month', 'config' => ['family' => 'web'], 'state' => 'active', 'service_id' => $sku === 'web-pro' ? $service?->id : null]);
    }
    $statement = app(InvoiceService::class)->issueForOrder($order, CommandContext::system('test')->withScope($org->id), 'card');
    $intent = PaymentIntent::query()->create(['organization_id' => $org->id, 'provider' => 'l09card', 'provider_id' => 'l09-'.uniqid(), 'purpose' => $purpose, 'reference_type' => $purpose === 'topup' ? null : 'order', 'reference_id' => $purpose === 'topup' ? null : $order->id,
        'amount_minor' => 72600, 'currency' => 'CZK', 'state' => 'SUCCEEDED', 'idempotency_key' => 'l09-pi-'.uniqid(), 'paid_at' => $placed]);
    app(OutboxPublisher::class)->relayPending();

    return [$order, $statement->refresh(), $intent, $service];
}

/** @param array<string,mixed> $extra */
function l09Body(Order $order, float $amount, string $basis, array $extra = []): array
{
    $ticket = Ticket::query()->create(['number' => 'TK-2026-'.random_int(10000, 99999), 'organization_id' => $order->organization_id, 'email' => 'owner@example.test', 'subject' => 'Reklamace']);

    return array_merge(['amount' => $amount, 'basis' => $basis, 'reason' => 'Zákonný nárok spotřebitele, vrácení na kartu.', 'ticket_id' => $ticket->id], $extra);
}

it('pays a price reduction for a defect back to the card with a credit note of the order document, while the service keeps running', function () {
    [, $org] = $this->customerWithOrganization();
    [$order, $statement, $intent] = l09PaidOrder($org, 'b2c', 'active');
    $finance = $this->staff('billing_finance_admin');
    $this->actingAs($finance, 'sanctum');
    $this->withHeader('Idempotency-Key', 'l09-d-0')->postJson("/v1/staff/payments/{$intent->id}/refund-statutory", l09Body($order, 100.0, 'defect'))->assertForbidden(); // no fresh step-up
    expect(L09CardGateway::$refunds)->toBe(0);

    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');
    $answer = $this->withHeader('Idempotency-Key', 'l09-d-1')->postJson("/v1/staff/payments/{$intent->id}/refund-statutory", l09Body($order, 100.0, 'defect'))->assertOk()->json();

    $note = Invoice::query()->where('type', 'credit_note')->where('corrects_invoice_id', $statement->id)->sole();
    expect($answer['state'])->toBe('succeeded')->and($answer['credit_note'])->toBe($note->number)->and((int) $note->total_minor)->toBe(-10000)
        ->and((int) PaymentRefund::query()->sole()->amount_minor)->toBe(10000)->and(L09CardGateway::$refunds)->toBe(1)
        ->and(app(WalletService::class)->balances($org, 'CZK')['posted']->minor)->toBe(0)
        ->and(app(LedgerService::class)->balance('liability:refund_payable:l09card:CZK', 'CZK')->minor)->toBe(0)
        ->and(app(LedgerService::class)->verifyInvariant()['balanced'])->toBeTrue();
});

it('moves money a credit note already put on the credit back to the card — never more than it returned nor than the credit has', function () {
    [, $org] = $this->customerWithOrganization();
    [$order, $statement, $intent] = l09PaidOrder($org);
    $ctx = CommandContext::system('test')->withScope($org->id);
    // the order could not be delivered: the settlement gave all of it back to the credit, as the system does today
    $given = app(InvoiceService::class)->giveBack($statement, null, 'Objednávku nelze dodat', $ctx);
    $wallets = app(WalletService::class);
    expect((int) $given['to_credit_minor'])->toBe(72600)->and($wallets->balances($org, 'CZK')['available']->minor)->toBe(72600);
    $note = $given['credit_note'];
    $wallets->charge($org, Money::minor(10000, 'CZK'), 'web', 'l09-spent', $ctx); // the customer spent part of it meanwhile
    $finance = $this->staff('billing_finance_admin');
    $this->actingAs($finance, 'sanctum');
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');

    $this->withHeader('Idempotency-Key', 'l09-u-0')->postJson("/v1/staff/payments/{$intent->id}/refund-statutory", l09Body($order, 726.0, 'undeliverable', ['credit_note_id' => $note->id]))
        ->assertStatus(409)->assertJsonPath('error', 'refund_exceeds_credit');
    $answer = $this->withHeader('Idempotency-Key', 'l09-u-1')->postJson("/v1/staff/payments/{$intent->id}/refund-statutory", l09Body($order, 626.0, 'undeliverable', ['credit_note_id' => $note->id]))->assertOk()->json();

    expect($answer['state'])->toBe('succeeded')->and((int) PaymentRefund::query()->sole()->amount_minor)->toBe(62600)->and(PaymentRefund::query()->sole()->credit_note_id)->toBe($note->id)
        ->and($wallets->balances($org, 'CZK')['posted']->minor)->toBe(0)->and(L09CardGateway::$refunds)->toBe(1)
        ->and(Invoice::query()->where('type', 'credit_note')->count())->toBe(1) // no second credit note: the document was corrected once
        ->and(app(LedgerService::class)->balance('liability:refund_payable:l09card:CZK', 'CZK')->minor)->toBe(0)
        ->and(app(LedgerService::class)->verifyInvariant()['balanced'])->toBeTrue();

    // the credit gets money again (a top-up): the rest of what the order is owed can go back now — the 100 CZK the customer used
    // meanwhile stay paid by the top-up they made — and never a haler more than the credit note returned
    $wallets->topup($org, Money::decimal('500', 'CZK'), 'card', 'l09-later', $ctx, bankProvider: 'comgate');
    $this->withHeader('Idempotency-Key', 'l09-u-2')->postJson("/v1/staff/payments/{$intent->id}/refund-statutory", l09Body($order, 100.0, 'undeliverable', ['credit_note_id' => $note->id]))->assertOk();
    $this->withHeader('Idempotency-Key', 'l09-u-3')->postJson("/v1/staff/payments/{$intent->id}/refund-statutory", l09Body($order, 0.01, 'undeliverable', ['credit_note_id' => $note->id]))
        ->assertStatus(409);
    expect(L09CardGateway::$refunds)->toBe(2)->and((int) $intent->fresh()->refunded_minor)->toBe(72600)->and($wallets->balances($org, 'CZK')['available']->minor)->toBe(40000)
        ->and(app(LedgerService::class)->verifyInvariant()['balanced'])->toBeTrue();
});

it('refuses what the law does not owe: a business order, an unknown basis, a top-up, a running service on a provider termination, a foreign credit note', function () {
    [, $org] = $this->customerWithOrganization();
    $finance = $this->staff('billing_finance_admin');
    $this->actingAs($finance, 'sanctum');
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');

    [$firm, , $firmIntent] = l09PaidOrder($org, 'b2b');
    $this->withHeader('Idempotency-Key', 'l09-r-1')->postJson("/v1/staff/payments/{$firmIntent->id}/refund-statutory", l09Body($firm, 10.0, 'defect'))->assertStatus(403)->assertJsonPath('error', 'statutory_refund_consumers_only');

    [$order, , $intent] = l09PaidOrder($org, 'b2c', 'active');
    $this->withHeader('Idempotency-Key', 'l09-r-2')->postJson("/v1/staff/payments/{$intent->id}/refund-statutory", l09Body($order, 10.0, 'goodwill'))->assertStatus(422);
    $this->withHeader('Idempotency-Key', 'l09-r-3')->postJson("/v1/staff/payments/{$intent->id}/refund-statutory", l09Body($order, 10.0, 'provider_termination'))->assertStatus(409)->assertJsonPath('error', 'refund_service_still_running');

    [$topupOrder, , $topup] = l09PaidOrder($org, 'b2c', null, 'topup');
    $this->withHeader('Idempotency-Key', 'l09-r-4')->postJson("/v1/staff/payments/{$topup->id}/refund-statutory", l09Body($topupOrder, 10.0, 'defect'))->assertStatus(422)->assertJsonPath('error', 'topup_not_refundable');

    [, $otherStatement] = l09PaidOrder($org);
    $foreign = app(InvoiceService::class)->giveBack($otherStatement, null, 'Jiná objednávka', CommandContext::system('test')->withScope($org->id))['credit_note'];
    $this->withHeader('Idempotency-Key', 'l09-r-5')->postJson("/v1/staff/payments/{$intent->id}/refund-statutory", l09Body($order, 10.0, 'undeliverable', ['credit_note_id' => $foreign->id]))->assertStatus(422)->assertJsonPath('error', 'refund_credit_note_mismatch');

    // the claim is on record: a ticket of this customer
    $this->withHeader('Idempotency-Key', 'l09-r-6')->postJson("/v1/staff/payments/{$intent->id}/refund-statutory", l09Body($order, 10.0, 'defect', ['ticket_id' => 'tk_nope']))->assertStatus(422)->assertJsonPath('error', 'refund_evidence_mismatch');
    expect(L09CardGateway::$refunds)->toBe(0)->and(PaymentRefund::query()->count())->toBe(0);
});
