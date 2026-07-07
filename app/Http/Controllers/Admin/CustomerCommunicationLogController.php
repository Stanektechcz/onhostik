<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerCommunicationLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerCommunicationLogController extends Controller
{
    public function index(Request $request): View
    {
        $customerId = $request->query('customer_id');
        $logs = CustomerCommunicationLog::when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.customer-communication-logs.index', compact('logs', 'customerId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer',
            'channel'     => 'required|in:email,phone,chat,note',
            'direction'   => 'required|in:inbound,outbound',
            'subject'     => 'nullable|string|max:255',
            'body'        => 'required|string',
        ]);

        $validated['admin_user_id'] = $request->user()->id;
        $validated['created_by']    = $request->user()->id;

        CustomerCommunicationLog::create($validated);

        return back()->with('status', 'Záznam komunikace uložen.');
    }
}
