<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Http\Controllers\Controller;
use App\Models\InvoiceInstallment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoiceInstallmentController extends Controller
{
    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $customer = $request->user()->customer;

        abort_if($invoice->customer_id !== $customer?->id, 403);
        abort_if(! in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::Overdue], true), 422);
        abort_if(InvoiceInstallment::where('invoice_id', $invoice->id)->exists(), 422, 'Splátkový plán již existuje.');

        $validated = $request->validate([
            'installment_count' => ['required', 'integer', 'min:2', 'max:12'],
        ]);

        $count       = (int) $validated['installment_count'];
        $totalMinor  = $invoice->total->getMinorAmount()->toInt();
        $baseAmount  = (int) floor($totalMinor / $count);
        $remainder   = $totalMinor - ($baseAmount * $count);

        $records = [];
        for ($i = 1; $i <= $count; $i++) {
            $amount = $baseAmount + ($i === $count ? $remainder : 0);
            InvoiceInstallment::create([
                'invoice_id'          => $invoice->id,
                'customer_id'         => $customer->id,
                'total_count'         => $count,
                'installment_number'  => $i,
                'amount_minor'        => $amount,
                'due_date'            => now()->addMonths($i - 1)->toDateString(),
                'status'              => 'pending',
            ]);
        }

        return back()->with('status', "Splátkový plán na {$count} splátek vytvořen.");
    }
}
