<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Request;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentRefund;
use Onhost\Domain\Payments\PaymentProviderRegistry;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\Models\WalletTopup;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\PaymentProvider;
use Onhost\Providers\Payments\Bank\BankTransferPaymentProvider;
use Tests\TestCase;

/*
 * G6 (owner decisions G-R1, G-R4): a consumer who withdrew within fourteen days and did NOT agree to take the money as credit
 * gets the payment of the ORDER back to where it came from. Finance does it — a staff bus command behind a fresh step-up (four
 * eyes above the refund approval threshold) — for an order payment only (a top-up became credit, and credit is never paid out
 * in money), never more than is left of that payment nor of the order's document, and always with a credit note of the order's
 * document. A card refund the gateway confirms at once is announced (`payment.refunded`: the customer is told by mail and in
 * the panel, loyalty takes the payment's points back); a bank refund stays pending until finance confirms the payout was sent.
 */

/** A card gateway that confirms every refund at once and counts the calls — nothing leaves the test. */
final class G6CardGateway implements PaymentProvider
{
    public static int $refunds = 0;

    public static function providerKey(): string
    {
        return 'g6card';
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

        return ['provider_refund_id' => 'g6-rf-'.self::$refunds, 'state' => 'succeeded', 'raw' => []];
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
    G6CardGateway::$refunds = 0;
    $registry = new PaymentProviderRegistry(app());
    $registry->register('g6card', G6CardGateway::class);
    $registry->register('bank', BankTransferPaymentProvider::class);
    app()->instance(PaymentProviderRegistry::class, $registry);
});

/**
 * A consumer's order placed `$daysAgo` days ago, two lines (500 + 100 CZK net, 21 %), its statement and the order payment
 * that paid it (`$provider`, purpose order).
 *
 * @return array{0:Order, 1:Invoice, 2:PaymentIntent}
 */
function g6PaidOrder(Organization $org, string $provider = 'g6card', string $class = 'b2c', int $daysAgo = 3, string $purpose = 'order'): array
{
    $placed = now()->subDays($daysAgo);
    $order = Order::query()->create(['number' => 'ON-G6'.substr(uniqid(), -6), 'organization_id' => $org->id, 'state' => OrderStateMachine::ACTIVE, 'currency' => 'CZK', 'subtotal_minor' => 60000, 'tax_minor' => 12600, 'total_minor' => 72600,
        'payment_mode' => 'wallet', 'idempotency_key' => 'g6-order-'.uniqid(), 'placed_at' => $placed, 'paid_at' => $placed, 'meta' => ['customer_class' => $class]]);
    foreach ([['web-pro', 'Webhosting Pro', 50000, 10500, 60500], ['backup', 'Zálohy navíc', 10000, 2100, 12100]] as [$sku, $name, $net, $tax, $total]) {
        OrderItem::query()->create(['order_id' => $order->id, 'sku' => $sku, 'product_key' => $sku, 'name' => $name, 'qty' => 1, 'unit_net_minor' => $net, 'tax_rate' => '21', 'tax_minor' => $tax, 'total_minor' => $total, 'period' => 'month', 'config' => ['family' => 'web'], 'state' => 'active']);
    }
    $statement = app(InvoiceService::class)->issueForOrder($order, CommandContext::system('test')->withScope($org->id), 'card');
    $intent = PaymentIntent::query()->create(['organization_id' => $org->id, 'provider' => $provider, 'provider_id' => 'g6-'.uniqid(), 'purpose' => $purpose, 'reference_type' => $purpose === 'topup' ? null : 'order', 'reference_id' => $purpose === 'topup' ? null : $order->id,
        'amount_minor' => 72600, 'currency' => 'CZK', 'state' => 'SUCCEEDED', 'idempotency_key' => 'g6-pi-'.uniqid(), 'paid_at' => $placed]);
    app(OutboxPublisher::class)->relayPending();

    return [$order, $statement->refresh(), $intent];
}

/** @param array<string,mixed> $extra */
function g6RefundBody(Order $order, float $amount, array $extra = []): array
{
    return ['amount' => $amount, 'sent_at' => now()->subDay()->toIso8601String(), 'reason' => 'Odstoupení e-mailem, peníze zpět na kartu.'] + $extra;
}

