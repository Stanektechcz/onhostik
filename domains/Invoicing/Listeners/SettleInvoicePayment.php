<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing\Listeners;

use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Payments\Events\PaymentSucceeded;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Money\Money;

/**
 * A verified bank transfer or gateway payment made for an invoice (postpaid documents paid "by bank" or "by card"
 * from the panel): the settled amount was credited to the wallet by PaymentService::settle(), so the invoice is paid
 * from the wallet the same way an explicit "pay from credit" would be — one ledger path for every payment method.
 */
final class SettleInvoicePayment
{
    public function __construct(private readonly InvoiceService $invoices, private readonly WalletService $wallets) {}

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
        $tax = Money::minor((int) round($invoice->tax_minor * ($open->minor / max(1, $invoice->total_minor))), $invoice->currency);
        $this->wallets->charge($invoice->organization_id, $open, $invoice->meta['revenue_family'] ?? 'services', "invoice:{$invoice->id}:payment:{$intent->id}", $context, 'invoice', $invoice->id, $tax);
        $this->invoices->markPaid($invoice, $open, $intent->method ?? $intent->provider, $context, postLedger: false, paymentReference: $intent->id);
    }
}
