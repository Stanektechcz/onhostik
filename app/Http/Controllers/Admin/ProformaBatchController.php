<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Models\Invoice;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProformaBatchController extends Controller
{
    public function index(): View
    {
        $proformas = Invoice::with('customer')
            ->where('type', InvoiceType::Proforma->value)
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value])
            ->orderBy('due_date')
            ->paginate(25);

        return view('admin.proforma-batch', compact('proformas'));
    }

    public function convert(Invoice $invoice): RedirectResponse
    {
        abort_if($invoice->type !== InvoiceType::Proforma, 422, 'Faktura není proforma.');
        abort_if(! in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::Overdue], true), 422, 'Proforma není otevřená.');

        $invoice->update(['type' => InvoiceType::Invoice->value]);

        return back()->with('status', 'Proforma č. ' . $invoice->number . ' převedena na daňový doklad.');
    }
}
