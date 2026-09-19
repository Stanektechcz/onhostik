<?php

declare(strict_types=1);

namespace App\Http\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/**
 * Request → CommandContext / organization resolution / list pagination for the v1 API.
 * The organization comes from `X-Organization` (or `?organization=`); without it the
 * user's first active membership is used. Staff with `staff.customer.read` may address
 * any organization; customers only those they belong to.
 */
final class ApiContext
{
    public function __construct(private readonly Authorizer $authorizer, private readonly StepUpService $stepUp) {}

    public function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new DomainError('unauthenticated', 'Sign in to continue.', 401);
        }

        return $user;
    }

    public function organization(Request $request, bool $required = true): ?Organization
    {
        $user = $this->user($request);
        $id = $request->headers->get('X-Organization') ?: $request->query('organization');
        if (is_string($id) && $id !== '') {
            $organization = Organization::query()->find($id);
            if ($organization === null) {
                throw DomainError::notFound('organization');
            }
            $member = OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->current()->exists(); // active and not past its end date (H343)
            if (! $member && ! $this->authorizer->can($user, 'staff.customer.read', CommandScope::organization($organization->id))) {
                throw DomainError::forbidden('You are not a member of this organization.');
            }

            return $organization;
        }
        $membership = OrganizationMembership::query()->where('user_id', $user->id)->current()->orderBy('created_at')->first();
        $organization = $membership ? Organization::query()->find($membership->organization_id) : null;
        if ($organization === null && $required) {
            throw new DomainError('organization_required', 'Choose an organization (X-Organization header).', 422, ['field' => 'organization']);
        }

        return $organization;
    }

    public function context(Request $request, ?Organization $organization = null, ?string $reason = null): CommandContext
    {
        $user = $request->user();
        $sessionId = $this->sessionId($request);
        $stepUp = $user instanceof User ? $this->stepUp->activeGrant($user, $sessionId)?->method : null;
        $token = $user instanceof User ? $user->currentAccessToken() : null;
        $actorType = $token instanceof PersonalAccessToken ? 'user' : 'user';

        return new CommandContext(
            $actorType, $user?->getAuthIdentifier() !== null ? (string) $user->getAuthIdentifier() : null, $organization?->id, null,
            $request->ip(), mb_substr((string) $request->userAgent(), 0, 250), $sessionId, $reason ?? $request->input('reason'), $request->input('ticket_ref'), $stepUp,
            array_values(array_filter((array) $request->input('approval_ids', []), 'is_string')), CommandContext::currentCorrelationId(), Context::get('request_id'),
        );
    }

    public function sessionId(Request $request): ?string
    {
        if ($request->hasSession() && $request->session()->isStarted()) {
            return $request->session()->getId();
        }
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        return $token instanceof PersonalAccessToken ? 'token:'.$token->getKey() : null;
    }

    public function can(Request $request, string $permission, ?CommandScope $scope = null): bool
    {
        $user = $request->user();

        return $user instanceof Authenticatable && $this->authorizer->can($user, $permission, $scope);
    }

    public function authorize(Request $request, string $permission, ?CommandScope $scope = null): void
    {
        if (! $this->can($request, $permission, $scope)) {
            throw DomainError::forbidden("Missing permission {$permission}");
        }
        $this->assertTokenScope($request, $permission);
    }

    /** API tokens carry documented scopes; a token without the scope for a permission family is refused even if the user could. */
    public function assertTokenScope(Request $request, ?string $permission): void
    {
        if ($permission === null) {
            return;
        }
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;
        if (! $token instanceof PersonalAccessToken) {
            return;
        }
        $needed = match (true) {
            str_starts_with($permission, 'service.manage'), str_starts_with($permission, 'service.delete'), str_starts_with($permission, 'backup.restore') => 'services:power',
            str_starts_with($permission, 'service.'), str_starts_with($permission, 'backup.read') => 'services:read',
            str_starts_with($permission, 'billing.invoice') => 'invoices:read',
            str_starts_with($permission, 'billing.wallet.read') => 'wallet:read',
            str_starts_with($permission, 'support.ticket') => 'tickets:write',
            str_starts_with($permission, 'dns.') => 'dns:write',
            str_starts_with($permission, 'domain.read') => 'domains:read',
            default => null,
        };
        if ($needed === null) {
            throw DomainError::forbidden('This action is not available to API tokens; use the portal.');
        }
        if (! $token->can($needed)) {
            throw DomainError::forbidden("The API token lacks the {$needed} scope.");
        }
    }

    /** `?limit=40&offset=0` + `X-Total-Count`, the shape the surfaces already paginate with. @param callable(mixed):array $present */
    public function paginate(Request $request, Builder $query, callable $present, string $defaultSort = 'created_at'): JsonResponse
    {
        $limit = max(1, min((int) config('onhost.api.max_page_size', 200), (int) $request->query('limit', (string) config('onhost.api.page_size', 40))));
        $offset = max(0, (int) $request->query('offset', '0'));
        $total = (clone $query)->count();
        $rows = $query->orderByDesc($defaultSort)->skip($offset)->take($limit)->get();

        return response()->json(['data' => $rows->map($present)->values()->all(), 'total' => $total, 'limit' => $limit, 'offset' => $offset])->header('X-Total-Count', (string) $total);
    }
}