it('refunds an order payment to the card behind a step-up, with a credit note of the order\'s document, and tells the customer', function () {
    [, $org] = $this->customerWithOrganization();
    [$order, $statement, $intent] = g6PaidOrder($org);
    $finance = $this->staff('billing_finance_admin');
    $this->actingAs($finance, 'sanctum');
    $body = g6RefundBody($order, 242.0);

    // no fresh step-up: refused before anything is paid out
    $this->withHeader('Idempotency-Key', 'g6-card-1')->postJson("/v1/staff/payments/{$intent->id}/refund", $body)->assertForbidden();
    expect(G6CardGateway::$refunds)->toBe(0);

    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');
    $answer = $this->withHeader('Idempotency-Key', 'g6-card-1')->postJson("/v1/staff/payments/{$intent->id}/refund", $body)->assertOk()->json();
    app(OutboxPublisher::class)->relayPending();

    $refund = PaymentRefund::query()->sole();
    $note = Invoice::query()->where('type', 'credit_note')->where('corrects_invoice_id', $statement->id)->sole();
    expect($answer['state'])->toBe('succeeded')->and($answer['credit_note'])->toBe($note->number)
        ->and($refund->state)->toBe('succeeded')->and((int) $refund->amount_minor)->toBe(24200)->and($refund->credit_note_id)->toBe($note->id)
        ->and((int) $note->total_minor)->toBe(-24200)
        ->and((int) $statement->fresh()->credited_minor)->toBe(24200)
        ->and((int) $intent->fresh()->refunded_minor)->toBe(24200)->and($intent->fresh()->state)->toBe('PARTIALLY_REFUNDED')
        ->and(G6CardGateway::$refunds)->toBe(1);
    // the money went to the card, not to the credit: nothing came back to the wallet
    expect(WalletTopup::query()->where('organization_id', $org->id)->where('source', 'return')->count())->toBe(0);
    // revenue and VAT go back, the payout leaves the gateway's account (through the refund payable, which ends at zero)
    $ledger = app(LedgerService::class);
    expect($ledger->balance(LedgerService::bankAccount('g6card', 'CZK'), 'CZK')->minor)->toBe(-24200)
        ->and($ledger->balance('liability:refund_payable:g6card:CZK', 'CZK')->minor)->toBe(0)
        ->and($ledger->verifyInvariant()['balanced'])->toBeTrue();
    // announced once: the customer hears it in the panel and by mail, in their language
    $event = OutboxMessage::query()->where('name', 'payment.refunded')->where('aggregate_id', $intent->id)->sole();
    expect(data_get($event->payload, 'credit_note'))->toBe($note->number)
        ->and(Notification::query()->where('event', 'payment.refunded')->where('organization_id', $org->id)->count())->toBe(1)
        ->and(MailOutbox::query()->where('template_key', 'payment-refunded')->where('organization_id', $org->id)->count())->toBe(1);

    // the same request again is the same refund: no second payout, no second credit note
    $this->withHeader('Idempotency-Key', 'g6-card-1')->postJson("/v1/staff/payments/{$intent->id}/refund", $body)->assertOk();
    expect(G6CardGateway::$refunds)->toBe(1)->and(Invoice::query()->where('type', 'credit_note')->count())->toBe(1);
});

it('refunds only an order payment: a top-up is credit and is never paid out, and support may not refund at all', function () {
    [, $org] = $this->customerWithOrganization();
    [$order, , $topup] = g6PaidOrder($org, purpose: 'topup');
    $support = $this->staff('support_l1');
    $this->actingAs($support, 'sanctum');
    app(StepUpService::class)->grant($support, 'totp', null, '127.0.0.1');
    $this->withHeader('Idempotency-Key', 'g6-support')->postJson("/v1/staff/payments/{$topup->id}/refund", g6RefundBody($order, 100.0))->assertForbidden();

    g6FinanceSteppedUp($this);
    $this->withHeader('Idempotency-Key', 'g6-topup')->postJson("/v1/staff/payments/{$topup->id}/refund", g6RefundBody($order, 100.0))->assertStatus(422)->assertJsonPath('error', 'topup_not_refundable');
    $topup->forceFill(['purpose' => 'invoice', 'reference_type' => 'invoice'])->save();
    $this->withHeader('Idempotency-Key', 'g6-invoice')->postJson("/v1/staff/payments/{$topup->id}/refund", g6RefundBody($order, 100.0))->assertStatus(422)->assertJsonPath('error', 'refund_payment_not_order');
    expect(G6CardGateway::$refunds)->toBe(0)->and(PaymentRefund::query()->count())->toBe(0)->and(Invoice::query()->where('type', 'credit_note')->count())->toBe(0);
});

