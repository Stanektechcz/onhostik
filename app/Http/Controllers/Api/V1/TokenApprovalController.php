<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Authorization\TokenApprovals;
use Onhost\Domain\Identity\Commands\TokenApprovalDecisionCommand;
use Onhost\Platform\Errors\DomainError;

/**
 * H0 (owner decision H-R1, permission program S1-05): requests an API token opened for a risky action of its organization. The
 * owner sees every one and decides them (fresh step-up, TokenApprovalDecisionCommand); anybody else sees only the requests of
 * their own personal tokens. API tokens do not reach these routes at all (TokenRouteScope: no family) — a token never approves.
 */
final class TokenApprovalController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $organization = $this->api->organization($request);
        $user = $this->api->user($request);
        $query = Approval::query()->where('organization_id', $organization->id)->whereNotNull('payload->'.TokenApprovals::PAYLOAD_KEY.'->id');
        if ((string) $organization->owner_user_id !== $user->id) {
            $query->where('requested_by', $user->id);
        }
        $state = (string) $request->query('state', '');
        if ($state === 'pending') {
            $query->where('state', 'pending')->where('expires_at', '>', now());
        } elseif ($state !== '') {
            $query->where('state', $state);
        }
        $rows = $query->orderByRaw("case state when 'pending' then 0 else 1 end")->orderByDesc('created_at')->limit(max(1, min(200, (int) $request->query('limit', 100))))->get();

        return $this->ok(['data' => $rows->map(fn (Approval $a) => TokenApprovals::present($a))->values()->all(), 'meta' => ['can_decide' => (string) $organization->owner_user_id === $user->id]]);
    }

    public function decide(Request $request, string $approval): JsonResponse
    {
        $organization = $this->api->organization($request);
        // another organization's request (or no token's) is not found, whatever is sent; anybody but the owner hears "owner" first
        if (! Approval::query()->whereKey($approval)->where('organization_id', $organization->id)->whereNotNull('payload->'.TokenApprovals::PAYLOAD_KEY.'->id')->exists()) {
            throw DomainError::notFound('approval');
        }
        if ((string) $organization->owner_user_id !== $this->api->user($request)->id) {
            throw DomainError::forbidden('A request of an API token is decided by the owner of the organization.');
        }
        $data = $request->validate(['decision' => ['required', 'string', 'in:approved,rejected'], 'note' => ['nullable', 'string', 'max:1000']]);

        return $this->dispatch(new TokenApprovalDecisionCommand($organization->id, $this->idempotencyKey($request, "token_approval.decide:{$approval}"), ['approval_id' => $approval] + $data), $this->api->context($request, $organization));
    }
}
