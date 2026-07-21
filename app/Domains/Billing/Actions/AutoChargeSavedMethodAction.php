<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Shared\Support\LogContext;
use App\Models\SavedPaymentMethod;
use Illuminate\Support\Facades\Log;

/**
 * Attempts to settle a renewal invoice from the customer's saved payment
 * method (audit D39).
 *
 * Until now every renewal required the customer to log in and pay by hand —
 * the saved-card table existed but nothing ever charged it, so "recurring
 * billing" was recurring paperwork.
 *
 * Deliberate boundaries:
 *
 *  - **Opt-in.** Only runs when the customer has a default saved method. No
 *    silent charging of a card someone stored for one-off use.
 *  - **Never double-charges.** A paid invoice, or one with no amount due, is
 *    skipped outright — this runs on a schedule and WILL be retried.
 *  - **The real charge is gateway work.** No gateway here exposes a
 *    merchant-initiated recurring API yet, so in mock mode the charge is
 *    simulated and in real mode it declines cleanly rather than pretending.
 *    That keeps the pipeline honest instead of marking invoices paid on a
 *    charge that never happened.
 */
final class AutoChargeSavedMethodAction
{
    public function __construct(
        private readonly ProcessMockPaymentAction $mockPayment,
    ) {}

    /**
     * @return 'charged'|'skipped_not_payable'|'skipped_no_method'|'declined'
     */
    public function execute(Invoice $invoice): string
    {
        if (! $this->isPayable($invoice)) {
            return 'skipped_not_payable';
        }

        $customer = $invoice->customer;

        $method = SavedPaymentMethod::query()
            ->where('customer_id', $customer->id)
            ->where('is_default', true)
            ->first();

        if ($method === null) {
            return 'skipped_no_method';
        }

        return LogContext::with(
            ['invoice_id' => $invoice->id, 'customer_id' => $customer->id],
            function () use ($invoice, $method): string {
                if (! (bool) config('provisioning.mock_mode', true)) {
                    /*
                     | Real merchant-initiated charge needs the gateway's
                     | recurring API + a stored mandate. Declining loudly is
                     | the only safe answer: marking the invoice paid here
                     | would book revenue that never arrived.
                     */
                    Log::warning('billing.auto_charge_unavailable', [
                        'provider' => $method->provider,
                        'reason'   => 'no merchant-initiated API wired for this provider',
                    ]);

                    return 'declined';
                }

                $this->mockPayment->execute($invoice);

                activity('billing')
                    ->performedOn($invoice)
                    ->withProperties([
                        'saved_method_id' => $method->id,
                        'provider'        => $method->provider,
                        // Never the token — only the last4, which is what a
                        // human needs to recognise the card.
                        'last4'           => $method->last4,
                    ])
                    ->log('invoice.auto_charged');

                return 'charged';
            },
        );
    }

    private function isPayable(Invoice $invoice): bool
    {
        if ($invoice->paid_at !== null || $invoice->status === InvoiceStatus::Paid) {
            return false;
        }

        if (! in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::Overdue], true)) {
            return false;
        }

        return $invoice->total->isPositive();
    }
}
