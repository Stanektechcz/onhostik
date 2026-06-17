<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Actions\IssueTaxDocumentAction;
use App\Domains\Billing\Actions\ProcessMockPaymentAction;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Exceptions\IncompleteBillingDetailsException;
use App\Domains\Billing\Models\Invoice;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $search = $request->string('q')->toString();

        return view('admin.invoices', [
            'invoices' => Invoice::query()
                ->with('customer')
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($q) use ($search): void {
                        $q->where('number', 'like', "%{$search}%")
                          ->orWhere('variable_symbol', 'like', "%{$search}%")
                          ->orWhereHas('customer', fn ($c) => $c->where('email', 'like', "%{$search}%")
                              ->orWhere('company_name', 'like', "%{$search}%"));
                    });
                })
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'filter' => $status,
            'search' => $search,
        ]);
    }

    public function show(Invoice $invoice): View
    {
        return view('admin.invoice-show', [
            'invoice' => $invoice->load(['items', 'payments', 'order', 'customer.user', 'customer.addresses', 'parentInvoice', 'renewalService']),
            'taxDocument' => Invoice::query()->where('parent_invoice_id', $invoice->id)->first(),
            'mockMode'    => (bool) config('provisioning.mock_mode', true),
        ]);
    }

    /**
     * Admin "mark paid" — deliberately runs the MOCK payment action so the
     * full idempotent payment path (payment row, InvoicePaid event, order
     * transition, provisioning, tax document) stays intact. Mock mode only.
     */
    public function markPaid(Invoice $invoice, ProcessMockPaymentAction $action): RedirectResponse
    {
        abort_unless((bool) config('provisioning.mock_mode', true), 403, 'Mock payments are disabled.');

        try {
            $action->execute($invoice);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('status', __('panel.admin.invoice_marked_paid'));
    }

    /** Admin cancel — marks an open invoice as cancelled with audit log. */
    public function cancel(Invoice $invoice): RedirectResponse
    {
        if (! $invoice->status->isOpen()) {
            return back()->withErrors(['invoice' => __('panel.admin.cancel_invoice_forbidden')]);
        }

        $invoice->update(['status' => InvoiceStatus::Cancelled]);

        activity('invoice')
            ->performedOn($invoice)
            ->withProperties(['previous_status' => $invoice->getOriginal('status')])
            ->log('invoice.cancelled');

        return back()->with('status', __('panel.admin.invoice_cancelled'));
    }

    /** Manual tax-document issuance after the customer completes billing details. */
    public function issueTaxDocument(Invoice $invoice, IssueTaxDocumentAction $action): RedirectResponse
    {
        try {
            $taxDocument = $action->execute($invoice);
        } catch (IncompleteBillingDetailsException|InvalidArgumentException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.invoices.show', $taxDocument)
            ->with('status', __('panel.admin.tax_document_issued'));
    }
}
