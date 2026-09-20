<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Identity\Authorization\ApprovalService;
use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Commands\ApprovalDecisionCommand;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/**
 * Nastavení systému → Schvalování: requests for a second person (four eyes). Whoever may decide approvals sees all of
 * them, everybody else their own — so the requester can watch what became of the request the refusal opened for them.
 */
final class ApprovalController extends ApiController
{
    public function index(Request $request, ApprovalService $approvals): JsonResponse
    {
        $user = $this->staff($request);
        $query = $approvals->visibleTo($user)->orderByRaw("case state when 'pending' then 0 else 1 end")->orderByDesc('created_at');
        $state = (string) $request->query('state', '');
        if ($state === 'pending') {
            $query->where('state', 'pending')->where('expires_at', '>', now());
        } elseif ($state !== '') {
            $query->where('state', $state);
        }
        $rows = $query->limit(max(1, min(200, (int) $request->query('limit', 100))))->get();
        $users = User::query()->whereIn('id', $rows->pluck('requested_by')->merge($rows->pluck('decided_by'))->filter()->unique()->all())->get()->keyBy('id');
        $deciders = ApprovalService::deciders();

        return $this->ok([
            'data' => $rows->map(fn (Approval $a) => ApprovalService::present($a, $users) + ['mine' => $a->requested_by === $user->id])->values()->all(),
            'meta' => [
                'four_eyes' => ApprovalService::enabled(), 'can_decide' => $this->api->can($request, ApprovalService::PERMISSION, CommandScope::global()),
                // four eyes need two heads: with fewer than two people who may decide, a critical action of the only one can never be approved
                'deciders' => $deciders->count(), 'second_person_exists' => $deciders->where('id', '!=', $user->id)->isNotEmpty(),
            ],
        ]);
    }

    public function decide(Request $request, string $approval): JsonResponse
    {
        $this->staff($request);
        $data = $request->validate(['decision' => ['required', 'string', 'in:approved,rejected'], 'note' => ['nullable', 'string', 'max:1000']]);

        return $this->dispatch(new ApprovalDecisionCommand($this->idempotencyKey($request, 'approval.decide'), ['approval_id' => $approval] + $data), $this->api->context($request));
    }

    private function staff(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->is_staff) {
            throw DomainError::forbidden('Staff only.');
        }

        return $user;
    }
}
