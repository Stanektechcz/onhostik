<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\SnapshotRestoreRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SnapshotRestoreRequestController extends Controller
{
    public function index(Request $request): View
    {
        $requests = SnapshotRestoreRequest::where('user_id', $request->user()->id)
            ->with(['service'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('panel.snapshot-restore-requests.index', compact('requests'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_id'    => 'required|integer',
            'snapshot_id'   => 'required|string|max:100',
            'restore_point' => 'required|string|max:255',
            'customer_note' => 'nullable|string|max:1000',
        ]);

        SnapshotRestoreRequest::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'status'  => 'pending',
        ]);

        return back()->with('status', 'Žádost o obnovení odeslána.');
    }
}
