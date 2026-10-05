<?php

declare(strict_types=1);

namespace Onhost\Domain\Loyalty;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Loyalty\Models\LoyaltyPoint;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Platform\Commands\CommandContext;

/**
 * Owner decision R6: points are taken back when the money is given back. A credit note is how every refund is booked,
 * a chargeback refund included (`ChargebackService` writes one per document), so a credit note on a document is the one
 * place this looks at.
 *
 * - The points the order earned (`order.paid`) go back in the proportion the document was credited: half the document,
 *   half the points; the last credit takes the remainder, so nothing is lost to rounding and nothing is taken twice.
 * - The points of the payments that settled the order or the document (`payment.on_time`) go back once the document is
 *   credited in full.
 * - Never more than the source earned, never from another organization (the credit note, its original and the event
 *   must all name the same one), and always once: a clawback is a row with its own rule and reference, so a redelivered
 *   event finds it already there. The promo credit of a level already reached stays (a level is not taken away).
 */
final class LoyaltyClawback
{
    public const ORDER_RULE = 'clawback.order';

    public const PAYMENT_RULE = 'clawback.payment';

    public function __construct(private readonly LoyaltyService $loyalty) {}

    /**
     * Read and write happen under a lock on the organization row, so two credit notes of one order handled together take
     * turns and the cumulative cap holds. The handler must run after the credit note committed (the outbox relays after
     * commit): "credited in full" is read from the documents as they stand when it runs.
     *
     * @return int points taken back (positive), 0 when nothing applied
     */
    public function onCreditNote(string $organizationId, string $creditNoteId, CommandContext $context): int
    {
        return DB::transaction(function () use ($organizationId, $creditNoteId, $context) {
            Organization::query()->whereKey($organizationId)->lockForUpdate()->first();
            $note = Invoice::query()->find($creditNoteId);
            if ($note === null || $note->type !== 'credit_note' || $note->corrects_invoice_id === null) {
                return 0;
            }
            $original = Invoice::query()->find($note->corrects_invoice_id);
            if ($original === null || $original->organization_id !== $organizationId || $note->organization_id !== $organizationId || (int) $original->total_minor <= 0) {
                return 0;
            }
            $taken = 0;
            $orderFull = false;
            if ($original->order_id !== null) {
                $order = Order::query()->where('organization_id', $organizationId)->find($original->order_id);
                if ($order !== null) {
                    [$points, $orderFull] = $this->takeOrderPoints($organizationId, $order, (string) $note->id, (string) $note->number, $context);
                    $taken += $points;
                }
            }
            $taken += $this->takePaymentPoints($organizationId, $original, $orderFull, (string) $note->number, $context);

            return $taken;
        });
    }

    /**
     * The base is the order: what its tax invoices have been credited in all, against the order total (or what its
     * invoices add up to when that is more). Only the invoices that stand count: not a proforma, a credit note or a draft.
     *
     * @return array{0:int, 1:bool} points taken, and whether the order is credited in full
     */
    private function takeOrderPoints(string $organizationId, Order $order, string $noteId, string $number, CommandContext $context): array
    {
        $invoices = Invoice::query()->where('organization_id', $organizationId)->where('order_id', $order->id)->whereNotIn('type', ['proforma', 'credit_note'])->whereNotIn('state', [Invoice::DRAFT, Invoice::CANCELLED])->get(['id', 'total_minor', 'credited_minor']);
        $base = max((int) $order->total_minor, (int) $invoices->sum('total_minor'));
        $credited = (int) $invoices->sum('credited_minor');
        if ($base <= 0 || $credited <= 0) {
            return [0, false];
        }
        $full = $credited >= $base;
        $earned = (int) LoyaltyPoint::query()->where('organization_id', $organizationId)->where('rule', 'order.paid')->where('reference', $order->id)->sum('points');
        $references = Invoice::query()->where('organization_id', $organizationId)->where('type', 'credit_note')->whereIn('corrects_invoice_id', $invoices->pluck('id'))->pluck('id')->map(fn ($id) => $order->id.':'.$id)->all();
        $already = -(int) LoyaltyPoint::query()->where('organization_id', $organizationId)->where('rule', self::ORDER_RULE)->whereIn('reference', $references)->sum('points');
        $target = $full ? $earned : intdiv($earned * $credited, $base);
        $take = min($target - $already, $earned - $already);

        return [$take > 0 ? $this->loyalty->clawback($organizationId, self::ORDER_RULE, $order->id.':'.$noteId, $take, "Dobropis {$number}", $context)['taken'] : 0, $full];
    }

    /**
     * F12b: a payment given back to its source without a credit note (`payment.refunded`, PaymentService::refund). Its points
     * (`payment.on_time` — an order payment or a credit top-up) go back once what is left of it would not have earned them:
     * refunded in full, or below the minimum payment (R6). The rule and reference are the credit-note path's, so a payment that
     * is both credited and refunded loses its points once. Only the payment of this organization counts.
     *
     * @return int points taken back (positive), 0 when nothing applied
     */
    public function onPaymentRefunded(string $organizationId, string $intentId, CommandContext $context): int
    {
        return DB::transaction(function () use ($organizationId, $intentId, $context) {
            Organization::query()->whereKey($organizationId)->lockForUpdate()->first();
            $intent = PaymentIntent::query()->where('organization_id', $organizationId)->find($intentId);
            if ($intent === null || $this->loyalty->qualifies(max(0, (int) $intent->amount_minor - (int) $intent->refunded_minor), (string) $intent->currency)) {
                return 0;
            }
            $earned = (int) LoyaltyPoint::query()->where('organization_id', $organizationId)->where('rule', 'payment.on_time')->where('reference', $intent->id)->sum('points');
            $already = -(int) LoyaltyPoint::query()->where('organization_id', $organizationId)->where('rule', self::PAYMENT_RULE)->where('reference', $intent->id)->sum('points');
            $take = $earned - $already;

            return $take > 0 ? $this->loyalty->clawback($organizationId, self::PAYMENT_RULE, (string) $intent->id, $take, 'Peníze se vrátily', $context)['taken'] : 0;
        });
    }

    /** The points of the payments for the order go with the order credited in full, those for the document with the document. */
    private function takePaymentPoints(string $organizationId, Invoice $original, bool $orderFull, string $number, CommandContext $context): int
    {
        $invoiceFull = (int) $original->credited_minor >= (int) $original->total_minor;
        $intents = PaymentIntent::query()->where('organization_id', $organizationId)->where(function ($q) use ($original, $orderFull, $invoiceFull) {
            $q->whereRaw('1 = 0');
            if ($invoiceFull) {
                $q->orWhere(fn ($w) => $w->where('reference_type', 'invoice')->where('reference_id', $original->id));
            }
            if ($orderFull && $original->order_id !== null) {
                $q->orWhere(fn ($w) => $w->where('reference_type', 'order')->where('reference_id', $original->order_id));
            }
        })->pluck('id');
        $taken = 0;
        foreach ($intents as $intentId) {
            $earned = (int) LoyaltyPoint::query()->where('organization_id', $organizationId)->where('rule', 'payment.on_time')->where('reference', $intentId)->sum('points');
            if ($earned > 0) {
                $taken += $this->loyalty->clawback($organizationId, self::PAYMENT_RULE, (string) $intentId, $earned, "Dobropis {$number}", $context)['taken'];
            }
        }

        return $taken;
    }
}