it('never refunds more than is left of the payment, nor of the order\'s document', function () {
    [, $org] = $this->customerWithOrganization();
    [$order, $statement, $intent] = g6PaidOrder($org);
    g6FinanceSteppedUp($this);

    $this->withHeader('Idempotency-Key', 'g6-over')->postJson("/v1/staff/payments/{$intent->id}/refund", g6RefundBody($order, 726.01))->assertStatus(409)->assertJsonPath('error', 'refund_exceeds_payment');
    $this->withHeader('Idempotency-Key', 'g6-part-1')->postJson("/v1/staff/payments/{$intent->id}/refund", g6RefundBody($order, 600.0))->assertOk();
    $this->withHeader('Idempotency-Key', 'g6-part-2')->postJson("/v1/staff/payments/{$intent->id}/refund", g6RefundBody($order, 126.01))->assertStatus(409)->assertJsonPath('error', 'refund_exceeds_payment');

    // what a credit note already took off the document (a withdrawal to credit, a correction) is not paid out again
    [$order2, $statement2, $intent2] = g6PaidOrder($org);
    app(InvoiceService::class)->creditNote($statement2, 'Oprava', CommandContext::system('test')->withScope($org->id), null, null, [$statement2->lines()->first()->id => 60500]);
    $this->withHeader('Idempotency-Key', 'g6-doc')->postJson("/v1/staff/payments/{$intent2->id}/refund", g6RefundBody($order2, 200.0))->assertStatus(409)->assertJsonPath('error', 'refund_exceeds_document');
    expect((int) $intent2->fresh()->refunded_minor)->toBe(0)->and(G6CardGateway::$refunds)->toBe(1)
        ->and((int) $statement->fresh()->credited_minor)->toBe(60000);
});

it('is the consumer\'s withdrawal: refused for an order placed as a business and for a notice sent after the fourteen days', function () {
    [, $org] = $this->customerWithOrganization();
    [$order, , $intent] = g6PaidOrder($org, class: 'b2b');
    g6FinanceSteppedUp($this);
    $this->withHeader('Idempotency-Key', 'g6-b2b')->postJson("/v1/staff/payments/{$intent->id}/refund", g6RefundBody($order, 100.0))->assertForbidden()->assertJsonPath('error', 'withdrawal_consumers_only');

    [$late, , $lateIntent] = g6PaidOrder($org, daysAgo: 20);
    $this->withHeader('Idempotency-Key', 'g6-late')->postJson("/v1/staff/payments/{$lateIntent->id}/refund", g6RefundBody($late, 100.0, ['sent_at' => now()->toIso8601String()]))->assertStatus(409)->assertJsonPath('error', 'withdrawal_period_over');
    expect(G6CardGateway::$refunds)->toBe(0)->and(Invoice::query()->where('type', 'credit_note')->count())->toBe(0);
});

it('asks a second person above the refund approval threshold', function () {
    config(['onhost.billing.refund_approval_threshold' => ['CZK' => 50000, 'EUR' => 2000]]);
    [, $org] = $this->customerWithOrganization();
    [$order, , $intent] = g6PaidOrder($org);
    g6FinanceSteppedUp($this);

    $body = g6RefundBody($order, 726.0); // one body: the approval is for exactly this request
    $approval = $this->withHeader('Idempotency-Key', 'g6-large')->postJson("/v1/staff/payments/{$intent->id}/refund", $body)->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    expect(G6CardGateway::$refunds)->toBe(0);
    $this->withHeader('Idempotency-Key', 'g6-large')->postJson("/v1/staff/payments/{$intent->id}/refund", $body + ['approval_ids' => [secondPersonApproves($approval)]])->assertOk()->assertJsonPath('state', 'succeeded');
    expect($intent->fresh()->state)->toBe('REFUNDED');

    // two refunds below the threshold that reach it together: the second one takes the second person
    [$order2, , $intent2] = g6PaidOrder($org);
    $this->withHeader('Idempotency-Key', 'g6-split-1')->postJson("/v1/staff/payments/{$intent2->id}/refund", g6RefundBody($order2, 300.0))->assertOk();
    $this->withHeader('Idempotency-Key', 'g6-split-2')->postJson("/v1/staff/payments/{$intent2->id}/refund", g6RefundBody($order2, 300.0))->assertForbidden()->assertJsonPath('error', 'approval_required');
    expect((int) $intent2->fresh()->refunded_minor)->toBe(30000);
});

