<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Billing\Commands\WithdrawalStaffCommand;
use Onhost\Domain\Billing\Models\Withdrawal;
use Onhost\Domain\Billing\WithdrawalPolicy;
use Onhost\Domain\Billing\WithdrawalService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Commands\PaymentRefundCommand;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Platform\Commands\CommandScope;

/**
 * Consumer withdrawals for finance (TASK-0025): the list — the ones still being unwound first, with the step a panel or a
 * hold refused — and the record of a notice the consumer sent by e-mail or letter, with the day it was sent.
 */
final class WithdrawalController extends ApiController
{
    public function index(Request $request, WithdrawalService $withdrawals): JsonResponse
    {
        $this->api->authorize($request, 'staff.billing.read', CommandScope::global()); // the staff key, not the customer's at global scope (TASK-0037, program IF-18)
        $state = (string) $request->query('state', '');
        $query = Withdrawal::query()->orderByDesc('created_at');
        if ($state !== '') {
            $query->whereIn('state', $state === 'open' ? Withdrawal::OPEN : [$state]);
        }
        $rows = $query->limit(200)->get();
        $organizations = Organization::query()->whereIn('id', $rows->pluck('organization_id'))->pluck('name', 'id');

        return response()->json(['data' => ['rows' => $rows->map(fn (Withdrawal $w) => $withdrawals->present($w) + ['organization' => $organizations->get($w->organization_id)])->all(),
            'open' => Withdrawal::query()->whereIn('state', Withdrawal::OPEN)->count(), 'stalled' => Withdrawal::query()->whereIn('state', Withdrawal::OPEN)->whereNotNull('error')->count(),
            'can_record' => $this->api->can($request, 'billing.refund.execute', CommandScope::global())]]);
    }

    /** A notice received by e-mail or letter: the day it was SENT decides the deadline and the refund; four eyes, because staff may date it back. */
    public function store(Request $request): JsonResponse
    {
        // H0 (H-R5, review M2): said before a second person is asked to approve anything — whatever id names the top-up
        $named = array_filter([(string) $request->input('topup_id', ''), (string) $request->input('order_id', '')], fn (string $v) => $v !== '');
        if ($request->filled('topup_id') || array_filter($named, fn (string $id) => WithdrawalPolicy::isTopUpReference($id, (string) $request->input('organization_id', ''))) !== []) {
            WithdrawalPolicy::refuseTopUp();
        }
        $data = $request->validate([
            'organization_id' => ['required', 'string', 'max:40'], 'service_id' => ['required_without:order_id', 'nullable', 'string', 'max:40'], 'order_id' => ['required_without:service_id', 'nullable', 'string', 'max:40'],
            'sent_at' => ['required', 'date'], 'refund_to_credit_agreed' => ['nullable', 'boolean'], 'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $payload = array_filter(['organization_id' => $data['organization_id'], 'service_id' => $data['service_id'] ?? null, 'order_id' => $data['order_id'] ?? null, 'sent_at' => (string) $data['sent_at'], 'reason' => $data['reason'],
            'refund_method' => ! empty($data['refund_to_credit_agreed']) ? Withdrawal::METHOD_CREDIT : Withdrawal::METHOD_SOURCE], fn ($v) => $v !== null); // L-06: the letter's own choice

        return $this->dispatch(new WithdrawalStaffCommand($this->idempotencyKey($request, 'withdrawal.staff:'.($data['service_id'] ?? $data['order_id'] ?? '')), $payload), $this->api->context($request, null, $data['reason']), 202);
    }

    /**
     * L-06: finance pays back to the order's payment what a withdrawal without the agreement to the credit owes it (state
     * `payout_due`). The amount is the withdrawal's, never typed in: a fresh step-up, four eyes from the approval threshold.
     */
    public function payout(Request $request, string $withdrawal): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:250']]);
        $row = Withdrawal::query()->findOrFail($withdrawal);
        $intent = $row->payout_payment_id === null ? null : PaymentIntent::query()->find($row->payout_payment_id);

        return $this->dispatch(new PaymentRefundCommand($this->idempotencyKey($request, 'withdrawal.payout:'.$row->id.':'.(int) $row->paid_out_minor), [
            'op' => 'refund.withdrawal_payout', 'withdrawal_id' => $row->id, 'organization_id' => $row->organization_id, 'amount_minor' => max(0, (int) $row->payout_minor - (int) $row->paid_out_minor), 'currency' => $row->currency,
            'payment_refunded_minor' => (int) ($intent->refunded_minor ?? 0), 'reason' => $data['reason'],
        ]), $this->api->context($request, null, $data['reason']));
    }
}
