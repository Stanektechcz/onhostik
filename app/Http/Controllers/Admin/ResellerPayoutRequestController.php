<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResellerPayoutRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerPayoutRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status   = $request->query('status');
        $requests = ResellerPayoutRequest::when($status, fn($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(15);
        return view('admin.reseller-payout-requests.index', compact('requests', 'status'));
    }

    public function update(Request $request, ResellerPayoutRequest $resellerPayoutRequest): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected,paid',
            'note'   => 'nullable|string|max:500',
        ]);
        $resellerPayoutRequest->update([
            'status'       => $validated['status'],
            'note'         => $validated['note'] ?? $resellerPayoutRequest->note,
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);
        return back()->with('status', 'Žádost o výplatu aktualizována.');
    }
}
