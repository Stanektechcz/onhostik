<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing\Listeners;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Invoicing\CzkTaxStatement;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\OrderSettlement;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Events\PaymentSucceeded;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/**
 * A verified bank transfer or gateway payment made for an invoice (postpaid documents paid "by bank" or "by card"
 * from the panel): the settled amount was credited to the wallet by PaymentService::settle(), so the invoice is paid
 * from the wallet the same way an explicit "pay from credit" would be — one ledger path for every payment method.
 *
 * The invoice is the tax document of the sale (G1, owner decision G-R1): the payment is matched to it and gets no receipt of
 * its own. What the invoice no longer owes stays as credit and is documented like a top-up: the part a credit note took off
 * meanwhile, or the whole payment when another one paid the invoice first.
 *
 * The invoice row is locked and what it still owes is read inside the transaction (security review of PR #103): two payments
 * for one invoice apply once, and the charge and the "paid" mark commit together or not at all.
 */
final class SettleInvoicePayment
{
    public function __construct(private readonly InvoiceService $invoices, private readonly WalletService $wallets, private readonly OrderSettlement $orders) {}

    public function handle(PaymentSucceeded $event): void
    {
        $intent = $event->intent;
        if ($intent->purpose !== 'invoice' || $intent->reference_type !== 'invoice') {
            return;
        }
        DB::transaction(function () use ($intent, $event) {
            $invoice = Invoice::query()->lockForUpdate()->find($intent->reference_id);
            if ($invoice === null || $invoice->organization_id !== $intent->organization_id) {
                return; // documented by PaymentService::document() as an ordinary payment
            }
            $context = $event->context->withScope($invoice->organization_id);
            $open = in_array($invoice->state, [Invoice::ISSUED, Invoice::OVERDUE], true) ? $invoice->outstanding() : Money::zero($invoice->currency);
            $applied = $open->greaterThan($intent->amount()) ? $intent->amount() : $open; // a payment pays what it brought, never more
            if ($applied->isPositive()) {
                $this->apply($invoice, $intent, $applied, $context);
            }
            $left = $intent->amount()->subtract($applied);
            if ($left->isPositive() && in_array($invoice->type, CzkTaxStatement::TYPES, true)) { // a proforma's payment has its receipt already
                $this->invoices->issueTopupDocument(Organization::query()->findOrFail($invoice->organization_id), $left, $intent->method ?? $intent->provider, $context, $intent->id);
            }
        }, 3);
    }

    private function apply(Invoice $invoice, PaymentIntent $intent, Money $applied, CommandContext $context): void
    {
        if ($invoice->bookedAtIssue()) { // the revenue and the VAT were booked when it was issued: the payment settles the receivable
            $this->orders->releaseReservation($invoice, $context);
            $this->wallets->settleReceivable($invoice->organization_id, $applied, "invoice:{$invoice->id}:payment:{$intent->id}", $context, 'invoice', $invoice->id, "Úhrada faktury {$invoice->number}");
        } else {
            $tax = Money::minor((int) round($invoice->tax_minor * ($applied->minor / max(1, $invoice->total_minor))), $invoice->currency);
            $this->wallets->charge($invoice->organization_id, $applied, $invoice->meta['revenue_family'] ?? 'services', "invoice:{$invoice->id}:payment:{$intent->id}", $context, 'invoice', $invoice->id, $tax, enforceBudget: false);
        }
        $paid = $this->invoices->markPaid($invoice, $applied, $intent->method ?? $intent->provider, $context, postLedger: false, paymentReference: $intent->id);
        if ((int) $paid->paid_minor !== (int) $invoice->paid_minor + $applied->minor) {
            // markPaid returned early: the charge above must not stay behind — the whole transaction rolls back
            throw new DomainError('invoice_payment_not_applied', "The payment could not be applied to {$invoice->number}.", 409);
        }
    }
}
