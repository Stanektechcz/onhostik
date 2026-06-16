<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\Payment;
use App\Models\User;
use InvalidArgumentException;

/**
 * Records a MANUAL refund against a completed payment.
 *
 * No payment gateway wired into this system currently exposes a refund
 * API (ComgateGateway has createPayment/getStatus/verifyWebhookSource —
 * no refund() method). This action therefore never calls out to a
 * gateway; it only marks the payment as PaymentStatus::ManualRefund and
 * records who/when/why in the audit log. The admin must still issue the
 * real money movement through the gateway's own dashboard.
 *
 * Kept distinct from PaymentStatus::Refunded on purpose: that status is
 * reserved for a future real gateway-refund integration, so the two can
 * never be confused once one exists.
 */
final class RefundPaymentAction
{
    public function execute(Payment $payment, User $actor, string $reason): Payment
    {
        if ($payment->status !== PaymentStatus::Completed) {
            throw new InvalidArgumentException('Only completed payments can be refunded.');
        }

        $payment->update(['status' => PaymentStatus::ManualRefund]);

        activity('payment')
            ->performedOn($payment)
            ->causedBy($actor)
            ->withProperties([
                'reason'      => $reason,
                'refund_type' => 'manual',
                'gateway_call' => false,
            ])
            ->log('payment.manual_refund_recorded');

        return $payment;
    }
}
