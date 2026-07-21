<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Enums\RefundDestination;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Services\CreditLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Records a refund against a completed payment.
 *
 * No payment gateway wired into this system exposes a refund API
 * (ComgateGateway has createPayment/getStatus/verifyWebhookSource — no
 * refund()). So returning money to the original card is necessarily a
 * manual step the admin performs in the gateway's own dashboard; this
 * action records that obligation rather than pretending to fulfil it.
 *
 * The credit destination, by contrast, IS fully automatic: the amount is
 * deposited to the customer's credit balance here and now.
 *
 * PaymentStatus::Refunded stays reserved for a future gateway-confirmed
 * refund, so it can never be confused with ManualRefund.
 */
final class RefundPaymentAction
{
    public function __construct(
        private readonly CreditLedger $creditLedger,
    ) {}

    public function execute(
        Payment $payment,
        User $actor,
        string $reason,
        RefundDestination $destination = RefundDestination::OriginalMethod,
    ): Payment {
        if ($payment->status !== PaymentStatus::Completed) {
            throw new InvalidArgumentException('Only completed payments can be refunded.');
        }

        DB::transaction(function () use ($payment, $reason, $destination): void {
            $payment->update([
                'status'             => PaymentStatus::ManualRefund,
                'refund_destination' => $destination->value,
                'refund_reason'      => mb_substr($reason, 0, 500),
                'refunded_at'        => now(),
            ]);

            // Credit is the one destination we can actually settle ourselves.
            if ($destination === RefundDestination::Credit && $payment->customer !== null) {
                $this->creditLedger->deposit(
                    $payment->customer,
                    $payment->amount,
                    "Refundace platby #{$payment->id}: {$reason}",
                );
            }
        });

        activity('payment')
            ->performedOn($payment)
            ->causedBy($actor)
            ->withProperties([
                'reason'          => $reason,
                'refund_type'     => 'manual',
                'destination'     => $destination->value,
                'gateway_call'    => false,
                'manual_action_required' => $destination->requiresManualAction(),
            ])
            ->log('payment.manual_refund_recorded');

        return $payment;
    }
}
