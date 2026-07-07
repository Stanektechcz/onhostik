<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\CustomerCommunicationLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CustomerCommunicationLogController extends Controller
{
    public function index(Request $request): View
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId !== null, 403);

        $logs = CustomerCommunicationLog::where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('panel.customer-communication-logs.index', compact('logs'));
    }
}
