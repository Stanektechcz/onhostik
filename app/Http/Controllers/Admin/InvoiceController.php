<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Actions\IssueCreditNoteAction;
use App\Domains\Billing\Actions\IssueTaxDocumentAction;
use App\Domains\Billing\Actions\ProcessMockPaymentAction;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Exceptions\IncompleteBillingDetailsException;
use App\Domains\Billing\Models\Invoice;
use App\Http\Controllers\Controller;
use App\Notifications\InvoiceIssuedNotification;
use App\Notifications\PaymentOverdueNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $status   = $request->string('status')->toString();
        $search   = $request->string('q')->toString();
        $dateFrom = $request->string('from')->toString();
        $dateTo   = $request->string('to')->toString();

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
                ->when($dateFrom !== '', fn ($q) => $q->whereDate('issue_date', '>=', $dateFrom))
                ->when($dateTo !== '', fn ($q) => $q->whereDate('issue_date', '<=', $dateTo))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'filter'        => $status,
            'search'        => $search,
            'dateFrom'      => $dateFrom,
            'dateTo'        => $dateTo,
            'countSent'     => (int) ($counts[InvoiceStatus::Sent->value] ?? 0),
            'countOverdue'  => (int) ($counts[InvoiceStatus::Overdue->value] ?? 0),
            'countPaid'     => (int) ($counts[InvoiceStatus::Paid->value] ?? 0),
            'countDraft'    => (int) ($counts[InvoiceStatus::Draft->value] ?? 0),
        ]);
    }

    public function downloadPdf(Invoice $invoice): Response
    {
        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice->load('items')]);

        return $pdf->download($invoice->number . '.pdf');
    }

    public function resendEmail(Invoice $invoice): RedirectResponse
    {
        $user = $invoice->customer?->user;

        if ($user === null) {
            return back()->withErrors(['invoice' => __('panel.admin.resend_no_user')]);
        }

        $user->notify(new InvoiceIssuedNotification($invoice->load('items')));

        return back()->with('status', __('panel.admin.resend_email_sent'));
    }

    public function sendPaymentReminder(Invoice $invoice, Request $request): RedirectResponse
    {
        if ($invoice->status !== InvoiceStatus::Overdue) {
            return back()->withErrors(['invoice' => __('panel.admin.payment_reminder_not_overdue')]);
        }

        $user = $invoice->customer?->user;
        if ($user === null) {
            return back()->withErrors(['invoice' => __('panel.admin.resend_no_user')]);
        }

        $daysOverdue = $invoice->due_date ? (int) $invoice->due_date->diffInDays(now()) : 0;

        $user->notify(new PaymentOverdueNotification($invoice->load('items'), $daysOverdue));

        $admin = $request->user();
        activity('invoice')
            ->performedOn($invoice)
            ->causedBy($admin)
            ->withProperties(['days_overdue' => $daysOverdue])
            ->log('invoice.payment_reminder_sent');

        return back()->with('status', __('panel.admin.payment_reminder_sent'));
    }

    public function sendBulkPaymentReminders(Request $request): RedirectResponse
    {
        $sent   = 0;
        $skipped = 0;

        Invoice::query()
            ->where('status', InvoiceStatus::Overdue)
            ->with(['customer.user', 'items'])
            ->chunk(50, function ($invoices) use (&$sent, &$skipped): void {
                foreach ($invoices as $invoice) {
                    $user = $invoice->customer?->user;
                    if ($user === null) {
                        $skipped++;
                        continue;
                    }

                    $daysOverdue = $invoice->due_date ? (int) $invoice->due_date->diffInDays(now()) : 0;
                    $user->notify(new PaymentOverdueNotification($invoice, $daysOverdue));
                    $sent++;
                }
            });

        activity('invoice')
            ->causedBy($request->user())
            ->withProperties(['sent' => $sent, 'skipped' => $skipped])
            ->log('invoice.bulk_payment_reminders_sent');

        return back()->with('status', __('panel.admin.bulk_reminder_sent', ['count' => $sent, 'skipped' => $skipped]));
    }

    public function show(Invoice $invoice): View
    {
        $childInvoices = Invoice::query()
            ->where('parent_invoice_id', $invoice->id)
            ->where('status', '!=', \App\Domains\Billing\Enums\InvoiceStatus::Cancelled->value)
            ->get();

        return view('admin.invoice-show', [
            'invoice'    => $invoice->load(['items', 'payments', 'order', 'customer.user', 'customer.addresses', 'parentInvoice', 'renewalService']),
            'taxDocument'=> $childInvoices->first(fn ($i) => $i->type === \App\Domains\Billing\Enums\InvoiceType::Invoice),
            'creditNote' => $childInvoices->first(fn ($i) => $i->type === \App\Domains\Billing\Enums\InvoiceType::CreditNote),
            'mockMode'   => (bool) config('provisioning.mock_mode', true),
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

    /** Batch mark multiple invoices as paid (admin shortcut for offline payments). */
    public function batchMarkPaid(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'exists:invoices,id'],
        ]);

        $marked = 0;
        Invoice::whereIn('id', $validated['ids'])
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value])
            ->each(function (Invoice $invoice) use ($request, &$marked): void {
                $invoice->update([
                    'status'  => InvoiceStatus::Paid,
                    'paid_at' => now(),
                ]);

                activity('billing')
                    ->performedOn($invoice)
                    ->causedBy($request->user())
                    ->withProperties(['batch' => true, 'previous_status' => InvoiceStatus::Sent->value])
                    ->log('invoice.batch_marked_paid');

                $marked++;
            });

        return back()->with('status', "Označeno jako zaplacené: {$marked} faktur.");
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

    public function issueCreditNote(Invoice $invoice, IssueCreditNoteAction $action, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $creditNote = $action->execute($invoice, $validated['reason'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.invoices.show', $creditNote)
            ->with('status', 'Dobropis ' . $creditNote->number . ' byl vystaven a kredit připsán zákazníkovi.');
    }
}
