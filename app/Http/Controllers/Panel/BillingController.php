<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Billing\Actions\PayInvoiceWithCreditAction;
use App\Domains\Billing\Actions\ProcessMockPaymentAction;
use App\Domains\Billing\Exceptions\InsufficientCreditException;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class BillingController extends Controller
{
    public function invoices(Request $request): View
    {
        $customer = $this->customer($request);

        return view('panel.billing.invoices', [
            'invoices' => $customer->invoices()->latest('id')->paginate(15),
        ]);
    }

    public function invoiceShow(Request $request, Invoice $invoice, CreditLedger $ledger): View
    {
        $this->authorize('view', $invoice);

        return view('panel.billing.invoice-show', [
            'invoice'       => $invoice->load(['items', 'payments', 'order']),
            'creditBalance' => $ledger->getBalance($this->customer($request)),
            'mockMode'      => (bool) config('provisioning.mock_mode', true),
        ]);
    }

    public function payments(Request $request): View
    {
        $customer = $this->customer($request);

        return view('panel.billing.payments', [
            'payments' => $customer->payments()->with('invoice')->latest('id')->paginate(15),
        ]);
    }

    public function credits(Request $request, CreditLedger $ledger): View
    {
        $customer = $this->customer($request);

        return view('panel.billing.credits', [
            'balance' => $ledger->getBalance($customer),
            'history' => $ledger->getHistory($customer),
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

    /** Pays the invoice from the customer's credit balance. */
    public function payCredit(Invoice $invoice, PayInvoiceWithCreditAction $action): RedirectResponse
    {
        $this->authorize('pay', $invoice);

        try {
            $action->execute($invoice);
        } catch (InsufficientCreditException $e) {
            return back()->withErrors(['payment' => __('panel.billing.insufficient_credit')]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('status', __('panel.billing.paid_credit'));
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user()?->customer;

        abort_if($customer === null, 403, 'No customer profile attached to this account.');

        return $customer;
    }
}
