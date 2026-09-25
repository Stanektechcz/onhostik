<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Billing\Commands\WithdrawalStaffCommand;
use Onhost\Domain\Billing\Models\Withdrawal;
use Onhost\Domain\Billing\WithdrawalService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Commands\CommandScope;

/**
 * Consumer withdrawals for finance (TASK-0025): the list — the ones still being unwound first, with the step a panel or a
 * hold refused — and the record of a notice the consumer sent by e-mail or letter, with the day it was sent.
 */
final class WithdrawalController extends ApiController
{
    public function index(Request $request, WithdrawalService $withdrawals): JsonResponse
    {
        $this->api->authorize($request, 'billing.invoice.read', CommandScope::global());
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
        $data = $request->validate([
            'organization_id' => ['required', 'string', 'max:40'], 'service_id' => ['required_without:order_id', 'nullable', 'string', 'max:40'], 'order_id' => ['required_without:service_id', 'nullable', 'string', 'max:40'],
            'sent_at' => ['required', 'date'], 'refund_to_credit_agreed' => ['accepted'], 'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $payload = array_filter(['organization_id' => $data['organization_id'], 'service_id' => $data['service_id'] ?? null, 'order_id' => $data['order_id'] ?? null, 'sent_at' => (string) $data['sent_at'], 'reason' => $data['reason']], fn ($v) => $v !== null);

        return $this->dispatch(new WithdrawalStaffCommand($this->idempotencyKey($request, 'withdrawal.staff:'.($data['service_id'] ?? $data['order_id'] ?? '')), $payload), $this->api->context($request, null, $data['reason']), 202);
    }
}
