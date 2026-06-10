<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Billing\Models\Invoice;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class BillingController extends Controller
{
    public function invoices(): View
    {
        return view('panel.billing.invoices');
    }

    public function invoiceShow(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        return view('panel.billing.invoice-show', ['invoice' => $invoice]);
    }

    public function payments(): View
    {
        return view('panel.billing.payments');
    }

    public function credits(): View
    {
        return view('panel.billing.credits');
    }
}
