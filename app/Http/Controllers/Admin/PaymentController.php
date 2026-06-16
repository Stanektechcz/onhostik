<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentWebhookLog;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
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
