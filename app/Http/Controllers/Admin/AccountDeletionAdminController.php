<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountDeletionAdminController extends Controller
{
    public function index(): View
    {
        $requests = AccountDeletionRequest::with('user')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.account-deletion.index', compact('requests'));
    }

    public function approve(Request $request, AccountDeletionRequest $deletion): RedirectResponse
    {
        abort_if($deletion->status !== 'pending', 422);

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $deletion->update([
            'status'                => 'approved',
            'reviewed_by'           => $request->user()?->id,
            'reviewed_at'           => now(),
            'admin_note'            => $validated['admin_note'] ?? null,
            'scheduled_deletion_at' => now()->addDays(30),
        ]);

        return back()->with('status', 'Žádost schválena. Účet bude smazán za 30 dní.');
    }

    public function reject(Request $request, AccountDeletionRequest $deletion): RedirectResponse
    {
        abort_if($deletion->status !== 'pending', 422);

        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'max:1000'],
        ]);

        $deletion->update([
            'status'      => 'rejected',
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'admin_note'  => $validated['admin_note'],
        ]);

        return back()->with('status', 'Žádost zamítnuta.');
    }
}
