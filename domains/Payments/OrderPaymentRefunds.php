<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Billing\WithdrawalPolicy;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\Models\InvoiceLine;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentRefund;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/**
 * G6 (owner decisions G-R1, G-R4): a consumer who withdrew from the contract within fourteen days and did not agree to take the
 * money as credit gets the payment of the ORDER back to where it came from — the card, or the bank account (a payout finance
 * sends by hand and confirms, PaymentService::confirmRefund).
 *
 *  · only a payment of an order (purpose `order`): a top-up became credit, and credit is never paid out in money;
 *  · only an order placed as a consumer, and only for a notice sent within the fourteen days (WithdrawalPolicy's calendar);
 *  · never more than is left of the payment, nor of the order's document: the refund is a credit note of that document first —
 *    what another credit note already took off it (a withdrawal to the credit, a correction) is never paid out a second time;
 *  · the credit note takes the revenue and its VAT back into `liability:refund_payable:<provider>`, which the payout empties.
 *    Nothing comes back to the credit (that would be the money twice).
 *
 * A document booked at issue (a postpaid invoice) is not refunded here: its credit note returns what was paid to the credit by
 * itself (InvoiceService::unbook), so a card refund beside it would pay twice (`refund_document_booked`).
 */
final class OrderPaymentRefunds
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly InvoiceService $invoices,
        private readonly WithdrawalPolicy $policy,
        private readonly LedgerService $ledger,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * Whether what a payment has been refunded so far plus this refund reaches the approval threshold of its currency (an unknown
     * currency counts as reaching it). The command asks it before the bus decides on a second person; the refund asks it again
     * under the payment's lock (security review of PR #111, M1), so two requests that each read the payment before the other
     * landed cannot both pass below it.
     */
    public static function reachesApprovalThreshold(int $refundedWithThis, string $currency): bool
    {
        $threshold = config('onhost.billing.refund_approval_threshold.'.strtoupper($currency));

        return $threshold === null || $refundedWithThis >= (int) $threshold;
    }

    /**
     * `$approved`: the bus asked a second person for this refund (the command was large when it was dispatched). `$ticketId`: the
     * consumer's notice on record — a ticket of the payment's organization (security review M2).
     *
     * The gateway is called inside this transaction (review L): the credit note, the payable and the refund row commit with the
     * gateway's answer or not at all. A gateway that refunded and a commit that then failed leaves money paid out without a row:
     * the retry under the same key reaches the gateway with the same idempotency key (Comgate refId, Stripe key) and is answered
     * with the refund it already made; reconciliation reports any other mismatch. A pending row before the call would need a
     * compensation of the credit note when the gateway refuses — a document that cannot be taken back — so it is not done.
     *
     * @return array{refund:PaymentRefund, credit_note:Invoice}
     */
    public function refundOnWithdrawal(PaymentIntent $intent, Money $amount, CarbonImmutable $sentAt, string $reason, string $idempotencyKey, CommandContext $context, bool $approved = false, string $ticketId = ''): array
    {
        return DB::transaction(function () use ($intent, $amount, $sentAt, $reason, $idempotencyKey, $context, $approved, $ticketId) {
            $intent = PaymentIntent::query()->lockForUpdate()->findOrFail($intent->id);
            $existing = PaymentRefund::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) { // the same request again: the refund it made (PaymentService refuses another payment or amount under the key)
                $refund = $this->payments->refund($intent, $amount, $reason, $idempotencyKey, $context);

                return ['refund' => $refund, 'credit_note' => Invoice::query()->findOrFail($refund->credit_note_id)];
            }
            $this->assertEvidence($intent, $ticketId);
            $this->assertRefundable($intent, $amount);
            if (! $approved && self::reachesApprovalThreshold((int) $intent->refunded_minor + $amount->minor, $amount->currency->value)) {
                throw new DomainError('refund_approval_required', 'With what this payment was refunded already, this refund needs a second person: send it again to ask for the approval.', 409, ['refunded' => Money::minor((int) $intent->refunded_minor, $amount->currency)]);
            }
            $order = Order::query()->where('organization_id', $intent->organization_id)->find((string) $intent->reference_id)
                ?? throw new DomainError('refund_order_missing', 'The order this payment paid is not there.', 409);
            $this->assertWithdrawal($order, $sentAt);
            $document = $this->documentOf($order);
            $note = $this->unpaidCreditNote($intent, $amount);
            if ($note === null) {
                $amounts = $this->amounts($document, $amount);
                $this->assertServicesEnded(InvoiceLine::query()->whereIn('id', array_keys($amounts))->pluck('service_id')->filter()->all());
                $note = $this->invoices->creditNote($document, $reason, $context->withScope($intent->organization_id), null, null, $amounts);
                $this->recognisePayable($intent, $document, $note, $context);
            } else {
                $this->assertServicesEnded($note->lines()->pluck('service_id')->filter()->all());
            }
            $refund = $this->payments->refund($intent, $amount, $reason, $idempotencyKey, $context, $note->id);
            $this->audit->record($context->withScope($intent->organization_id), 'payment.refund.withdrawal', 'succeeded', ['payment' => $intent->id, 'order' => $order->number, 'document' => $document->number, 'credit_note' => $note->number, 'amount' => $amount, 'sent_at' => $sentAt->toIso8601String(), 'state' => $refund->state, 'ticket' => $ticketId, 'approved' => $approved], 'payment_intent', $intent->id);

            return ['refund' => $refund, 'credit_note' => $note];
        }, 3);
    }

    /** The consumer's notice is on record: a ticket of the payment's own organization (security review M2). */
    private function assertEvidence(PaymentIntent $intent, string $ticketId): void
    {
        $ticket = $ticketId === '' ? null : Ticket::query()->find($ticketId);
        if ($ticket === null || (string) $ticket->organization_id !== (string) $intent->organization_id) {
            throw new DomainError('refund_evidence_mismatch', 'Name the ticket of this customer that holds their withdrawal notice.', 422, ['field' => 'ticket_id']);
        }
    }

    /**
     * Money back for a service the customer keeps using is no withdrawal (security review M2): every service of the refunded lines
     * must have ended — cancelled through the ordinary saga — before its money goes back.
     *
     * @param  array<int, mixed>  $serviceIds
     */
    private function assertServicesEnded(array $serviceIds): void
    {
        $running = Service::query()->whereIn('id', array_values(array_unique(array_map('strval', $serviceIds))))->whereNotIn('state', [ServiceStateMachine::TERMINATED, ServiceStateMachine::FAILED])->pluck('id')->all();
        if ($running !== []) {
            throw new DomainError('refund_service_still_running', 'A service of the refunded lines still runs: cancel it first, then refund its money.', 409, ['services' => $running]);
        }
    }

    /**
     * A credit note whose payout was cancelled (a bank payout returned, a wrong account) and that no other refund pays out: the next
     * payout of the same amount uses it, so the document is not credited twice and the payable is not posted twice (review L).
     */
    private function unpaidCreditNote(PaymentIntent $intent, Money $amount): ?Invoice
    {
        $cancelled = PaymentRefund::query()->where('payment_intent_id', $intent->id)->where('state', 'cancelled')->where('amount_minor', $amount->minor)->whereNotNull('credit_note_id')->orderBy('created_at')->pluck('credit_note_id');
        foreach ($cancelled as $noteId) {
            if (! PaymentRefund::query()->where('credit_note_id', $noteId)->whereIn('state', ['pending', 'succeeded'])->exists()) {
                return Invoice::query()->find($noteId);
            }
        }

        return null;
    }

    private function assertRefundable(PaymentIntent $intent, Money $amount): void
    {
        if ($intent->purpose === 'topup') {
            throw new DomainError('topup_not_refundable', 'A top-up became credit; credit is spent on services and is never paid back in money.', 422);
        }
        if ($intent->purpose !== 'order' || $intent->reference_type !== 'order' || (string) $intent->reference_id === '') {
            throw new DomainError('refund_payment_not_order', 'Only the payment of an order is refunded to its source on a withdrawal.', 422, ['purpose' => $intent->purpose]);
        }
        if (! $intent->isSucceeded()) {
            throw new DomainError('payment_not_refundable', 'Only successful payments can be refunded.', 409);
        }
        if (! $amount->isPositive() || $amount->currency->value !== strtoupper((string) $intent->currency)) {
            throw new DomainError('refund_currency_mismatch', 'A refund is a positive amount in the currency of the payment.', 422, ['field' => 'amount']);
        }
        if ((int) $intent->refunded_minor + $amount->minor > (int) $intent->amount_minor) {
            throw new DomainError('refund_exceeds_payment', 'Refund exceeds what is left of the payment.', 409, ['left' => Money::minor(max(0, (int) $intent->amount_minor - (int) $intent->refunded_minor), (string) $intent->currency)]);
        }
    }

    /** A consumer's order and a notice sent within its fourteen days (the same calendar as the withdrawal in the panel). */
    private function assertWithdrawal(Order $order, CarbonImmutable $sentAt): void
    {
        $class = $this->policy->classAtOrder($order);
        if ($class !== 'b2c') {
            throw new DomainError('withdrawal_consumers_only', 'Odstoupit od smlouvy bez udání důvodu může jen spotřebitel; objednávka byla uzavřena na firmu.', 403, ['customer_class' => $class]);
        }
        $start = CarbonImmutable::make($order->placed_at) ?? throw new DomainError('withdrawal_not_applicable', 'Objednávka nebyla odeslána.', 422, ['why' => 'no_order']);
        if ($sentAt->greaterThan(CarbonImmutable::now())) {
            throw new DomainError('withdrawal_sent_in_future', 'Odstoupení nemůže být odesláno v budoucnu.', 422, ['field' => 'sent_at']);
        }
        if ($sentAt->lessThan($start)) {
            throw new DomainError('withdrawal_sent_before_order', 'Odstoupení nemůže předcházet objednávce.', 422, ['field' => 'sent_at']);
        }
        $deadline = WithdrawalPolicy::deadlineFrom($start);
        if ($sentAt->greaterThan($deadline)) {
            throw new DomainError('withdrawal_period_over', 'Lhůta 14 dnů pro odstoupení uplynula '.$deadline->format('j. n. Y').'.', 409, ['deadline' => $deadline->toIso8601String()]);
        }
    }

    private function documentOf(Order $order): Invoice
    {
        $document = Invoice::query()->where('order_id', $order->id)->whereIn('type', ['statement', 'invoice'])->whereNotIn('state', [Invoice::DRAFT, Invoice::CANCELLED])->orderBy('created_at')->first()
            ?? throw new DomainError('refund_document_missing', 'The order has no document to correct with a credit note.', 409);
        if ($document->bookedAtIssue()) {
            throw new DomainError('refund_document_booked', 'A postpaid invoice returns what was paid to the credit by its own credit note; it is not refunded to the card.', 409, ['document' => $document->number]);
        }

        return $document;
    }

    /**
     * The refund as gross amounts of the document's lines, in their order: each line gives what it has left until the amount is
     * reached. More than the document has left is refused.
     *
     * @return array<string,int>
     */
    private function amounts(Invoice $document, Money $amount): array
    {
        $credited = $this->invoices->creditedByLine($document);
        $remaining = $amount->minor;
        $out = [];
        foreach ($document->lines()->get() as $line) {
            $left = (int) $line->total_minor - (int) ($credited[$line->id]['total'] ?? 0);
            if ($remaining <= 0 || $left <= 0) {
                continue;
            }
            $take = min($left, $remaining);
            $out[(string) $line->id] = $take;
            $remaining -= $take;
        }
        if ($remaining > 0) {
            throw new DomainError('refund_exceeds_document', 'Refund exceeds what is left of the order\'s document after its credit notes.', 409, ['document' => $document->number, 'left' => Money::minor($amount->minor - $remaining, $document->currency)]);
        }

        return $out;
    }

    /**
     * The credit note's revenue and VAT go back — to the payout the customer is owed, not to the credit. The document records the
     * paid part as given back, so a later credit note of it (InvoiceService::giveBack) never returns it to the credit as well.
     */
    private function recognisePayable(PaymentIntent $intent, Invoice $document, Invoice $note, CommandContext $context): void
    {
        $gross = Money::minor(abs((int) $note->total_minor), $note->currency);
        $tax = Money::minor(abs((int) $note->tax_minor), $note->currency);
        $postings = WalletService::revenueReturn($gross, $tax);
        $postings[] = ['account' => PaymentService::refundPayableAccount((string) $intent->provider, $gross->currency->value), 'credit' => $gross->minor];
        $this->ledger->post('refund_payable', $gross->currency, $postings, "refund-payable:{$note->id}", $intent->organization_id, 'invoice', $note->id, "Credit note {$note->number} for {$document->number}: refunded to the source of payment {$intent->id}", $context->actorType.':'.($context->actorId ?? 'system'));
        $document = Invoice::query()->lockForUpdate()->findOrFail($document->id);
        $document->forceFill(['meta' => array_merge((array) $document->meta, ['overpaid_returned_minor' => (int) ($document->meta['overpaid_returned_minor'] ?? 0) + $gross->minor])])->save();
    }
}
