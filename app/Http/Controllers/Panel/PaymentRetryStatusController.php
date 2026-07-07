<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\PaymentRetrySchedule;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentRetryStatusController extends Controller
{
    public function index(Request $request): View
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId !== null, 403);

        $retries = PaymentRetrySchedule::where('customer_id', $customerId)
            ->orderByDesc('retry_at')
            ->paginate(15);

        return view('panel.payment-retry-status.index', compact('retries'));
    }
}
