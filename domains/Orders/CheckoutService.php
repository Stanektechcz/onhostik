<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Catalog\Models\PromoCode;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\AccountingClock;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\Models\Consent;
use Onhost\Domain\Orders\Models\ConsentDocument;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\PaymentService;
use Onhost\Domain\Services\Limits\LimitRaisePolicy;
use Onhost\Domain\WalletLedger\Models\WalletHold;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Ids\PublicId;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Checkout is never a provider call (§78):
 *   Cart -> Quote -> tax -> consents -> wallet/payment authorization -> Order COMMITTED
 *   -> async provisioning (order.paid event) -> live progress -> invoice/document.
 * Mixed carts are one checkout, but every item is fulfilled by its own saga (§78.1).
 */
final class CheckoutService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly PaymentService $payments,
        private readonly InvoiceService $invoices,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
        private readonly OrderRiskService $risk,
        private readonly CreditOrderPolicy $creditPolicy,
    ) {}

    /**
     * @param  array<string, array{version?:string, person?:string}>  $consents  document key => {version, person}
     * @param  array{mode:string, method?:string, return_urls?:array<string,string>, provider?:string}  $payment
     * @return array{order:Order, redirect_url:?string, payment_intent_id:?string, bank_instructions:?array<string,mixed>}
     */
    public function placeOrder(Quote $quote, Organization $organization, ?User $user, array $consents, array $payment, string $idempotencyKey, CommandContext $context, string $source = 'web'): array
    {
        $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null && $existing->organization_id !== $organization->id) {
            // the key is the client's own random value, but it is not a credential: a key that belongs to another organization's
            // order answers with a conflict and nothing about that order (it used to return the order, its totals and its payment link)
            throw new DomainError('idempotency_key_reused', 'This Idempotency-Key was already used for another request.', 409);
        }
        if ($existing !== null) {
            return ['order' => $existing, 'redirect_url' => $existing->meta['redirect_url'] ?? null, 'payment_intent_id' => $existing->payment_intent_id, 'bank_instructions' => $existing->meta['bank_instructions'] ?? null];
        }
        if (! $quote->isValid()) {
            throw new DomainError('quote_expired', 'The quote has expired; refresh the cart to get current prices.', 409);
        }
        if ($quote->organization_id !== null && $quote->organization_id !== $organization->id) {
            throw new DomainError('quote_organization_mismatch', 'The quote belongs to a different organization.', 403);
        }
        $mode = $payment['mode'] ?? 'wallet';
        if (! in_array($mode, ['wallet', 'gateway', 'bank', 'postpaid'], true)) {
            throw new DomainError('payment_mode_invalid', 'Unsupported payment mode.');
        }
        LimitRaisePolicy::assertOrderable($quote, $source, $context); // customers order a raise once the switch is on; a free raise is staff's (TASK-0022)
        if ($mode === 'postpaid' && $this->wallets->approvedCreditLine($organization->id, $quote->currency)->isZero()) {
            throw new DomainError('postpaid_not_approved', 'Postpaid billing requires an approved credit line.', 403);
        }
        // owner decision 20 (TASK-0021): a credit order of somebody who may not spend the credit waits for the owner or the billing admin;
        // what the organization could not pay anyway is refused now, not after somebody approved it
        $quoted = Money::minor((int) $quote->total_minor, $quote->currency);
        $awaitApproval = $this->creditPolicy->mustAwaitApproval($organization, $context, $mode, $source, $quoted);
        // what a non-holder orders (by card or bank too) renews only under the organization's standing auto-renew default
        $standingDefault = $this->creditPolicy->followsStandingDefault($organization, $context);
        $requiredDocs = $this->requiredDocuments($quote, $organization);
        foreach ($requiredDocs as $key) {
            if (! isset($consents[$key])) {
                throw new DomainError('consent_required', "Consent to document '{$key}' is required.", 422, ['field' => 'terms', 'missing' => $key]);
            }
        }

        // Retry safety beyond the idempotency key (a new quote id per attempt would otherwise create a fresh order):
        // an unpaid order with the same content for the same organization placed in the last minutes is the same order.
        $fingerprint = self::fingerprint($quote, $organization, $mode);
        $duplicate = Order::query()->where('organization_id', $organization->id)->whereIn('state', [OrderStateMachine::NEW, OrderStateMachine::PENDING_PAYMENT])
            ->where('meta->fingerprint', $fingerprint)->where('placed_at', '>=', now()->subMinutes((int) config('onhost.orders.duplicate_window_minutes', 15)))->orderByDesc('placed_at')->first();
        if ($duplicate !== null) {
            $this->audit->record($context->withScope($organization->id), 'order.place', 'replayed', ['number' => $duplicate->number, 'reason' => 'same content within the duplicate window'], 'order', $duplicate->id);

            return ['order' => $duplicate, 'redirect_url' => $duplicate->meta['redirect_url'] ?? null, 'payment_intent_id' => $duplicate->payment_intent_id, 'bank_instructions' => $duplicate->meta['bank_instructions'] ?? null];
        }
        if ($awaitApproval) { // after the duplicate lookup: a resubmitted held cart gets the order that already waits, not a 402
            $this->assertCoverable($organization, $quoted, collect($quote->lines)->contains(fn ($line) => ($line['product_key'] ?? null) === 'domain'), $context);
        }

        // intake pre-check (audit §5f-8): scored before anything is written; a held order is placed and paid like any other, only its fulfilment waits for staff
        $risk = $this->risk->assess($quote, $organization, $user, $context, $source);

        return DB::transaction(function () use ($quote, $organization, $user, $consents, $payment, $idempotencyKey, $context, $source, $mode, $fingerprint, $risk, $awaitApproval, $standingDefault) {
            $quote->forceFill(['state' => 'accepted', 'organization_id' => $organization->id])->save();
            // a promo code is used when an order is placed with it — counted here, under a lock, so "the first hundred" is a
            // hundred even when two checkouts race. The counter existed and nothing ever wrote to it: every limited code was unlimited.
            $promoCode = (string) ($quote->versions['promo'] ?? '');
            if ($promoCode !== '') {
                $promo = PromoCode::query()->where('code', $promoCode)->lockForUpdate()->first();
                if ($promo === null || ! $promo->isUsable()) {
                    throw new DomainError('promo_exhausted', 'This promo code can no longer be used; refresh the cart to see the price without it.', 409, ['field' => 'promo']);
                }
                $promo->forceFill(['uses' => (int) $promo->uses + 1])->save();
            }
            $number = $this->allocateNumber();
            $order = Order::query()->create([
                'number' => $number,
                'organization_id' => $organization->id,
                'user_id' => $user?->id,
                'quote_id' => $quote->id,
                'state' => OrderStateMachine::NEW,
                'currency' => $quote->currency,
                'subtotal_minor' => $quote->subtotal_minor,
                'discount_minor' => $quote->discount_minor,
                'tax_minor' => $quote->tax_minor,
                'total_minor' => $quote->total_minor,
                'payment_mode' => $mode,
                'promo_code' => $quote->versions['promo'] ?? null,
                'source' => $source,
                'commit_months' => (int) ($quote->versions['commit_months'] ?? 1),
                'idempotency_key' => $idempotencyKey,
                'placed_at' => now(),
                'meta' => array_filter(['tax_review_required' => (bool) ($quote->versions['tax_review_required'] ?? false), 'renewal_total_minor' => $quote->renewal_total_minor, 'fingerprint' => $fingerprint,
                    'customer_class' => (string) ($organization->customer_class ?: 'b2c'), // TASK-0025: the class the contract was concluded as decides a later withdrawal
                    'vat_review' => (bool) ($quote->versions['vat_review'] ?? false), 'vat' => $quote->versions['vat'] ?? null, // TASK-0031: the VIES check the tax was decided on, for the document and for finance

                    'risk' => ['score' => $risk['score'], 'reasons' => $risk['reasons']], 'review' => $risk['hold'] ? ['state' => 'pending', 'score' => $risk['score'], 'reasons' => $risk['reasons'], 'opened_at' => now()->toIso8601String()] : null,
                    'approval' => $awaitApproval ? CreditOrderApprovals::opened($context) : null, 'renewal_consent' => $standingDefault ? 'organization_default' : null], fn ($v) => $v !== null),
            ]);
            foreach ($quote->lines as $line) {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'sku' => $line['sku'],
                    'product_key' => $line['product_key'],
                    'plan_version_id' => $line['plan_version_id'],
                    'price_id' => $line['price_id'],
                    'name' => $line['name'],
                    'qty' => $line['qty'],
                    'unit_net_minor' => $line['unit_net'],
                    'discount_minor' => $line['discount'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_minor' => $line['tax'],
                    'total_minor' => $line['total'],
                    'period' => $line['period'],
                    'config' => array_merge($line['config'], ['entitlements' => $line['entitlements'], 'family' => $line['family'], 'renewal_net_minor' => $line['renewal_net'], 'tax_category' => $line['tax_category'], 'currency' => $quote->currency]),
                    'state' => 'pending',
                ]);
            }
            $consentIds = $this->recordConsents($order, $organization, $user, $consents, $context);
            $order->forceFill(['consents' => $consentIds])->save();
            $this->audit->record($context->withScope($organization->id), 'order.place', 'succeeded', ['number' => $number, 'total' => $order->total(), 'mode' => $mode], 'order', $order->id);

            $result = ['order' => $order, 'redirect_url' => null, 'payment_intent_id' => null, 'bank_instructions' => null];
            $total = $order->total();

            if (! $total->isZero() && in_array($mode, ['gateway', 'bank'], true)) {
                // a hard budget refuses the order before anybody pays for it; after the payment the money is the order's
                $this->wallets->assertWithinBudget($organization, $total, $context);
            }
            if ($total->isZero()) { // nothing to pay (a plan downgrade): paid at once, whatever method was chosen
                $this->markPaid($order, $context, 'wallet');
            } elseif ($awaitApproval) { // nothing reserved, documented or provisioned: the owner or the billing admin decides (CreditOrderApprovals)
                $this->audit->record($context->withScope($organization->id), 'order.approval.request', 'succeeded', ['number' => $number, 'total' => $total, 'mode' => $mode], 'order', $order->id);
                $this->outbox->publish(GenericEvent::of('order.approval.required', 'order', $order->id, ['number' => $number, 'total' => $total, 'mode' => $mode, 'requester_id' => CreditOrderApprovals::of($order)['requester_id'] ?? null, 'requester' => CreditOrderApprovals::of($order)['requester_name'] ?? null], $organization->id));
            } elseif ($mode === 'wallet' || $mode === 'postpaid') {
                $this->payFromCredit($order, $context);
            } elseif ($mode === 'gateway') {
                $intent = $this->payments->createIntent($organization, $total, 'order', 'order', $order->id, $context, [
                    'provider' => $payment['provider'] ?? null,
                    'method' => $payment['method'] ?? null,
                    'return_urls' => $payment['return_urls'] ?? [],
                    'description' => "Objednávka {$number}",
                    'email' => $user?->email ?? $organization->billing_email,
                ]);
                $order->forceFill(['state' => OrderStateMachine::PENDING_PAYMENT, 'payment_intent_id' => $intent->id, 'meta' => array_merge($order->meta ?? [], ['redirect_url' => $intent->redirect_url])])->save();
                $result['redirect_url'] = $intent->redirect_url;
                $result['payment_intent_id'] = $intent->id;
            } else { // bank transfer: proforma with variable symbol, matched by statement import
                $proforma = $this->invoices->issueProforma($order, $context);
                $intent = $this->payments->createIntent($organization, $total, 'order', 'order', $order->id, $context, ['provider' => 'bank', 'reference' => $proforma->payment_reference, 'description' => "Objednávka {$number}"]);
                $order->forceFill(['state' => OrderStateMachine::PENDING_PAYMENT, 'payment_intent_id' => $intent->id, 'invoice_id' => $proforma->id, 'meta' => array_merge($order->meta ?? [], ['bank_instructions' => $intent->raw['instructions'] ?? null])])->save();
                $result['payment_intent_id'] = $intent->id;
                $result['bank_instructions'] = $intent->raw['instructions'] ?? null;
            }

            $this->outbox->publish(GenericEvent::of('order.placed', 'order', $order->id, ['number' => $number, 'state' => $order->refresh()->state, 'total' => $total, 'mode' => $mode] + ($awaitApproval ? ['approval' => 'pending'] : []), $organization->id));

            return $result;
        }, 3);
    }

    /**
     * Reserves the order's total on the credit and marks it paid: at placement by whoever may spend the credit, and when an owner or
     * billing admin approves a held credit order (CreditOrderApprovals). No expiry: the reservation lasts until the order is settled
     * (OrderSettlement) — a registry can take days, a risk review too.
     */
    public function payFromCredit(Order $order, CommandContext $context): Order
    {
        $hold = $this->wallets->hold($order->organization_id, $order->total(), 'order', "order:{$order->id}", $context, 'order', $order->id, $this->hasDomain($order) ? 'domain' : 'normal', null);
        $order->forceFill(['wallet_hold_id' => $hold->id])->save();

        return $this->markPaid($order, $context, 'wallet');
    }

    /**
     * A held credit order is refused at once when the organization could not pay it anyway (read only: nothing is reserved).
     * The same arithmetic as the hold the approval will make (WalletService::hold): an order without a domain may not use the
     * money kept aside for domain renewals.
     */
    private function assertCoverable(Organization $organization, Money $total, bool $hasDomain, CommandContext $context): void
    {
        $spendable = $this->wallets->spendable($organization, $total->currency);
        if (! $hasDomain) {
            $spendable = $spendable->subtract($this->wallets->domainReserve($organization->id, $total->currency));
        }
        if ($spendable->lessThan($total)) {
            throw new DomainError('insufficient_funds', 'Insufficient wallet balance for this operation.', 402, ['required' => $total, 'available' => $spendable, 'hint' => 'Top up the wallet or enable auto top-up.']);
        }
        $this->wallets->assertWithinBudget($organization, $total, $context);
    }

    /** Called by the wallet path immediately and by the payment settlement listener after a verified payment. */
    public function markPaid(Order $order, CommandContext $context, string $method, ?string $paymentIntentId = null): Order
    {
        return DB::transaction(function () use ($order, $context, $method, $paymentIntentId) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (in_array($order->state, [OrderStateMachine::PAID, OrderStateMachine::PROVISIONING, OrderStateMachine::ACTIVE, OrderStateMachine::PARTIALLY_ACTIVE], true)) {
                return $order; // idempotent (duplicate webhook / replay)
            }
            OrderStateMachine::machine()->assertTransition($order->state, OrderStateMachine::PAID);
            if ($order->wallet_hold_id === null && $paymentIntentId !== null) {
                // Gateway/bank payments were credited to the wallet by PaymentService; reserve them now.
                $hold = $this->wallets->hold($order->organization_id, $order->total(), 'order', "order:{$order->id}", $context, 'order', $order->id, $this->hasDomain($order) ? 'domain' : 'normal', null, enforceBudget: false); // this money was paid for this order; the budget was asked at placement
                $order->forceFill(['wallet_hold_id' => $hold->id]);
            }
            $order->forceFill(['state' => OrderStateMachine::PAID, 'paid_at' => now(), 'payment_intent_id' => $paymentIntentId ?? $order->payment_intent_id])->save();
            $invoice = $this->invoices->issueForOrder($order, $context, $method);
            $order->forceFill(['invoice_id' => $invoice->id])->save();
            $this->audit->record($context->withScope($order->organization_id), 'order.paid', 'succeeded', ['number' => $order->number, 'method' => $method], 'order', $order->id);
            if (($order->meta['review']['state'] ?? null) === 'pending') { // held by the intake pre-check: paid, documented, not provisioned until staff decide
                $this->outbox->publish(GenericEvent::of('order.review.required', 'order', $order->id, ['number' => $order->number, 'score' => $order->meta['review']['score'] ?? null, 'reasons' => $order->meta['review']['reasons'] ?? [], 'total' => $order->total()], $order->organization_id));

                return $order;
            }
            $this->outbox->publish(GenericEvent::of('order.paid', 'order', $order->id, ['number' => $order->number, 'items' => $order->items()->pluck('id')->all(), 'invoice_id' => $invoice->id], $order->organization_id));

            return $order;
        }, 3);
    }

    /**
     * Staff decision on a held order (audit §5f-8): release starts the fulfilment the payment would have started,
     * reject cancels the order and frees the credit hold (a gateway payment is refunded through the ordinary path).
     */
    public function review(Order $order, string $decision, CommandContext $context, ?string $reason = null): Order
    {
        if (($order->meta['review']['state'] ?? null) !== 'pending') {
            throw new DomainError('order_not_under_review', 'The order is not waiting for a review.', 409);
        }
        if (! in_array($decision, ['release', 'reject'], true)) {
            throw new DomainError('order_review_decision_invalid', 'Decision must be release or reject.', 422, ['field' => 'decision']);
        }
        $review = array_merge((array) $order->meta['review'], ['state' => $decision === 'release' ? 'released' : 'rejected', 'decided_at' => now()->toIso8601String(), 'decided_by' => $context->actorId, 'reason' => $reason]);
        $order->forceFill(['meta' => array_merge((array) $order->meta, ['review' => $review])])->save();
        $weights = $this->risk->learn($order, $decision, $context->actorType.':'.($context->actorId ?? 'system')); // the decision teaches the check (§5g-4)
        $this->audit->record($context->withScope($order->organization_id), 'order.review', 'succeeded', ['number' => $order->number, 'decision' => $decision, 'reason' => $reason, 'score' => $review['score'] ?? null, 'weights' => array_intersect_key($weights, array_flip((array) data_get($order->meta, 'risk.reasons', [])))], 'order', $order->id);
        if ($decision === 'reject') {
            if ($order->state !== OrderStateMachine::CANCELLED) {
                $this->transition($order, OrderStateMachine::CANCELLED, $context, $reason ?? 'rejected after review');
            }
            $this->outbox->publish(GenericEvent::of('order.review.rejected', 'order', $order->id, ['number' => $order->number, 'reason' => $reason], $order->organization_id));

            return $order->fresh();
        }
        $this->outbox->publish(GenericEvent::of('order.review.released', 'order', $order->id, ['number' => $order->number], $order->organization_id));
        if (in_array($order->state, [OrderStateMachine::PAID], true)) {
            $this->outbox->publish(GenericEvent::of('order.paid', 'order', $order->id, ['number' => $order->number, 'items' => $order->items()->pluck('id')->all(), 'invoice_id' => $order->invoice_id, 'released' => true], $order->organization_id));
        }

        return $order->fresh();
    }

    /** States a person may cancel an order from: the customer while nothing is paid, staff also once it is paid but nothing runs. */
    public const CUSTOMER_CANCELLABLE = [OrderStateMachine::NEW, OrderStateMachine::PENDING_PAYMENT];

    public const STAFF_CANCELLABLE = [OrderStateMachine::NEW, OrderStateMachine::PENDING_PAYMENT, OrderStateMachine::PAID, OrderStateMachine::FAILED];

    /**
     * The one thing a person does to the state of an order: cancel it. Every other state follows the money and the lines —
     * "paid" comes from a payment, "active" from delivered lines. The endpoint used to take ANY target state from whoever
     * held `staff.order.manage`, outside the command bus: a support agent with an organization of their own declared their
     * unpaid order paid, the `order.paid` event provisioned it, and no money, no reservation and no tax document ever existed.
     *
     * An order with running services is not cancelled here — its services are (each with its final backup and, where
     * support approved it, the return of the unused period).
     */
    public function cancel(Order $order, CommandContext $context, ?string $reason, bool $staff): Order
    {
        $allowed = $staff ? self::STAFF_CANCELLABLE : self::CUSTOMER_CANCELLABLE;
        if (! in_array($order->state, $allowed, true)) {
            if (! $staff) {
                throw DomainError::forbidden('Customers can only cancel orders that are not paid yet.');
            }
            throw new DomainError('order_not_cancellable', $order->state === OrderStateMachine::CANCELLED ? 'The order is already cancelled.' : "An order that is {$order->state} has services being delivered or running; cancel the services instead.", 409, ['state' => $order->state]);
        }
        if ($staff && $order->paid_at !== null && trim((string) $reason) === '') {
            throw new DomainError('reason_required', 'Uveďte důvod zrušení zaplacené objednávky (zapíše se do auditu a na opravný doklad).', 422, ['field' => 'reason']);
        }

        return $this->transition($order, OrderStateMachine::CANCELLED, $context, $reason, $allowed);
    }

    /** @param list<string>|null $onlyFrom the states the caller decided on; the locked row must still be in one (an approval may have paid it meanwhile) */
    public function transition(Order $order, string $to, CommandContext $context, ?string $note = null, ?array $onlyFrom = null): Order
    {
        return DB::transaction(function () use ($order, $to, $context, $note, $onlyFrom) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($onlyFrom !== null && ! in_array($order->state, $onlyFrom, true)) {
                throw new DomainError('order_state_changed', "Objednávka se mezitím změnila (je {$order->state}); načtěte ji znovu.", 409, ['state' => $order->state]);
            }
            OrderStateMachine::machine()->assertTransition($order->state, $to);
            $from = $order->state;
            $patch = ['state' => $to];
            if ($to === OrderStateMachine::ACTIVE) {
                $patch['activated_at'] = now();
            }
            if ($to === OrderStateMachine::CANCELLED) {
                $patch['cancelled_at'] = now();
                if ($order->promo_code !== null && $order->paid_at === null) { // an order nobody paid gives its use of the code back
                    PromoCode::query()->where('code', $order->promo_code)->where('uses', '>', 0)->decrement('uses');
                }
                if ($order->wallet_hold_id !== null) {
                    $hold = WalletHold::query()->find($order->wallet_hold_id);
                    if ($hold !== null && $hold->isActive()) {
                        $this->wallets->release($hold, 'order cancelled', $context);
                    }
                }
                // an unpaid order leaves nothing behind: its proforma is voided and the bank transfer stops being matched
                if ($order->invoice_id !== null) {
                    $proforma = Invoice::query()->find($order->invoice_id);
                    if ($proforma !== null && $proforma->type === 'proforma' && (int) $proforma->paid_minor === 0 && in_array($proforma->state, [Invoice::DRAFT, Invoice::ISSUED, Invoice::OVERDUE], true)) {
                        $this->invoices->cancelProforma($proforma, $context, "Objednávka {$order->number} zrušena".($note !== null && $note !== '' ? " · {$note}" : ''));
                    }
                }
                if ($order->payment_intent_id !== null) {
                    $intent = PaymentIntent::query()->find($order->payment_intent_id);
                    if ($intent !== null) {
                        $this->payments->cancel($intent, $context, "order {$order->number} cancelled");
                    }
                }
            }
            $order->forceFill($patch)->save();
            $returned = $to === OrderStateMachine::CANCELLED && $order->paid_at !== null ? $this->giveBackCancelled($order, $context, $note) : [];
            $this->audit->record($context->withScope($order->organization_id), 'order.transition', 'succeeded', ['from' => $from, 'to' => $to, 'note' => $note] + ($returned !== [] ? ['returned' => $returned['returned'], 'credit_notes' => $returned['credit_notes']] : []), 'order', $order->id);
            $this->outbox->publish(GenericEvent::of('order.'.strtolower($to), 'order', $order->id, ['number' => $order->number, 'from' => $from, 'note' => $note] + $returned, $order->organization_id));

            return $order;
        }, 3);
    }

    /**
     * A PAID order that is cancelled (a rejected review, staff on the customer's request) was documented the moment it was
     * paid: a statement for credit, a tax invoice with a receivable for postpaid. Releasing the reservation gave the money
     * back, but the document stood — a postpaid customer kept owing (and was dunned) for an order that was never delivered,
     * a prepaid one kept a statement for services they never got. Every tax document of the order is credited for what it
     * has left (nothing, when the settlement already gave the undelivered lines back), and the lines say so.
     *
     * @return array{}|array{returned:Money, to:string, credit_notes:list<string>, items:list<string>}
     */
    private function giveBackCancelled(Order $order, CommandContext $context, ?string $note): array
    {
        $reason = "Objednávka {$order->number} zrušena".($note !== null && trim($note) !== '' ? ' · '.trim($note) : '');
        $notes = [];
        $minor = 0;
        // only lines that were never delivered: a line whose service runs (or is being set up) is not given back by cancelling the order
        $open = OrderItem::query()->where('order_id', $order->id)->whereIn('state', ['pending', 'failed', 'refunded'])->get();
        foreach (Invoice::query()->where('order_id', $order->id)->whereIn('type', ['statement', 'invoice'])->orderBy('created_at')->get() as $document) {
            $lineIds = $document->lines()->whereIn('order_item_id', $open->pluck('id')->all())->pluck('id')->all();
            $credit = $lineIds === [] ? null : $this->invoices->creditRemaining($document, $reason, $context->withScope($order->organization_id), $lineIds);
            if ($credit !== null) {
                $notes[] = (string) $credit->number;
                $minor += abs((int) $credit->total_minor);
            }
        }
        OrderItem::query()->whereIn('id', $open->pluck('id')->all())->update(['state' => 'refunded']);
        if ($notes === []) {
            return [];
        }

        return ['returned' => Money::minor($minor, $order->currency), 'to' => $order->payment_mode === 'postpaid' ? 'invoice' : 'credit', 'credit_notes' => $notes, 'items' => $open->map(fn (OrderItem $i) => (string) $i->name)->values()->all()];
    }

    /** Content identity of an order attempt: organization, payment mode, lines (sku, qty, period, config) and totals. */
    public static function fingerprint(Quote $quote, Organization $organization, string $mode): string
    {
        $lines = array_map(fn ($l) => [$l['sku'], (int) $l['qty'], $l['period'] ?? null, $l['config'] ?? []], $quote->lines);
        usort($lines, fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));

        return hash('sha256', json_encode([$organization->id, $mode, $quote->currency, $lines, (int) $quote->total_minor, (int) ($quote->versions['commit_months'] ?? 1)]));
    }

    /** Documents the customer must accept for this quote (§23.7, §46.3). @return list<string> */
    public function requiredDocuments(Quote $quote, Organization $organization): array
    {
        $docs = ['terms', 'privacy'];
        if ($organization->isB2b()) {
            $docs[] = 'dpa';
        } else {
            $docs[] = 'withdrawal_waiver'; // immediate provisioning inside the 14-day period requires explicit request (ČOI/108/2024)
        }
        foreach ($quote->lines as $line) {
            if ($line['product_key'] === 'domain') {
                $docs[] = 'registrar_terms';
                $docs[] = 'registry_terms_'.$line['config']['tld'];
            }
            if (($line['config']['sla_class'] ?? 'standard') !== 'standard') {
                $docs[] = 'sla';
            }
        }

        return array_values(array_unique(array_filter($docs, fn ($k) => ConsentDocument::current($k) !== null || str_starts_with($k, 'registry_terms_'))));
    }

    private function recordConsents(Order $order, Organization $organization, ?User $user, array $consents, CommandContext $context): array
    {
        $ids = [];
        foreach ($consents as $key => $data) {
            $document = ConsentDocument::current($key);
            $version = (string) ($data['version'] ?? $document?->version ?? 'unversioned');
            $consent = Consent::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $user?->id,
                'order_id' => $order->id,
                'kind' => str_starts_with($key, 'registry_terms_') ? 'registry_terms' : $key,
                'document_key' => $key,
                'document_version' => $version,
                'document_hash' => $document?->hash,
                'document_url' => $document?->url ?? ($data['url'] ?? null),
                'language' => $data['language'] ?? $organization->locale,
                'person' => $data['person'] ?? $user?->name,
                'ip' => $context->ip,
                'user_agent' => $context->userAgent,
                'accepted_at' => now(),
                'evidence' => ['request_id' => $context->requestId, 'correlation_id' => $context->correlationId, 'quote_id' => $order->quote_id],
            ]);
            $ids[] = $consent->id;
        }

        return $ids;
    }

    private function hasDomain(Order $order): bool
    {
        return $order->items()->where('product_key', 'domain')->exists();
    }

    private function allocateNumber(): string
    {
        $year = AccountingClock::year(); // the order's number and its document's number belong to the same year
        DB::table('order_sequences')->insertOrIgnore(['year' => $year, 'next' => 1000]);
        $row = DB::table('order_sequences')->where('year', $year)->lockForUpdate()->first();
        $next = (int) $row->next + 1;
        DB::table('order_sequences')->where('year', $year)->update(['next' => $next]);

        return PublicId::documentNumber('OH', $year, $next, 4);
    }
}
