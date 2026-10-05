<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * G6, finance: `refund.withdrawal` gives the payment of an order back to its source on a consumer's withdrawal (payment_id,
 * amount_minor, sent_at, reason) — money leaves the company: HIGH with a fresh step-up, and from the refund approval threshold
 * on (`onhost.billing.refund_approval_threshold`) CRITICAL behind a second person. `refund.confirm` records that the bank payout
 * of a pending refund was sent (refund_id, reference) — the customer is then told the money is on its way back: HIGH, step-up.
 */
final class PaymentRefundCommand extends GlobalCommand implements RiskAwareCommand
{
    public const OPS = ['refund.withdrawal', 'refund.confirm'];

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
        if ($this->op() !== 'refund.withdrawal') {
            return false;
        }
        $threshold = config('onhost.billing.refund_approval_threshold.'.strtoupper((string) $this->get('currency', 'CZK')));

        return $threshold === null || (int) $this->get('amount_minor', 0) + (int) $this->get('payment_refunded_minor', 0) >= (int) $threshold;
    }
}
