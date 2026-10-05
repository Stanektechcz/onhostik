<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing\Listeners;

use Onhost\Domain\Invoicing\CzkTaxStatement;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\OrderSettlement;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Events\PaymentSucceeded;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Money\Money;

/**
 * A verified bank transfer or gateway payment made for an invoice (postpaid documents paid "by bank" or "by card"
 * from the panel): the settled amount was credited to the wallet by PaymentService::settle(), so the invoice is paid
 * from the wallet the same way an explicit "pay from credit" would be — one ledger path for every payment method.
 *
 * The invoice is the tax document of the sale (G1, owner decision G-R1): the payment is matched to it and gets no receipt of
 * its own. Only what the invoice no longer owes — a credit note written while the customer was at the gateway — stays as
 * credit, and that money is documented like a top-up.
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
        $invoice = Invoice::query()->find($intent->reference_id);
        if ($invoice === null || ! in_array($invoice->state, [Invoice::ISSUED, Invoice::OVERDUE], true)) {
            return;
        }
        $open = $invoice->outstanding();
        if (! $open->isPositive()) {
            return;
        }
        $context = $event->context->withScope($invoice->organization_id);
        $applied = $open->greaterThan($intent->amount()) ? $intent->amount() : $open; // a payment pays what it brought, never more
        if ($invoice->bookedAtIssue()) { // the revenue and the VAT were booked when it was issued: the payment settles the receivable
            $this->orders->releaseReservation($invoice, $context);
            $this->wallets->settleReceivable($invoice->organization_id, $applied, "invoice:{$invoice->id}:payment:{$intent->id}", $context, 'invoice', $invoice->id, "Úhrada faktury {$invoice->number}");
        } else {
            $tax = Money::minor((int) round($invoice->tax_minor * ($applied->minor / max(1, $invoice->total_minor))), $invoice->currency);
            $this->wallets->charge($invoice->organization_id, $applied, $invoice->meta['revenue_family'] ?? 'services', "invoice:{$invoice->id}:payment:{$intent->id}", $context, 'invoice', $invoice->id, $tax, enforceBudget: false);
        }
        $this->invoices->markPaid($invoice, $applied, $intent->method ?? $intent->provider, $context, postLedger: false, paymentReference: $intent->id);
        $left = $intent->amount()->subtract($applied);
        if ($left->isPositive() && in_array($invoice->type, CzkTaxStatement::TYPES, true)) {
            $organization = Organization::query()->findOrFail($invoice->organization_id);
            $this->invoices->issueTopupDocument($organization, $left, $intent->method ?? $intent->provider, $context, $intent->id);
        }
    }
}
