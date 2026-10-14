<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Loyalty\LoyaltyService;
use Onhost\Domain\Loyalty\Models\LoyaltyPoint;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Owner decision R6: loyalty points are earned only by payments of 100 CZK or more, and they are taken back when the
 * money is given back (credit note, which is also how a chargeback refund is booked). Nothing here is a discount: points
 * are never money, a clawback never touches the wallet, and one organization can never lose another one's points.
 */

beforeEach(fn () => $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]));

/** Publishes an event for the organization and runs the listeners. */
function r6Publish(Organization $org, string $name, string $type, string $id, array $payload, ?string $organizationId = null): void
{
    app(OutboxPublisher::class)->publish(GenericEvent::of($name, $type, $id, $payload, $organizationId ?? $org->id));
    app(OutboxPublisher::class)->relayPending();
}

/** One issued tax invoice of an order. */
function r6OrderInvoice(Organization $org, Order $order, int $gross): Invoice
{
    $ctx = CommandContext::system('test')->withScope($org->id);
    $invoices = app(InvoiceService::class);

    return $invoices->issue($invoices->draft($org, 'invoice', 'CZK', [['sku' => 'vps', 'description' => 'VPS', 'qty' => 1, 'unit_net' => $gross, 'discount' => 0, 'net' => $gross, 'tax_rate' => '0', 'tax_category' => 'Z', 'tax' => 0, 'total' => $gross]], $ctx, $order->id, ['postpaid' => true, 'payment_method' => 'bank']), $ctx);
}

/** A paid order of `$gross` minor units, its tax invoice (issued, one line) and the payment that settled it. */
function r6PaidOrder(Organization $org, int $gross, ?int $invoiceGross = null): array
{
    $order = Order::query()->create(['number' => 'OH-R6-'.random_int(10000, 99999), 'organization_id' => $org->id, 'state' => 'ACTIVE', 'currency' => 'CZK', 'subtotal_minor' => $gross, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => $gross, 'payment_mode' => 'wallet', 'source' => 'web', 'commit_months' => 1, 'idempotency_key' => 'r6-'.uniqid(), 'placed_at' => now()]);
    $ctx = CommandContext::system('test')->withScope($org->id);
    $invoice = r6OrderInvoice($org, $order, $invoiceGross ?? $gross);
    $intent = PaymentIntent::query()->create(['organization_id' => $org->id, 'provider' => 'comgate', 'provider_id' => 'cg-'.uniqid(), 'purpose' => 'order', 'reference_type' => 'order', 'reference_id' => $order->id, 'amount_minor' => $gross, 'currency' => 'CZK', 'state' => 'SUCCEEDED', 'idempotency_key' => 'r6-pi-'.uniqid(), 'paid_at' => now()]);
    r6Publish($org, 'payment.succeeded', 'payment', $intent->id, ['purpose' => 'order', 'reference' => ['order', $order->id], 'amount' => ['minor' => $gross, 'currency' => 'CZK']]);
    r6Publish($org, 'order.paid', 'order', $order->id, ['number' => $order->number]);

    return [$order, $invoice, $intent];
}

it('earns points only from payments of 100 CZK or more', function () {
    [, $org] = $this->customerWithOrganization();
    $loyalty = app(LoyaltyService::class);

    r6Publish($org, 'payment.succeeded', 'payment', 'pi_small', ['purpose' => 'topup', 'amount' => ['minor' => 100, 'currency' => 'CZK']]); // 1 CZK
    r6Publish($org, 'payment.succeeded', 'payment', 'pi_edge', ['purpose' => 'topup', 'amount' => ['minor' => 9999, 'currency' => 'CZK']]); // 99.99 CZK
    expect($loyalty->points($org->id))->toBe(0);

    r6Publish($org, 'payment.succeeded', 'payment', 'pi_ok', ['purpose' => 'topup', 'amount' => ['minor' => 10000, 'currency' => 'CZK']]); // exactly 100 CZK
    r6Publish($org, 'payment.succeeded', 'payment', 'pi_ok', ['purpose' => 'topup', 'amount' => ['minor' => 10000, 'currency' => 'CZK']]); // redelivered
    expect($loyalty->points($org->id))->toBe(10);

    $order = Order::query()->create(['number' => 'OH-R6-1', 'organization_id' => $org->id, 'state' => 'ACTIVE', 'currency' => 'CZK', 'subtotal_minor' => 9900, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 9900, 'payment_mode' => 'wallet', 'source' => 'web', 'commit_months' => 1, 'idempotency_key' => 'r6-small', 'placed_at' => now()]);
    r6Publish($org, 'order.paid', 'order', $order->id, ['number' => $order->number]);
    expect($loyalty->points($org->id))->toBe(10); // a 99 CZK order earns nothing either
});

