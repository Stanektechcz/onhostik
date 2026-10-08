<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Domain\Payments\OrderPaymentRefunds;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * G6, finance: `refund.withdrawal` gives the payment of an order back to its source on a consumer's withdrawal (payment_id,
 * amount_minor, sent_at, reason) — money leaves the company: HIGH with a fresh step-up, and from the refund approval threshold
 * on (`onhost.billing.refund_approval_threshold`) CRITICAL behind a second person. `refund.confirm` records that the bank payout
 * of a pending refund was sent (refund_id, reference) — the customer is then told the money is on its way back: HIGH, step-up, and
 * another person than who asked for the refund (unless one operator runs the platform). `refund.cancel` cancels a pending bank
 * payout and releases what it reserved of the payment (refund_id); its credit note waits for the next payout: HIGH, step-up.
 * `refund.withdrawal_payout` (L-06) pays out what a withdrawal without the agreement to the credit owes the order's payment
 * (withdrawal_id); `refund.statutory` (L-09) gives a consumer's statutory money back to the payment of the order (payment_id,
 * amount_minor, basis, ticket_id, credit_note_id?). Both pay money out: as `refund.withdrawal`.
 */
final class PaymentRefundCommand extends GlobalCommand implements RiskAwareCommand
{
    public const OPS = ['refund.withdrawal', 'refund.confirm', 'refund.cancel', 'refund.withdrawal_payout', 'refund.statutory'];

    /** the operations that pay money out of a payment (the rest only confirm or cancel a payout already asked for) */
    private const PAYING = ['refund.withdrawal', 'refund.withdrawal_payout', 'refund.statutory'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): string
    {
        return $this->large() ? 'billing.refund.execute_large' : 'billing.refund.execute';
    }

    public function name(): string
    {
        return 'payments.'.$this->op();
    }

    public function riskLevel(): string
    {
        return $this->large() ? PermissionCatalog::CRITICAL : PermissionCatalog::HIGH;
    }

    public function requiresStepUp(): bool
    {
        return true;
    }

    public function requiresApproval(): bool
    {
        return $this->large();
    }

    /**
     * A refund that brings what was refunded of the payment to the approval threshold of its currency or above (an unknown
     * currency counts as large): splitting one refund into smaller ones does not get round the second person.
     */
    private function large(): bool
    {
        if (! in_array($this->op(), self::PAYING, true)) {
            return false;
        }

        // a first look; OrderPaymentRefunds asks again under the payment's lock and refuses when this one was too optimistic (M1)
        return OrderPaymentRefunds::reachesApprovalThreshold((int) $this->get('amount_minor', 0) + (int) $this->get('payment_refunded_minor', 0), (string) $this->get('currency', 'CZK'));
    }
}
