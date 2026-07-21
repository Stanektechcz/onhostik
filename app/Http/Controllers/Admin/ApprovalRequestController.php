<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Approvals\Exceptions\ApprovalException;
use App\Domains\Approvals\Models\ApprovalRequest;
use App\Domains\Approvals\Services\ApprovalService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Second-admin review queue for four-eyes approvals (audit 74).
 */
final class ApprovalRequestController extends Controller
{
    public function __construct(private readonly ApprovalService $approvals) {}

    public function index(Request $request): View
    {
        $status   = $request->query('status', ApprovalRequest::STATUS_PENDING);
        $requests = ApprovalRequest::with(['requester', 'reviewer', 'subject'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.approvals.index', compact('requests', 'status'));
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest): RedirectResponse
    {
        try {
            $this->approvals->approve($approvalRequest, $request->user());
        } catch (ApprovalException $e) {
            // Self-approval, a stale request, or a failed execution — surface
            // the reason rather than a generic error.
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('status', 'Žádost byla schválena a provedena.');
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest): RedirectResponse
    {
        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->approvals->reject($approvalRequest, $request->user(), $validated['review_note'] ?? null);
        } catch (ApprovalException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('status', 'Žádost byla zamítnuta.');
    }
}