it('takes the points back when a credit note gives the money back, once, in proportion, never below what the order earned', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'r6a@example.cz']);
    $loyalty = app(LoyaltyService::class);
    [$order, $invoice, $intent] = r6PaidOrder($org, 40000); // 400 CZK: 4 order points + 10 for the payment
    expect($loyalty->points($org->id))->toBe(14);

    $ctx = CommandContext::system('test')->withScope($org->id);
    $half = app(InvoiceService::class)->creditNote($invoice, 'Polovina nebyla dodána', $ctx, null, null, [$invoice->lines()->first()->id => 20000]);
    app(OutboxPublisher::class)->relayPending();
    app(OutboxPublisher::class)->relayPending(); // a second relay never counts it twice
    expect(-$half->total_minor)->toBe(20000)->and($loyalty->points($org->id))->toBe(12)  // 2 of the 4 order points; the payment still stands
        ->and(LoyaltyPoint::query()->where('organization_id', $org->id)->where('rule', 'clawback.order')->sum('points'))->toBe(-2);

    // the rest of the document: the remaining order points and the payment's points go too, and the balance stops at what was earned
    app(InvoiceService::class)->creditRemaining($invoice->refresh(), 'Storno zbytku', $ctx);
    app(OutboxPublisher::class)->relayPending();
    expect($loyalty->points($org->id))->toBe(0)->and($invoice->refresh()->state)->toBe(Invoice::CREDITED);
    expect(app(WalletService::class)->balances($org, 'CZK')['promo']->minor)->toBe(0); // points are never money: no wallet movement
});

it('does not let one organization lose another one\'s points', function () {
    [, $a] = $this->customerWithOrganization(['email' => 'r6b@example.cz']);
    [, $b] = $this->customerWithOrganization(['email' => 'r6c@example.cz']);
    $loyalty = app(LoyaltyService::class);
    [, $invoice] = r6PaidOrder($a, 30000);
    r6PaidOrder($b, 30000);
    expect($loyalty->points($a->id))->toBe(13)->and($loyalty->points($b->id))->toBe(13);

    $note = app(InvoiceService::class)->creditNote($invoice, 'Storno', CommandContext::system('test')->withScope($a->id));
    app(OutboxPublisher::class)->relayPending(); // the real event: only A loses
    expect($loyalty->points($a->id))->toBe(0)->and($loyalty->points($b->id))->toBe(13);

    // a replay under another organization's id changes nothing for anybody
    r6Publish($b, 'invoice.issued', 'invoice', $note->id, ['number' => $note->number, 'type' => 'credit_note'], $b->id);
    expect($loyalty->points($b->id))->toBe(13)->and(LoyaltyPoint::query()->where('organization_id', $b->id)->where('points', '<', 0)->count())->toBe(0);
});

