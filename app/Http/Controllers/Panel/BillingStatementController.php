<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BillingStatementController extends Controller
{
    public function index(Request $request): View
    {
        return view('panel.billing.statement-form');
    }

    public function download(Request $request, int $year, int $month): Response|\Illuminate\Http\RedirectResponse
    {
        if ($year > now()->year || ($year === now()->year && $month > now()->month)) {
            return back()->withErrors(['period' => 'Nelze generovat výkaz pro budoucí období.']);
        }

        if ($month < 1 || $month > 12) {
            return back()->withErrors(['period' => 'Neplatný měsíc.']);
        }

        /** @var \App\Models\User $user */
        $user     = $request->user();
        $customer = $user->customer;

        if ($customer === null) {
            return back()->withErrors(['error' => 'Zákaznický profil nenalezen.']);
        }

        $invoices = Invoice::where('customer_id', $customer->id)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('created_at')
            ->get();

        $paidTotal    = $invoices->where('status', InvoiceStatus::Paid)->sum(fn (Invoice $i) => $i->total->getMinorAmount()->toInt());
        $pendingTotal = $invoices->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])->sum(fn (Invoice $i) => $i->total->getMinorAmount()->toInt());

        $pdf = Pdf::loadView('pdf.billing-statement', [
            'customer'    => $customer,
            'invoices'    => $invoices,
            'year'        => $year,
            'month'       => $month,
            'paidTotal'   => $paidTotal,
            'pendingTotal' => $pendingTotal,
        ]);

        $filename = "vykaz-{$year}-{$month}-{$customer->id}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
