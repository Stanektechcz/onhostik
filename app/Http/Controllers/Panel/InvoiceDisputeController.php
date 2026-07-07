<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Billing\Models\Invoice;
use App\Http\Controllers\Controller;
use App\Models\InvoiceDispute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceDisputeController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user()->customer;
        abort_if($customer === null, 403);

        $disputes = InvoiceDispute::with('invoice')
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('panel.invoices.disputes', compact('disputes'));
    }

    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user     = $request->user();
        $customer = $user->customer;

        if ($customer === null || $invoice->customer_id !== $customer->id) {
            abort(403);
        }

        $alreadyOpen = InvoiceDispute::where('invoice_id', $invoice->id)
            ->whereIn('status', ['open', 'under_review'])
            ->exists();

        if ($alreadyOpen) {
            return back()->withErrors(['dispute' => 'K této faktuře již existuje otevřená námitka.']);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        InvoiceDispute::create([
            'invoice_id'  => $invoice->id,
            'customer_id' => $customer->id,
            'reason'      => $validated['reason'],
            'status'      => 'open',
        ]);

        activity('invoice')
            ->performedOn($invoice)
            ->causedBy($user)
            ->withProperties(['reason' => $validated['reason']])
            ->log('invoice.dispute_filed');

        return back()->with('status', 'Vaše námitka byla zaznamenána. Budeme vás kontaktovat do 3 pracovních dnů.');
    }
}