it('keeps a bank refund pending until finance confirms the payout, then announces it once', function () {
    [, $org] = $this->customerWithOrganization();
    [$order, , $intent] = g6PaidOrder($org, provider: 'bank');
    g6FinanceSteppedUp($this);

    $answer = $this->withHeader('Idempotency-Key', 'g6-bank')->postJson("/v1/staff/payments/{$intent->id}/refund", g6RefundBody($order, 726.0))->assertOk()->json();
    app(OutboxPublisher::class)->relayPending();
    expect($answer['state'])->toBe('pending')
        ->and(OutboxMessage::query()->where('name', 'payment.refunded')->count())->toBe(0)
        ->and($intent->fresh()->state)->toBe('SUCCEEDED')->and((int) $intent->fresh()->refunded_minor)->toBe(72600) // the payment is spoken for, not yet paid out
        ->and(app(LedgerService::class)->balance('liability:refund_payable:bank:CZK', 'CZK')->minor)->toBe(72600);
    expect($this->getJson('/v1/staff/payments/refunds?state=pending')->assertOk()->json('data.rows.0.id'))->toBe($answer['id']);

    // confirming needs the step-up too, and it is finance's
    $support = $this->staff('support_l1');
    $this->actingAs($support, 'sanctum');
    app(StepUpService::class)->grant($support, 'totp', null, '127.0.0.1');
    $this->withHeader('Idempotency-Key', 'g6-bank-confirm-s')->postJson("/v1/staff/payments/refunds/{$answer['id']}/confirm", ['reference' => 'FIO-2026-10-05-1', 'reason' => 'Příkaz odeslán z Fio.'])->assertForbidden();

    g6FinanceSteppedUp($this);
    $this->withHeader('Idempotency-Key', 'g6-bank-confirm')->postJson("/v1/staff/payments/refunds/{$answer['id']}/confirm", ['reference' => 'FIO-2026-10-05-1', 'reason' => 'Příkaz odeslán z Fio.'])->assertOk()->assertJsonPath('state', 'succeeded');
    app(OutboxPublisher::class)->relayPending();
    $refund = PaymentRefund::query()->findOrFail($answer['id']);
    expect($refund->state)->toBe('succeeded')->and($refund->provider_refund_id)->toBe('FIO-2026-10-05-1')->and($refund->confirmed_at)->not->toBeNull()
        ->and($intent->fresh()->state)->toBe('REFUNDED')
        ->and(OutboxMessage::query()->where('name', 'payment.refunded')->count())->toBe(1)
        ->and(MailOutbox::query()->where('template_key', 'payment-refunded')->count())->toBe(1)
        ->and(app(LedgerService::class)->balance('liability:refund_payable:bank:CZK', 'CZK')->minor)->toBe(0);

    // a second confirmation changes nothing
    $this->withHeader('Idempotency-Key', 'g6-bank-confirm-2')->postJson("/v1/staff/payments/refunds/{$answer['id']}/confirm", ['reference' => 'FIO-other', 'reason' => 'Znovu omylem.'])->assertStatus(409)->assertJsonPath('error', 'refund_not_pending');
    expect(OutboxMessage::query()->where('name', 'payment.refunded')->count())->toBe(1);
});

it('cannot confirm a card refund, which the gateway already confirmed', function () {
    [, $org] = $this->customerWithOrganization();
    [$order, , $intent] = g6PaidOrder($org);
    g6FinanceSteppedUp($this);
    $id = $this->withHeader('Idempotency-Key', 'g6-card-c')->postJson("/v1/staff/payments/{$intent->id}/refund", g6RefundBody($order, 100.0))->assertOk()->json('id');
    $this->withHeader('Idempotency-Key', 'g6-card-c-confirm')->postJson("/v1/staff/payments/refunds/{$id}/confirm", ['reference' => 'x-1', 'reason' => 'Omylem potvrzeno.'])->assertStatus(409)->assertJsonPath('error', 'refund_not_pending');
});

function g6FinanceSteppedUp(TestCase $test): void
{
    $finance = User::factory()->staff()->create();
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $finance->id, 'role_key' => 'billing_finance_admin', 'scope_type' => 'global', 'scope_id' => null, 'organization_id' => null]);
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');
    $test->actingAs($finance, 'sanctum');
}
