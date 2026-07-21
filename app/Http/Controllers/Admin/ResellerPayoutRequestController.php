<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResellerPayoutRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResellerPayoutRequestController extends Controller
{
    /**
     * Which transitions are allowed from each state (audit K145).
     *
     * The previous version let any status become any other, so a payout marked
     * `paid` could be flipped back to `approved` and paid a SECOND time — real
     * money, no guard. `paid` and `rejected` are terminal here: reversing a
     * completed payout is a new financial event, not an edit, and must not be
     * a dropdown away.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'pending'  => ['approved', 'rejected'],
        'approved' => ['paid', 'rejected'],
        'rejected' => [],
        'paid'     => [],
    ];

    public function index(Request $request): View
    {
        $status   = $request->query('status');
        $requests = ResellerPayoutRequest::when($status, fn ($q) => $q->where('status', $status))
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

        $from = (string) $resellerPayoutRequest->status;
        $to   = $validated['status'];

        $allowed = self::TRANSITIONS[$from] ?? []; // @phpstan-ignore-line — $from is DB-sourced, may be an unknown status

        if (! in_array($to, $allowed, true)) {
            // 422, not a silent no-op: the operator asked for something the
            // money-state machine forbids, and they need to know why.
            throw ValidationException::withMessages([
                'status' => "Nelze změnit stav „{$from}“ → „{$to}“.",
            ]);
        }

        /*
         | Four-eyes (audit 74): paying out real money can be gated so a SECOND
         | admin must approve. When configured (and above any threshold), stage
         | the "paid" transition as an approval request instead of executing it
         | now. Default config is off, so without opting in this is a no-op and
         | the payout flips straight to paid as before.
         */
        $approvals = app(\App\Domains\Approvals\Services\ApprovalService::class);

        if ($to === 'paid'
            && $approvals->required('reseller_payout_paid', ['amount_minor' => (int) $resellerPayoutRequest->amount])) {
            $approvals->request(
                'reseller_payout_paid',
                ['amount_minor' => (int) $resellerPayoutRequest->amount, 'currency' => $resellerPayoutRequest->currency],
                $request->user(),
                $resellerPayoutRequest,
            );

            return back()->with('status', 'Výplata byla odeslána ke schválení druhým administrátorem.');
        }

        $resellerPayoutRequest->update([
            'status'       => $to,
            'note'         => $validated['note'] ?? $resellerPayoutRequest->note,
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        // Money moved — leave a trail. Who, from what, to what, and how much.
        activity()
            ->performedOn($resellerPayoutRequest)
            ->causedBy($request->user())
            ->withProperties([
                'from'          => $from,
                'to'            => $to,
                'amount_minor'  => $resellerPayoutRequest->amount,
                'currency'      => $resellerPayoutRequest->currency,
            ])
            ->log('reseller_payout.status_changed');

        return back()->with('status', 'Žádost o výplatu aktualizována.');
    }
}
