<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentRefund;
use Onhost\Domain\Payments\OrderPaymentRefunds;
use Onhost\Domain\Payments\PaymentProviderRegistry;
use Onhost\Domain\Payments\PaymentService;
use Onhost\Domain\Payments\RefundsNotPaidOut;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Payments\Bank\BankTransferPaymentProvider;

/*
 * H3 (phase H, TASK-0121): a bank payout finance cancelled (returned by the bank, a wrong account) leaves its credit note and the
 * money it owes in `liability:refund_payable:<provider>`; the next payout of the same amount uses that note (G6 review L). When no
 * next payout comes, nothing said so — the customer was owed money and the only trace was a ledger balance. The report lists
 * every such credit note (the payment, the organization, the amount, since when), and the payable's balance per account, for
 * finance: an artisan command and a staff route behind `staff.billing.read`. Paying it out stays the refund flow of G6.
 */

beforeEach(function () {
    $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]);
    $registry = new PaymentProviderRegistry(app());
    $registry->register('bank', BankTransferPaymentProvider::class);
    app()->instance(PaymentProviderRegistry::class, $registry);
});

/** A consumer's order paid by bank three days ago (600 + 21 % = 726 CZK), its statement and the payment. @return array{0:Order, 1:PaymentIntent} */
function h3BankOrder(Organization $org): array
{
    $placed = now()->subDays(3);
    $order = Order::query()->create(['number' => 'ON-H3'.substr(uniqid(), -6), 'organization_id' => $org->id, 'state' => OrderStateMachine::ACTIVE, 'currency' => 'CZK', 'subtotal_minor' => 60000, 'tax_minor' => 12600, 'total_minor' => 72600,
        'payment_mode' => 'wallet', 'idempotency_key' => 'h3-order-'.uniqid(), 'placed_at' => $placed, 'paid_at' => $placed, 'meta' => ['customer_class' => 'b2c']]);
    OrderItem::query()->create(['order_id' => $order->id, 'sku' => 'web-pro', 'product_key' => 'web-pro', 'name' => 'Webhosting Pro', 'qty' => 1, 'unit_net_minor' => 60000, 'tax_rate' => '21', 'tax_minor' => 12600, 'total_minor' => 72600, 'period' => 'month', 'config' => ['family' => 'web'], 'state' => 'active']);
    app(InvoiceService::class)->issueForOrder($order, CommandContext::system('test')->withScope($org->id), 'bank');
    $intent = PaymentIntent::query()->create(['organization_id' => $org->id, 'provider' => 'bank', 'provider_id' => 'h3-'.uniqid(), 'purpose' => 'order', 'reference_type' => 'order', 'reference_id' => $order->id,
        'amount_minor' => 72600, 'currency' => 'CZK', 'state' => 'SUCCEEDED', 'idempotency_key' => 'h3-pi-'.uniqid(), 'paid_at' => $placed]);
    app(OutboxPublisher::class)->relayPending();

    return [$order, $intent];
}

/** The withdrawal refund of `$minor` to the bank account, pending until finance confirms or cancels it. */
function h3PendingRefund(PaymentIntent $intent, int $minor, string $key): PaymentRefund
{
    $ticket = Ticket::query()->create(['number' => 'TK-2026-'.random_int(10000, 99999), 'organization_id' => $intent->organization_id, 'email' => 'owner@example.test', 'subject' => 'Odstoupení od smlouvy']);

    return app(OrderPaymentRefunds::class)->refundOnWithdrawal($intent, Money::minor($minor, 'CZK'), CarbonImmutable::now()->subDay(), 'Odstoupení, peníze na účet.', $key, CommandContext::system('test'), true, $ticket->id)['refund'];
}

function h3Finance(object $test, string $role = 'billing_finance_admin'): User
{
    $user = User::factory()->staff()->create();
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $role, 'scope_type' => 'global', 'scope_id' => null, 'organization_id' => null]);
    $test->actingAs($user, 'sanctum');

    return $user;
}

it('lists a credit note whose bank payout was cancelled and that no later payout paid, with the payable it left behind', function () {
    [, $org] = $this->customerWithOrganization();
    [$order, $intent] = h3BankOrder($org);
    $ctx = CommandContext::system('test');

    $stranded = h3PendingRefund($intent, 72600, 'h3-r1');
    app(PaymentService::class)->cancelRefund($stranded, $ctx); // the bank returned it

    [, $paidAgain] = h3BankOrder($org);                          // a second order: cancelled, then paid out with the same note
    $first = h3PendingRefund($paidAgain, 72600, 'h3-r2');
    app(PaymentService::class)->cancelRefund($first, $ctx);
    h3PendingRefund($paidAgain, 72600, 'h3-r3');               // pending again: on its way, not stranded

    $report = app(RefundsNotPaidOut::class)->report();
    expect($report['rows'])->toHaveCount(1)
        ->and($report['rows'][0])->toMatchArray(['payment_id' => $intent->id, 'organization_id' => $org->id, 'order' => $order->number, 'provider' => 'bank', 'refund_id' => $stranded->id])
        ->and($report['rows'][0]['amount'])->toEqual(Money::minor(72600, 'CZK'))
        ->and($report['rows'][0]['credit_note'])->toBeString()->not->toBe('')
        ->and($report['rows'][0]['cancelled_at'])->not->toBeNull()
        ->and($report['total'])->toBe(['CZK' => 72600])
        // the payable holds both notes: the stranded one and the one whose payout is pending
        ->and($report['payable'])->toBe(['liability:refund_payable:bank:CZK' => 145200])
        ->and(app(LedgerService::class)->balance('liability:refund_payable:bank:CZK', 'CZK')->minor)->toBe(145200);
});

it('answers finance on the staff route and refuses support', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [, $intent] = h3BankOrder($org);
    app(PaymentService::class)->cancelRefund(h3PendingRefund($intent, 30000, 'h3-r4'), CommandContext::system('test'));

    h3Finance($this);
    $this->getJson('/v1/staff/payments/refunds/not-paid-out')->assertOk()
        ->assertJsonPath('data.rows.0.payment_id', $intent->id)
        ->assertJsonPath('data.total.CZK', 30000);

    $support = $this->staff('support_l1');
    $this->actingAs($support, 'sanctum');
    $this->getJson('/v1/staff/payments/refunds/not-paid-out')->assertForbidden();

    $this->actingAs($owner, 'sanctum');
    expect($this->getJson('/v1/staff/payments/refunds/not-paid-out')->status())->toBeIn([403, 404]);
});

it('prints the report on the command line and exits non-zero while money waits', function () {
    $this->artisan('onhost:billing:refunds-not-paid-out')->assertSuccessful()->expectsOutputToContain('no cancelled refund waits');

    [, $org] = $this->customerWithOrganization();
    [, $intent] = h3BankOrder($org);
    app(PaymentService::class)->cancelRefund(h3PendingRefund($intent, 30000, 'h3-r5'), CommandContext::system('test'));
    $this->artisan('onhost:billing:refunds-not-paid-out')->assertFailed()->expectsOutputToContain('owed in CZK: 300 Kč');
});
