<?php

declare(strict_types=1);

namespace App\Http\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use LogicException;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\TokenScopes;
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
        // the token first: an Origin or Referer of a stateful domain makes Sanctum start a session for a bearer request too, and
        // that fresh session id would stand in for `token:<id>` — StepUpService then matched a session-less grant and a HIGH
        // action ran through a token (TASK-0030 review round 1). A token is a token, whatever headers it is sent with.
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;
        if ($token instanceof PersonalAccessToken) {
            return 'token:'.$token->getKey();
        }

        return $request->hasSession() && $request->session()->isStarted() ? $request->session()->getId() : null;
    }

    /**
     * Whether this request may do `$permission` — the person's roles AND, for a bearer, the token's scopes. A read path that
     * shows more to whoever manages (operation secrets, revealed listings, deploy values) must not show it to a read-only
     * token of a person who manages (C13-H2c).
     */
    public function can(Request $request, string $permission, ?CommandScope $scope = null): bool
    {
        return $this->holds($request, $permission, $scope) && $this->tokenAllows($request, $permission);
    }

    /**
     * `$tokenPermission`: what the token is asked for when it is not `$permission` — an action endpoint finds the service as a
     * read of the person, but the token is asked for what the action will do (a console-only token runs the console's commands
     * and reads nothing, TASK-0030 review round 1). The bus asks the person for the action's own permission afterwards.
     */
    public function authorize(Request $request, string $permission, ?CommandScope $scope = null, ?string $tokenPermission = null): void
    {
        // the person first, the token after: "Missing permission X" stays the answer to somebody who lacks the role
        if (! $this->holds($request, $permission, $scope)) {
            throw DomainError::forbidden("Missing permission {$permission}");
        }
        $this->assertTokenScope($request, $tokenPermission ?? $permission);
    }

    /**
     * authorize() for a write that does its work WITHOUT the bus: a HIGH permission asks for the same fresh step-up the bus
     * would, with the same answer the console's step-up dialog repeats the request on (audit §4 "Step-up is not enforced on
     * non-bus staff triggers" — dunning by hand, the capacity pass, staff SSO into a panel ran on a bare session). Reads keep
     * authorize(). A CRITICAL write never comes here: it goes through the bus, where the second person is asked.
     */
    public function authorizeAction(Request $request, string $permission, ?CommandScope $scope = null): void
    {
        if (PermissionCatalog::requiresFourEyes($permission)) {
            throw new LogicException("{$permission} is CRITICAL: dispatch a command, the bus asks for the second person.");
        }
        $this->authorize($request, $permission, $scope);
        if (! PermissionCatalog::requiresStepUp($permission)) {
            return;
        }
        $user = $request->user();
        if (! $user instanceof User) {
            throw DomainError::forbidden('Step-up authentication is only possible for a person.');
        }
        if ($this->stepUp->activeGrant($user, $this->sessionId($request)) === null) {
            throw new DomainError('step_up_required', 'Step-up authentication required for this action', 403, ['requirement' => 'step_up', 'help' => '/v1/auth/step-up']);
        }
    }

    /**
     * API tokens carry documented scopes; a token without the scope for a permission is refused even if the user could.
     * The decision is the one explicit map (TokenScopes): what it does not name is not available to tokens (C13-H2c).
     */
    public function assertTokenScope(Request $request, ?string $permission): void
    {
        $token = TokenScopes::tokenOf($request->user());
        if ($token === null) {
            return; // the portal's own session
        }
        // a command that asks for no permission is decided by its handler for a person in the portal — never opened to a token
        $needed = TokenScopes::for($permission);
        if ($needed === null) {
            throw DomainError::forbidden('This action is not available to API tokens; use the portal.');
        }
        if (! $token->can($needed)) {
            throw DomainError::forbidden("The API token lacks the {$needed} scope.");
        }
    }

    private function holds(Request $request, string $permission, ?CommandScope $scope): bool
    {
        $user = $request->user();

        return $user instanceof Authenticatable && $this->authorizer->can($user, $permission, $scope);
    }

    private function tokenAllows(Request $request, string $permission): bool
    {
        $token = TokenScopes::tokenOf($request->user());
        if ($token === null) {
            return true;
        }
        $needed = TokenScopes::for($permission);

        return $needed !== null && $token->can($needed);
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
