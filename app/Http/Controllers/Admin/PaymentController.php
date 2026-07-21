<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Actions\RefundPaymentAction;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentWebhookLog;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    /**
     * Records a MANUAL refund. No gateway in this system can be called to
     * actually move money back — see RefundPaymentAction docblock. The
     * admin must still complete the real refund in the Comgate portal.
     */
    public function refund(Request $request, Payment $payment, RefundPaymentAction $refundPayment): RedirectResponse
    {
        if ($payment->status !== PaymentStatus::Completed) {
            return back()->withErrors(['payment' => __('panel.admin.refund_not_completed')]);
        }

        $validated = $request->validate([
            'reason'      => ['required', 'string', 'min:3', 'max:500'],
            'destination' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Domains\Billing\Enums\RefundDestination::class)],
        ]);

        $admin = $request->user();
        abort_if($admin === null, 403);

        $destination = \App\Domains\Billing\Enums\RefundDestination::tryFrom(
            is_string($validated['destination'] ?? null) ? $validated['destination'] : '',
        ) ?? \App\Domains\Billing\Enums\RefundDestination::OriginalMethod;

        try {
            $refundPayment->execute($payment, $admin, $validated['reason'], $destination);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        // Returning money to the card is not something we can do for the
        // operator — say so plainly instead of implying it is done.
        return back()->with('status', $destination->requiresManualAction()
            ? 'Refundace zaznamenána. Vrácení peněz proveďte v administraci platební brány.'
            : 'Refundace zaznamenána a částka byla připsána na kredit zákazníka.');
    }

    /** Stream payments as CSV for accounting/export. */
    public function export(Request $request): StreamedResponse
    {
        $status   = $request->string('status')->toString();
        $dateFrom = $request->string('date_from')->toString();
        $dateTo   = $request->string('date_to')->toString();
        $search   = $request->string('q')->toString();

        $query = Payment::query()
            ->with(['customer', 'invoice'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($dateFrom !== '', fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->whereHas('customer', fn ($c) => $c->where('email', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%"))
                      ->orWhereHas('invoice', fn ($i) => $i->where('number', 'like', "%{$search}%"));
                });
            })
            ->orderBy('id');

        $filename = 'platby-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fprintf($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'ID', 'Status', 'Metoda', 'Zákazník', 'E-mail', 'IČO',
                'Faktura', 'Celkem', 'Měna', 'Comgate ID', 'Datum',
            ], ';');

            $query->chunk(200, function ($payments) use ($out): void {
                foreach ($payments as $payment) {
                    $currency = $payment->amount->getCurrency()->getCurrencyCode();
                    fputcsv($out, [
                        $payment->id,
                        $payment->status->label(),
                        $payment->method->label(),
                        $payment->customer?->company_name ?: ($payment->customer->email ?: ''),
                        $payment->customer->email ?: '',
                        $payment->customer?->registration_number ?: '',
                        $payment->invoice->number ?: '',
                        number_format($payment->amount->getMinorAmount()->toInt() / 100, 2, ',', ''),
                        $currency,
                        $payment->gateway_transaction_id ?? '',
                        ($payment->processed_at ?? $payment->created_at)?->format('d.m.Y H:i') ?? '',
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function index(Request $request): View
    {
        $status   = $request->string('status')->toString();
        $dateFrom = $request->string('date_from')->toString();
        $dateTo   = $request->string('date_to')->toString();
        $search   = $request->string('q')->toString();

        return view('admin.payments', [
            'payments' => Payment::query()
                ->with(['customer', 'invoice'])
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->when($dateFrom !== '', fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
                ->when($dateTo !== '', fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
                ->when($search !== '', function ($q) use ($search): void {
                    $q->where(function ($inner) use ($search): void {
                        $inner->whereHas('customer', fn ($c) => $c->where('email', 'like', "%{$search}%")
                            ->orWhere('company_name', 'like', "%{$search}%"))
                          ->orWhereHas('invoice', fn ($i) => $i->where('number', 'like', "%{$search}%"));
                    });
                })
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'webhookLogs' => PaymentWebhookLog::query()
                ->latest('id')
                ->paginate(15, ['*'], 'webhooks'),
            'statusFilter' => $status,
            'dateFrom'     => $dateFrom,
            'dateTo'       => $dateTo,
            'search'       => $search,
        ]);
    }
}
