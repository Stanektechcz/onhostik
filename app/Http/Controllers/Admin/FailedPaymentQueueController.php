<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Http\Controllers\Controller;
use App\Notifications\RenewalPaymentFailedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class FailedPaymentQueueController extends Controller
{
    public function index(): View
    {
        $invoices = Invoice::with('customer', 'renewalService')
            ->whereNotNull('renewal_failure_notified_at')
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->whereNotNull('renewal_service_id')
            ->orderByDesc('renewal_failure_notified_at')
            ->paginate(25);

        return view('admin.failed-payment-queue', compact('invoices'));
    }

    public function resend(Invoice $invoice): RedirectResponse
    {
        $user    = $invoice->customer?->user;
        $service = $invoice->renewalService;

        if ($user === null || $service === null) {
            return back()->withErrors(['error' => 'Zákazník nebo služba nenalezena.']);
        }

        $user->notify(new RenewalPaymentFailedNotification($invoice, $service));

        $invoice->update(['renewal_failure_notified_at' => now()]);

        return back()->with('status', 'Upomínka odeslána zákazníkovi.');
    }
}
