<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvoiceDispute;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoiceDisputeController extends Controller
{
    public function index(): View
    {
        $disputes = InvoiceDispute::with('invoice', 'customer')
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.invoice-disputes.index', compact('disputes'));
    }

    public function resolve(Request $request, InvoiceDispute $dispute): RedirectResponse
    {
        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'max:1000'],
            'status'     => ['required', 'in:resolved,rejected'],
        ]);

        $dispute->update([
            'status'       => $validated['status'],
            'admin_note'   => $validated['admin_note'],
            'resolved_by'  => $request->user()?->id,
            'resolved_at'  => now(),
        ]);

        return back()->with('status', 'Námitka uzavřena.');
    }
}
