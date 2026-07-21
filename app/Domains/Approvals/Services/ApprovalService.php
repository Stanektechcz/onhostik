<?php

declare(strict_types=1);

namespace App\Domains\Approvals\Services;

use App\Domains\Approvals\Contracts\ApprovalExecutor;
use App\Domains\Approvals\Exceptions\ApprovalException;
use App\Domains\Approvals\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Orchestrates four-eyes approvals (audit 74).
 *
 * Two responsibilities: decide whether an action needs a second admin, and run
 * the two-step request → approve → execute flow. The single invariant that
 * makes it "four eyes" is enforced here: the admin who requested an action can
 * never be the one who approves it.
 */
final class ApprovalService
{
    /**
     * Does this action need a second admin, given its context?
     *
     * @param array<string, mixed> $context
     */
    public function required(string $action, array $context = []): bool
    {
        $config = config("approvals.actions.{$action}");

        if (! is_array($config) || ! ($config['enabled'] ?? false)) {
            return false;
        }

        $min = (int) ($config['min_amount_minor'] ?? 0);

        // A threshold of 0 means "always"; otherwise only amounts at or above
        // the bar need the second signature.
        if ($min > 0) {
            return (int) ($context['amount_minor'] ?? 0) >= $min;
        }

        return true;
    }

    /**
     * Stage an action for approval. Writes only the request — nothing else.
     *
     * @param array<string, mixed> $payload
     */
    public function request(string $action, array $payload, User $requester, ?Model $subject = null): ApprovalRequest
    {
        $request = new ApprovalRequest([
            'action'       => $action,
            'payload'      => $payload,
            'requested_by' => $requester->id,
            'status'       => ApprovalRequest::STATUS_PENDING,
        ]);

        if ($subject !== null) {
            $request->subject()->associate($subject);
        }

        $request->save();

        activity('approvals')
            ->performedOn($request)
            ->causedBy($requester)
            ->withProperties(['action' => $action])
            ->log('approval.requested');

        return $request;
    }

    /**
     * Approve and execute. Throws if the approver is the requester (the whole
     * point) or the request is not pending.
     *
     * @throws ApprovalException
     */
    public function approve(ApprovalRequest $request, User $approver): void
    {
        if (! $request->isPending()) {
            throw new ApprovalException('Tuto žádost již nelze schválit.');
        }

        // Four eyes, not two: the requester approving their own action would
        // defeat the entire control.
        if ((int) $request->requested_by === (int) $approver->id) {
            throw new ApprovalException('Vlastní žádost nemůže schválit její zadavatel.');
        }

        $request->update([
            'status'      => ApprovalRequest::STATUS_APPROVED,
            'reviewed_by' => $approver->id,
            'reviewed_at' => now(),
        ]);

        try {
            $this->executorFor($request->action)->execute($request);

            $request->update([
                'status'      => ApprovalRequest::STATUS_EXECUTED,
                'executed_at' => now(),
            ]);
        } catch (Throwable $e) {
            // The approval stands, but the execution failed — record why rather
            // than leave the row looking done. The action did NOT happen.
            $request->update([
                'status'         => ApprovalRequest::STATUS_FAILED,
                'failure_reason' => mb_substr($e->getMessage(), 0, 500),
            ]);

            throw new ApprovalException('Provedení akce selhalo: ' . $e->getMessage(), previous: $e);
        }

        activity('approvals')
            ->performedOn($request)
            ->causedBy($approver)
            ->withProperties(['action' => $request->action])
            ->log('approval.approved');
    }

    /** Reject a pending request; nothing executes. */
    public function reject(ApprovalRequest $request, User $approver, ?string $note = null): void
    {
        if (! $request->isPending()) {
            throw new ApprovalException('Tuto žádost již nelze zamítnout.');
        }

        $request->update([
            'status'      => ApprovalRequest::STATUS_REJECTED,
            'reviewed_by' => $approver->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        activity('approvals')
            ->performedOn($request)
            ->causedBy($approver)
            ->withProperties(['action' => $request->action])
            ->log('approval.rejected');
    }

    private function executorFor(string $action): ApprovalExecutor
    {
        $class = config("approvals.actions.{$action}.executor");

        if (! is_string($class) || ! class_exists($class)) {
            throw new ApprovalException("Pro akci „{$action}“ není definován executor.");
        }

        $executor = app($class);

        if (! $executor instanceof ApprovalExecutor) {
            throw new ApprovalException("Executor akce „{$action}“ neimplementuje ApprovalExecutor.");
        }

        return $executor;
    }
}
