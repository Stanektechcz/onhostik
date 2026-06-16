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
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $admin = $request->user();
        abort_if($admin === null, 403);

        try {
            $refundPayment->execute($payment, $admin, $validated['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('status', __('panel.admin.payment_refunded'));
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
