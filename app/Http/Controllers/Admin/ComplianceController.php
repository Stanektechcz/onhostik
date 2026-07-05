<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Compliance\Enums\GdprRequestStatus;
use App\Domains\Compliance\Models\GdprRequest;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ComplianceController extends Controller
{
    public function index(): View
    {
        $requests = GdprRequest::query()
            ->with('customer.user')
            ->latest()
            ->paginate(30);

        $pendingCount = GdprRequest::where('status', GdprRequestStatus::Pending->value)->count();

        return view('admin.compliance.index', compact('requests', 'pendingCount'));
    }

    public function approve(Request $request, GdprRequest $gdprRequest): RedirectResponse
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $gdprRequest->update([
            'status'       => GdprRequestStatus::Completed,
            'admin_note'   => $validated['admin_note'] ?? null,
            'completed_at' => now(),
        ]);

        return back()->with('status', 'Žádost schválena.');
    }

    public function reject(Request $request, GdprRequest $gdprRequest): RedirectResponse
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $gdprRequest->update([
            'status'     => GdprRequestStatus::Rejected,
            'admin_note' => $validated['admin_note'] ?? null,
        ]);

        return back()->with('status', 'Žádost zamítnuta.');
    }
}
