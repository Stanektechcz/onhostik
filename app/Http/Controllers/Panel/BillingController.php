<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Billing\Actions\CreateCreditTopUpInvoiceAction;
use App\Domains\Billing\Actions\PayInvoiceWithCreditAction;
use App\Domains\Billing\Actions\ProcessMockPaymentAction;
use App\Domains\Billing\Enums\PaymentMethod;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Exceptions\InsufficientCreditException;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Billing\Services\Gateways\ComgateGateway;
use App\Domains\Billing\Services\Gateways\GopayGateway;
use App\Domains\Billing\Services\Gateways\StripeGateway;
use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class BillingController extends Controller
{
    public function invoices(Request $request): View
    {
        $customer = $this->customer($request);

        $iCounts = DB::table('invoices')
            ->where('customer_id', $customer->id)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return view('panel.billing.invoices', [
            'invoices'      => $customer->invoices()->latest('id')->paginate(15),
            'countUnpaid'   => (int) (($iCounts[\App\Domains\Billing\Enums\InvoiceStatus::Sent->value] ?? 0)
                                    + ($iCounts[\App\Domains\Billing\Enums\InvoiceStatus::Overdue->value] ?? 0)),
            'countOverdue'  => (int) ($iCounts[\App\Domains\Billing\Enums\InvoiceStatus::Overdue->value] ?? 0),
            'countPaid'     => (int) ($iCounts[\App\Domains\Billing\Enums\InvoiceStatus::Paid->value] ?? 0),
            'countTotal'    => (int) $iCounts->sum(),
        ]);
    }

    public function invoiceShow(Request $request, Invoice $invoice, CreditLedger $ledger): View
    {
        $this->authorize('view', $invoice);

        $bankSettings = DB::table('settings')
            ->where('group', 'site')
            ->whereIn('name', ['bank_czk', 'bank_eur'])
            ->pluck('payload', 'name')
            ->map(fn ($v) => json_decode($v, true));

        return view('panel.billing.invoice-show', [
            'invoice'          => $invoice->load(['items', 'payments', 'order', 'renewalService']),
            'creditBalance'    => $ledger->getBalance($this->customer($request)),
            'mockMode'         => (bool) config('provisioning.mock_mode', true),
            'stripeConfigured' => StripeGateway::fromConfig()->isConfigured(),
            'gopayConfigured'  => GopayGateway::fromConfig()->isConfigured(),
            'bankCzk'          => $bankSettings['bank_czk'] ?? config('billing.supplier.bank_account_czk'),
            'bankEur'          => $bankSettings['bank_eur'] ?? config('billing.supplier.bank_account_eur'),
        ]);
    }

    public function payments(Request $request): View
    {
        $customer = $this->customer($request);

        $pCounts = DB::table('payments')
            ->where('customer_id', $customer->id)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $paidTotal = (int) DB::table('payments')
            ->where('customer_id', $customer->id)
            ->where('status', PaymentStatus::Completed->value)
            ->sum('amount');

        return view('panel.billing.payments', [
            'payments'      => $customer->payments()->with('invoice')->latest('id')->paginate(15),
            'countPaid'     => (int) ($pCounts[PaymentStatus::Completed->value] ?? 0),
            'countPending'  => (int) ($pCounts[PaymentStatus::Pending->value] ?? 0),
            'countFailed'   => (int) ($pCounts[PaymentStatus::Failed->value] ?? 0),
            'paidTotalMinor' => $paidTotal,
        ]);
    }

    public function credits(Request $request, CreditLedger $ledger): View
    {
        $customer = $this->customer($request);

        /*
         | Deposits that carry an expiry date and haven't been expired yet.
         | Customers were told about this by e-mail but the page itself never
         | showed it, so credit could silently evaporate between two visits.
         */
        $expiring = \App\Domains\Billing\Models\CreditTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('type', \App\Domains\Billing\Enums\CreditTransactionType::Deposit)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->whereDoesntHave('expiryDeductions')
            ->orderBy('expires_at')
            ->get();

        return view('panel.billing.credits', [
            'balance'      => $ledger->getBalance($customer),
            'history'      => $ledger->getHistory($customer),
            'expiring'     => $expiring,
            // Auto top-up lives on its own page; surfacing its state here is
            // what makes it discoverable at all.
            'autoTopup'    => is_array($customer->credit_auto_topup) ? $customer->credit_auto_topup : null,
            'defaultCard'  => \App\Models\SavedPaymentMethod::query()
                ->where('customer_id', $customer->id)
                ->where('is_default', true)
                ->first(),
        ]);
    }

    /**
     * MOCK gateway payment — only available while mock mode is on.
     * `outcome=fail` simulates a declined gateway payment.
     */
    public function payMock(Request $request, Invoice $invoice, ProcessMockPaymentAction $action): RedirectResponse
    {
        $this->authorize('pay', $invoice);

        abort_unless((bool) config('provisioning.mock_mode', true), 403, 'Mock payments are disabled.');

        $simulateFailure = $request->input('outcome') === 'fail';

        try {
            $payment = $action->execute($invoice, simulateFailure: $simulateFailure);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with(
            $payment->isCompleted() ? 'status' : 'payment_failed',
            $payment->isCompleted()
                ? __('panel.billing.paid_mock')
                : __('panel.billing.payment_failed_mock'),
        );
    }

    /** Download invoice as PDF. */
    public function invoicePrint(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice->load('items')]);

        return $pdf->download($invoice->number . '.pdf');
    }

    /** Initiate a Comgate card/bank payment and redirect the customer to the payment page. */
    public function payComgate(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('pay', $invoice);

        $returnUrl = route('panel.billing.invoices.comgate-return', $invoice);

        try {
            $result = ComgateGateway::fromConfig()->createPayment(
                $invoice->loadMissing('customer'),
                $returnUrl,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['payment' => __('panel.billing.comgate_error')]);
        }

        Payment::create([
            'customer_id'            => $invoice->customer_id,
            'invoice_id'             => $invoice->id,
            'method'                 => PaymentMethod::Comgate,
            'status'                 => PaymentStatus::Pending,
            'amount'                 => $invoice->total,
            'gateway_transaction_id' => $result['transId'],
            'gateway_response'       => ['transId' => $result['transId']],
        ]);

        return redirect()->away($result['redirect']);
    }

    /** Handle the browser return from Comgate (paid / cancelled / pending). */
    public function comgateReturn(Invoice $invoice, Request $request): RedirectResponse
    {
        $status = $request->query('status', 'pending');

        $flashKey   = $status === 'cancelled' ? 'payment_failed' : 'status';
        $flashValue = __('panel.billing.' . match ($status) {
            'paid'      => 'comgate_paid',
            'cancelled' => 'comgate_cancelled',
            default     => 'comgate_pending',
        });

        return redirect()
            ->route('panel.billing.invoices.show', $invoice)
            ->with($flashKey, $flashValue);
    }

    /** Initiate a GoPay payment and redirect the customer to GoPay's hosted page. */
    public function payGopay(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('pay', $invoice);

        $returnUrl = route('panel.billing.invoices.gopay-return', $invoice);
        $notifyUrl = route('webhooks.gopay');

        try {
            $result = GopayGateway::fromConfig()->createPayment(
                $invoice->loadMissing('customer'),
                $returnUrl,
                $notifyUrl,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['payment' => __('panel.billing.gopay_error')]);
        }

        Payment::create([
            'customer_id'            => $invoice->customer_id,
            'invoice_id'             => $invoice->id,
            'method'                 => PaymentMethod::GoPay,
            'status'                 => PaymentStatus::Pending,
            'amount'                 => $invoice->total,
            'gateway_transaction_id' => $result['paymentId'],
            'gateway_response'       => ['payment_id' => $result['paymentId']],
        ]);

        return redirect()->away($result['gwUrl']);
    }

    /** Handle the browser return from GoPay (paid / cancelled / pending). */
    public function gopayReturn(Invoice $invoice, Request $request): RedirectResponse
    {
        $status = $request->query('state', 'PAYMENT_METHOD_CHOSEN');

        if ($status === 'CANCELED') {
            return redirect()
                ->route('panel.billing.invoices.show', $invoice)
                ->with('payment_failed', __('panel.billing.gopay_cancelled'));
        }

        return redirect()
            ->route('panel.billing.invoices.show', $invoice)
            ->with('status', __('panel.billing.gopay_processing'));
    }

    /** Initiate a Stripe Checkout Session and redirect the customer to Stripe's hosted page. */
    public function payStripe(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('pay', $invoice);

        $successUrl = route('panel.billing.invoices.stripe-return', $invoice);
        $cancelUrl  = route('panel.billing.invoices.stripe-return', $invoice);

        try {
            $result = StripeGateway::fromConfig()->createCheckoutSession(
                $invoice->loadMissing('customer'),
                $successUrl,
                $cancelUrl,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['payment' => __('panel.billing.stripe_error')]);
        }

        Payment::create([
            'customer_id'            => $invoice->customer_id,
            'invoice_id'             => $invoice->id,
            'method'                 => PaymentMethod::Stripe,
            'status'                 => PaymentStatus::Pending,
            'amount'                 => $invoice->total,
            'gateway_transaction_id' => $result['sessionId'],
            'gateway_response'       => ['session_id' => $result['sessionId']],
        ]);

        return redirect()->away($result['url']);
    }

    /** Handle the browser return from Stripe (success or cancellation). */
    public function stripeReturn(Invoice $invoice, Request $request): RedirectResponse
    {
        $status = $request->query('status', 'pending');

        if ($status === 'cancelled') {
            return redirect()
                ->route('panel.billing.invoices.show', $invoice)
                ->with('payment_failed', __('panel.billing.stripe_cancelled'));
        }

        // For success, the actual payment confirmation comes via webhook.
        // The return URL just tells the user to wait/check back.
        return redirect()
            ->route('panel.billing.invoices.show', $invoice)
            ->with('status', __('panel.billing.stripe_processing'));
    }

    /** Creates a credit top-up proforma and sends the customer to pay it. */
    public function topUp(Request $request, CreateCreditTopUpInvoiceAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
        ]);

        $customer = $this->customer($request);

        try {
            $invoice = $action->execute(
                $customer,
                Money::of($validated['amount'], $customer->preferred_currency->value),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('panel.billing.invoices.show', $invoice)
            ->with('status', __('panel.billing.topup_created'));
    }

    /** Pays the invoice from the customer's credit balance. */
    public function payCredit(Invoice $invoice, PayInvoiceWithCreditAction $action): RedirectResponse
    {
        $this->authorize('pay', $invoice);

        // Paying a top-up FROM credit would be circular — gateway only.
        if ($invoice->purpose === 'credit_topup') {
            return back()->withErrors(['payment' => __('panel.billing.topup_credit_forbidden')]);
        }

        try {
            $action->execute($invoice);
        } catch (InsufficientCreditException $e) {
            return back()->withErrors(['payment' => __('panel.billing.insufficient_credit')]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('status', __('panel.billing.paid_credit'));
    }

    /** Set or update B2B PO number and custom reference on an invoice. */
    public function updateReference(Request $request, Invoice $invoice): RedirectResponse
    {
        $customer = $this->customer($request);
        abort_if($invoice->customer_id !== $customer->id, 403);

        $validated = $request->validate([
            'purchase_order_number' => ['nullable', 'string', 'max:100'],
            'custom_reference'      => ['nullable', 'string', 'max:255'],
        ]);

        $invoice->update([
            'purchase_order_number' => $validated['purchase_order_number'] ?: null,
            'custom_reference'      => $validated['custom_reference'] ?: null,
        ]);

        return back()->with('status', 'Reference faktury byla uložena.');
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user()?->customer;

        abort_if($customer === null, 403, 'No customer profile attached to this account.');

        return $customer;
    }
}
