<?php

declare(strict_types=1);

namespace App\Domains\Approvals\Executors;

use App\Domains\Approvals\Contracts\ApprovalExecutor;
use App\Domains\Approvals\Exceptions\ApprovalException;
use App\Domains\Approvals\Models\ApprovalRequest;
use App\Models\ResellerPayoutRequest;

/**
 * Marks a reseller payout as paid, once a second admin has approved it (audit 74).
 *
 * This is the only place the payout actually flips to `paid` when four-eyes is
 * on. It re-checks the state at execution time: the request may have been paid
 * or rejected through another path between staging and approval, and paying
 * twice is exactly the real-money mistake the control exists to prevent.
 */
final class ExecuteResellerPayoutPaid implements ApprovalExecutor
{
    public function execute(ApprovalRequest $request): void
    {
        $payout = $request->subject;

        if (! $payout instanceof ResellerPayoutRequest) {
            throw new ApprovalException('Žádost nemá připojenou výplatu.');
        }

        // Guard against a second payment: only an `approved` payout may become
        // `paid`. If it already moved on, do not pay again.
        if ($payout->status !== 'approved') {
            throw new ApprovalException("Výplatu ve stavu „{$payout->status}“ nelze označit jako zaplacenou.");
        }

        $payout->update([
            'status'       => 'paid',
            'processed_by' => $request->reviewed_by,
            'processed_at' => now(),
        ]);

        activity()
            ->performedOn($payout)
            ->withProperties([
                'from'                 => 'approved',
                'to'                   => 'paid',
                'amount_minor'         => $payout->amount,
                'currency'             => $payout->currency,
                'approval_request_id'  => $request->id,
                'four_eyes'            => true,
            ])
            ->log('reseller_payout.status_changed');
    }
}
