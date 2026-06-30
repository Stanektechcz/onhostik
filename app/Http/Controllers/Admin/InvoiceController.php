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
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $search = $request->string('q')->toString();

        $counts = Invoice::query()->selectRaw('status, COUNT(*) as cnt')->groupBy('status')->pluck('cnt', 'status');

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
            'filter'        => $status,
            'search'        => $search,
            'countSent'     => (int) ($counts[InvoiceStatus::Sent->value] ?? 0),
            'countOverdue'  => (int) ($counts[InvoiceStatus::Overdue->value] ?? 0),
            'countPaid'     => (int) ($counts[InvoiceStatus::Paid->value] ?? 0),
            'countDraft'    => (int) ($counts[InvoiceStatus::Draft->value] ?? 0),
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

    /** Stream invoices as CSV for accounting/export. */
    public function export(Request $request): StreamedResponse
    {
        $status    = $request->string('status')->toString();
        $dateFrom  = $request->string('from')->toString();
        $dateTo    = $request->string('to')->toString();

        $query = Invoice::query()
            ->with('customer')
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($dateFrom !== '', fn ($q) => $q->whereDate('issue_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($q) => $q->whereDate('issue_date', '<=', $dateTo))
            ->orderBy('id');

        $filename = 'faktury-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

            fputcsv($out, [
                'Číslo faktury', 'Typ', 'Status', 'Datum vystavení', 'Splatnost',
                'Datum platby', 'Zákazník', 'E-mail', 'IČO', 'DIČ',
                'Základ', 'DPH', 'Celkem', 'Měna',
            ], ';');

            $query->chunk(200, function ($invoices) use ($out): void {
                foreach ($invoices as $invoice) {
                    $currency = $invoice->total->getCurrency()->getCurrencyCode();
                    $minor    = 100;
                    fputcsv($out, [
                        $invoice->number,
                        $invoice->type->label(),
                        $invoice->status->label(),
                        $invoice->issue_date?->format('d.m.Y') ?? '',
                        $invoice->due_date?->format('d.m.Y') ?? '',
                        $invoice->paid_at?->format('d.m.Y') ?? '',
                        $invoice->customer->company_name ?: ($invoice->snapshot_name ?: ''),
                        $invoice->customer->email,
                        $invoice->customer->registration_number ?: '',
                        $invoice->customer->vat_number ?: '',
                        number_format($invoice->subtotal->getMinorAmount()->toInt() / $minor, 2, ',', ''),
                        number_format($invoice->tax_amount->getMinorAmount()->toInt() / $minor, 2, ',', ''),
                        number_format($invoice->total->getMinorAmount()->toInt() / $minor, 2, ',', ''),
                        $currency,
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
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
