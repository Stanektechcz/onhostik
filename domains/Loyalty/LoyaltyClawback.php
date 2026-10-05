<?php

declare(strict_types=1);

namespace Onhost\Domain\Loyalty;

use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Loyalty\Models\LoyaltyPoint;
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

    /** @return int points taken back (positive), 0 when nothing applied */
    public function onCreditNote(string $organizationId, string $creditNoteId, CommandContext $context): int
    {
        $note = Invoice::query()->find($creditNoteId);
        if ($note === null || $note->type !== 'credit_note' || $note->corrects_invoice_id === null) {
            return 0;
        }
        $original = Invoice::query()->find($note->corrects_invoice_id);
        if ($original === null || $original->organization_id !== $organizationId || $note->organization_id !== $organizationId) {
            return 0;
        }
        $total = (int) $original->total_minor;
        $credit = abs((int) $note->total_minor);
        if ($total <= 0 || $credit === 0) {
            return 0;
        }
        $full = (int) $original->credited_minor >= $total;
        $taken = 0;

        if ($original->order_id !== null) {
            $taken += $this->takeOrderPoints($organizationId, (string) $original->order_id, (string) $note->id, (string) $note->number, $credit, $total, $full, $context);
        }
        if ($full) {
            $taken += $this->takePaymentPoints($organizationId, $original, (string) $note->number, $context);
        }

        return $taken;
    }

    private function takeOrderPoints(string $organizationId, string $orderId, string $noteId, string $number, int $credit, int $total, bool $full, CommandContext $context): int
    {
        $earned = (int) LoyaltyPoint::query()->where('organization_id', $organizationId)->where('rule', 'order.paid')->where('reference', $orderId)->sum('points');
        $already = -(int) LoyaltyPoint::query()->where('organization_id', $organizationId)->where('rule', self::ORDER_RULE)->where('reference', 'like', $orderId.':%')->sum('points');
        $want = $full ? $earned - $already : intdiv($earned * min($credit, $total), $total);
        $take = min($want, $earned - $already);

        return $take > 0 ? $this->loyalty->clawback($organizationId, self::ORDER_RULE, $orderId.':'.$noteId, $take, "Dobropis {$number}", $context)['taken'] : 0;
    }

    private function takePaymentPoints(string $organizationId, Invoice $original, string $number, CommandContext $context): int
    {
        $intents = PaymentIntent::query()->where('organization_id', $organizationId)->where(function ($q) use ($original) {
            $q->where(fn ($w) => $w->where('reference_type', 'invoice')->where('reference_id', $original->id));
            if ($original->order_id !== null) {
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
