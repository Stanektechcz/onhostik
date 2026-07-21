<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SnapshotRestoreRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin approval flow for customer snapshot-restore requests (audit E76).
 *
 * Customers could raise a request but nothing on the admin side could act on
 * it, so requests piled up unanswered. Restoring a snapshot overwrites live
 * data, which is why it is a request-and-approve flow rather than a button
 * the customer can press.
 */
class SnapshotRestoreRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        $query = SnapshotRestoreRequest::query()->with(['service.customer'])->latest('id');

        if (in_array($status, ['pending', 'approved', 'rejected', 'completed'], true)) {
            $query->where('status', $status);
        }

        return view('admin.snapshot-restore-requests', [
            'requests'     => $query->paginate(25)->withQueryString(),
            'status'       => $status,
            'pendingCount' => SnapshotRestoreRequest::where('status', 'pending')->count(),
        ]);
    }

    public function approve(Request $request, SnapshotRestoreRequest $snapshotRestoreRequest): RedirectResponse
    {
        if ($snapshotRestoreRequest->status !== 'pending') {
            return back()->withErrors(['request' => 'Vyřídit lze pouze čekající požadavek.']);
        }

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $snapshotRestoreRequest->update([
            'status'     => 'approved',
            'admin_note' => $validated['admin_note'] ?? null,
            'handled_by' => $request->user()?->id,
        ]);

        activity('provisioning')
            ->performedOn($snapshotRestoreRequest->service)
            ->causedBy($request->user())
            ->withProperties(['request_id' => $snapshotRestoreRequest->id, 'decision' => 'approved'])
            ->log('snapshot_restore.approved');

        return back()->with('status', 'Požadavek na obnovu byl schválen.');
    }

    public function reject(Request $request, SnapshotRestoreRequest $snapshotRestoreRequest): RedirectResponse
    {
        if ($snapshotRestoreRequest->status !== 'pending') {
            return back()->withErrors(['request' => 'Vyřídit lze pouze čekající požadavek.']);
        }

        $validated = $request->validate([
            // A rejection without a reason is useless to the customer.
            'admin_note' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $snapshotRestoreRequest->update([
            'status'     => 'rejected',
            'admin_note' => $validated['admin_note'],
            'handled_by' => $request->user()?->id,
        ]);

        activity('provisioning')
            ->performedOn($snapshotRestoreRequest->service)
            ->causedBy($request->user())
            ->withProperties(['request_id' => $snapshotRestoreRequest->id, 'decision' => 'rejected'])
            ->log('snapshot_restore.rejected');

        return back()->with('status', 'Požadavek na obnovu byl zamítnut.');
    }
}
