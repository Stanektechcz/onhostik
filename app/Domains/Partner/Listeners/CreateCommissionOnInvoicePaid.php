<?php

declare(strict_types=1);

namespace App\Domains\Partner\Listeners;

use App\Domains\Billing\Events\InvoicePaid;
use App\Domains\Partner\Enums\CommissionStatus;
use App\Domains\Partner\Enums\PartnerStatus;
use App\Domains\Partner\Models\PartnerCommission;
use App\Domains\Partner\Models\PartnerReferral;
use App\Domains\Partner\Services\ReferralTracker;
use App\Notifications\PartnerCommissionCreatedNotification;
use Illuminate\Support\Facades\DB;

/**
 * Creates a PartnerCommission when a referred customer pays an order invoice.
 *
 * Idempotency: unique constraint on partner_commissions.invoice_id prevents
 * duplicate rows even if InvoicePaid fires more than once.
 *
 * Excluded from commission:
 *  - credit top-up invoices (purpose = 'credit_topup')
 *  - renewals (purpose = 'renewal') — only first order counts
 *  - self-referrals (partner.user_id == customer.user_id)
 *  - banned/paused partner profiles
 *  - invoices from customers with no referral record
 */
final class CreateCommissionOnInvoicePaid
{
    public function __construct(private ReferralTracker $tracker) {}

    public function handle(InvoicePaid $event): void
    {
        $invoice = $event->invoice;

        // Only commissionable invoice purposes
        $commissionablePurposes = config('partner.commissionable_purposes', ['order']);
        if (!in_array($invoice->purpose, $commissionablePurposes, true)) {
            return;
        }

        // Quick idempotency check before acquiring any lock
        if (PartnerCommission::where('invoice_id', $invoice->id)->exists()) {
            return;
        }

        // Find the referral for this customer
        $referral = PartnerReferral::where('referred_customer_id', $invoice->customer_id)
            ->with('partnerProfile')
            ->first();

        if ($referral === null) {
            return;
        }

        $profile = $referral->partnerProfile;

        if ($profile === null || $profile->status !== PartnerStatus::Active) {
            return;
        }

        // Self-referral guard
        if (
            config('partner.self_referral_blocked', true) &&
            $invoice->loadMissing('customer')->customer?->user_id === $profile->user_id
        ) {
            return;
        }

        // Calculate commission from invoice subtotal (excl. VAT)
        $subtotalMinor = (int) DB::table('invoices')
            ->where('id', $invoice->id)
            ->value('subtotal');

        if ($subtotalMinor <= 0) {
            return;
        }

        $rate   = $profile->commission_rate_percent;
        $amount = (int) round($subtotalMinor * $rate / 100);

        if ($amount <= 0) {
            return;
        }

        $holdDays   = config('partner.commission_hold_days', 14);
        $eligibleAt = now()->addDays($holdDays);

        // Atomic insert — unique constraint on invoice_id prevents duplicates
        try {
            $commission = PartnerCommission::create([
                'partner_profile_id'  => $profile->id,
                'partner_referral_id' => $referral->id,
                'order_id'            => $invoice->order_id,
                'invoice_id'          => $invoice->id,
                'payment_id'          => $event->payment->id,
                'amount'              => $amount,
                'currency'            => $invoice->currency->value ?? 'CZK',
                'rate_percent'        => $rate,
                'status'              => CommissionStatus::Pending,
                'eligible_at'         => $eligibleAt,
            ]);

            // Mark referral as converted (idempotent)
            $this->tracker->markConverted($invoice->customer_id);

            // Notify partner via email — never block on notification failure
            try {
                $partnerUser = $profile->loadMissing('user')->user;
                if ($partnerUser?->email) {
                    $partnerUser->notify(new PartnerCommissionCreatedNotification($commission));
                }
            } catch (\Throwable) {
                // Notification failure must never break the payment flow
            }

        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Race condition or replay — silently ignore (idempotent)
        }
    }
}
