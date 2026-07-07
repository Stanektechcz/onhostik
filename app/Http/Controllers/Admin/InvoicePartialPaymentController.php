<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Models\Invoice;
use App\Http\Controllers\Controller;
use App\Models\InvoicePartialPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoicePartialPaymentController extends Controller
{
    public function index(Invoice $invoice): View
    {
        $payments = InvoicePartialPayment::where('invoice_id', $invoice->id)
            ->orderByDesc('paid_at')
            ->get();

        $totalPaidHaler = $payments->sum('amount_haler');
        $invoiceTotalHaler = (int) \Illuminate\Support\Facades\DB::table('invoices')
            ->where('id', $invoice->id)
            ->value('total');

        return view('admin.invoice-partial-payments.index', compact(
            'invoice', 'payments', 'totalPaidHaler', 'invoiceTotalHaler'
        ));
    }

    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'amount_haler'   => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'max:50'],
            'note'           => ['nullable', 'string', 'max:500'],
            'paid_at'        => ['required', 'date'],
        ]);

        InvoicePartialPayment::create(array_merge($validated, [
            'invoice_id'    => $invoice->id,
            'admin_user_id' => $request->user()?->id,
        ]));

        return back()->with('status', 'Částečná platba zaznamenána.');
    }
}
