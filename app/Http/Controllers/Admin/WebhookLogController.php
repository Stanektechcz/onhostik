<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Models\PaymentWebhookLog;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class WebhookLogController extends Controller
{
    public function index(Request $request): View
    {
        $provider  = $request->string('provider')->toString();
        $processed = $request->string('processed')->toString();
        $dateFrom  = $request->string('date_from')->toString();
        $dateTo    = $request->string('date_to')->toString();
        $hasError  = $request->boolean('has_error');

        $logs = PaymentWebhookLog::query()
            ->when($provider !== '', fn ($q) => $q->where('provider', $provider))
            ->when($processed !== '', fn ($q) => $q->where('processed', $processed === '1'))
            ->when($dateFrom !== '', fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($hasError, fn ($q) => $q->whereNotNull('error_message'))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $counts = PaymentWebhookLog::query()
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN processed = 1 THEN 1 ELSE 0 END) as processed,
                SUM(CASE WHEN error_message IS NOT NULL THEN 1 ELSE 0 END) as errors,
                SUM(CASE WHEN signature_valid = 0 OR signature_valid IS NULL THEN 1 ELSE 0 END) as invalid_sig
            ")
            ->first();

        return view('admin.webhook-logs', [
            'logs'       => $logs,
            'counts'     => $counts,
            'provider'   => $provider,
            'processed'  => $processed,
            'dateFrom'   => $dateFrom,
            'dateTo'     => $dateTo,
            'hasError'   => $hasError,
        ]);
    }
}