it('announces the clawback to the customer and lists it in the history', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'r6d@example.cz']);
    [, $invoice] = r6PaidOrder($org, 20000);
    app(InvoiceService::class)->creditNote($invoice, 'Storno', CommandContext::system('test')->withScope($org->id));
    app(OutboxPublisher::class)->relayPending();
    app(OutboxPublisher::class)->relayPending(); // the clawback event itself is published while the first one is relayed

    $this->actingAs($owner, 'sanctum');
    $summary = $this->getJson('/v1/account/rewards')->assertOk()->json('data');
    expect($summary['points'])->toBe(0)->and(collect($summary['history'])->pluck('rule')->all())->toContain('clawback.order', 'clawback.payment')
        ->and(Notification::query()->where('organization_id', $org->id)->where('title', 'like', 'Věrnostní body vráceny%')->exists())->toBeTrue();
});

it('measures the clawback against the order, not against one of its invoices', function () {
    [, $org] = $this->customerWithOrganization(['email' => 'r6e@example.cz']);
    $loyalty = app(LoyaltyService::class);
    [$order, $first, $intent] = r6PaidOrder($org, 40000, 20000); // 400 CZK order billed on two invoices of 200 CZK
    $second = r6OrderInvoice($org, $order, 20000);
    expect($loyalty->points($org->id))->toBe(14);
    $ctx = CommandContext::system('test')->withScope($org->id);

    // the first invoice credited in full is half of the order: half of its 4 points, the payment still stands
    app(InvoiceService::class)->creditNote($first, 'Storno první faktury', $ctx);
    app(OutboxPublisher::class)->relayPending();
    expect($loyalty->points($org->id))->toBe(12);

    // the second one completes the order: the rest of the points and the payment's points go
    app(InvoiceService::class)->creditNote($second, 'Storno druhé faktury', $ctx);
    app(OutboxPublisher::class)->relayPending();
    expect($loyalty->points($org->id))->toBe(0)->and(LoyaltyPoint::query()->where('organization_id', $org->id)->sum('points'))->toBe(0);
});

it('never takes more than was earned when two credit notes of one order are booked before either is handled', function () {
    [, $org] = $this->customerWithOrganization(['email' => 'r6f@example.cz']);
    $loyalty = app(LoyaltyService::class);
    [, $invoice] = r6PaidOrder($org, 40000);
    $ctx = CommandContext::system('test')->withScope($org->id);
    $line = $invoice->lines()->first()->id;
    app(InvoiceService::class)->creditNote($invoice, 'Část 1', $ctx, null, null, [$line => 30000]);
    app(InvoiceService::class)->creditNote($invoice->refresh(), 'Část 2', $ctx, null, null, [$line => 10000]);
    app(OutboxPublisher::class)->relayPending(); // both events are handled now, the document is already credited in full

    $taken = -LoyaltyPoint::query()->where('organization_id', $org->id)->where('points', '<', 0)->sum('points');
    expect($loyalty->points($org->id))->toBe(0)->and($taken)->toBe(14)->and(LoyaltyPoint::query()->where('organization_id', $org->id)->where('rule', 'clawback.order')->sum('points'))->toBe(-4);
});

it('earns order points per the currency minimum and nothing from a payment event without an amount', function () {
    [, $org] = $this->customerWithOrganization(['email' => 'r6g@example.cz']);
    $loyalty = app(LoyaltyService::class);
    config(['onhost.loyalty.min_payment_minor' => ['CZK' => 5000, 'default' => 5000]]); // the same table drives the minimum and the order's point unit
    $order = Order::query()->create(['number' => 'OH-R6-2', 'organization_id' => $org->id, 'state' => 'ACTIVE', 'currency' => 'CZK', 'subtotal_minor' => 15000, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 15000, 'payment_mode' => 'wallet', 'source' => 'web', 'commit_months' => 1, 'idempotency_key' => 'r6-unit', 'placed_at' => now()]);
    r6Publish($org, 'order.paid', 'order', $order->id, ['number' => $order->number]);
    expect($loyalty->points($org->id))->toBe(3); // 150 CZK at one point per 50 CZK

    r6Publish($org, 'payment.succeeded', 'payment', 'pi_noamount', ['purpose' => 'topup']); // no amount: cannot be shown to reach the minimum, so it earns nothing (intended)
    expect($loyalty->points($org->id))->toBe(3);
});
