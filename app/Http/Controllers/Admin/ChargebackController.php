<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chargeback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChargebackController extends Controller
{
    public function index(Request $request): View
    {
        $statuses = ['received', 'under_review', 'won', 'lost', 'refunded'];

        $chargebacks = Chargeback::with(['customer'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('received_at')
            ->paginate(20);

        return view('admin.chargebacks.index', compact('chargebacks', 'statuses'));
    }

    public function update(Request $request, Chargeback $chargeback): RedirectResponse
    {
        $validated = $request->validate([
            'status'     => 'required|in:received,under_review,won,lost,refunded',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $chargeback->update([
            ...$validated,
            'handled_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Chargeback aktualizován.');
    }
}
