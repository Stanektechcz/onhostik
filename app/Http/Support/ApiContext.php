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
use Onhost\Domain\Identity\Models\ServiceAccount;
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
 *
 * TASK-0039 (permission program P0-08/P0-09): a context built for a /v1/staff/* request by a member of staff is in staff mode
 * (StaffActor) — nowhere else. An API token acts for its own organization only: another one named in the request is refused,
 * none named means the token's own (IF-5).
 */
final class ApiContext
{
    public function __construct(private readonly Authorizer $authorizer, private readonly StepUpService $stepUp) {}

    public function user(Request $request): User
    {
        $user = $request->user();
        if ($user instanceof ServiceAccount) { // TASK-0079: a pipeline's token reaches the organization's endpoints, never a person's
            throw new DomainError('person_required', 'This endpoint acts for a signed-in person; a service account token cannot use it.', 403);
        }
        if (! $user instanceof User) {
            throw new DomainError('unauthenticated', 'Sign in to continue.', 401);
        }

        return $user;
    }

    public function organization(Request $request, bool $required = true): ?Organization
    {
        if ($request->user() instanceof ServiceAccount) {
            return $this->serviceAccountOrganization($request);
        }
        $user = $this->user($request);
        $id = $request->headers->get('X-Organization') ?: $request->query('organization');
        $id = self::tokenOrganization($request, is_string($id) ? $id : null) ?? $id;
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
        // TASK-0070: the organization the person chose in this web session (still a current membership), else the first one
        $chosen = TokenScopes::tokenOf($user) === null ? CurrentOrganization::chosen($request, $user) : null;
        $membership = $chosen !== null ? null : OrganizationMembership::query()->where('user_id', $user->id)->current()->orderBy('created_at')->first();
        $organizationId = $chosen ?? $membership?->organization_id;
        $organization = $organizationId !== null ? Organization::query()->find($organizationId) : null;
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
        // TASK-0079: a service account acts as itself — the bus loads it as the principal (IdentityCommandAuthorizer), decides on its
        // own bindings and never lets it past a step-up; it was named `user` with the account's id, which no person has
        $actorType = $user instanceof ServiceAccount ? 'service_account' : 'user';

        return new CommandContext(
            $actorType, $user?->getAuthIdentifier() !== null ? (string) $user->getAuthIdentifier() : null, $organization?->id, null,
            $request->ip(), mb_substr((string) $request->userAgent(), 0, 250), $sessionId, $reason ?? $request->input('reason'), $request->input('ticket_ref'), $stepUp,
            array_values(array_filter((array) $request->input('approval_ids', []), 'is_string')), CommandContext::currentCorrelationId(), Context::get('request_id'),
            staffMode: self::staffMode($request),
        );
    }

    // ── TASK-0039 (permission program P0-08 IF-8, P0-09 IF-5) ──
    /**
     * Staff mode: a /v1/staff/* request of a person in the portal (program principle 2, "staffMode set only by the /v1/staff/*
     * routes"). Whether that person IS staff is StaffActor's question, asked where it matters. A token never — tokens do not
     * reach staff routes (TokenRouteScope) and act for one organization.
     */
    public static function staffMode(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User && TokenScopes::tokenOf($user) === null && $request->is('v1/staff/*');
    }

    /**
     * The organization a token acts for, or null for the portal's own session (and for a token bound to none while
     * `onhost.token_organization_required` is off). A token of A that names B — `X-Organization` or `?organization=` — is refused
     * (a person in two organizations used the token of one for the other, audit PA-04); a token that names none acts for A.
     *
     * @throws DomainError `token_organization_mismatch` (403), `token_unbound` (403)
     */
    public static function tokenOrganization(Request $request, ?string $named = null): ?string
    {
        $token = TokenScopes::tokenOf($request->user());
        if ($token === null) {
            return null;
        }
        $own = $token->organization_id === null ? null : (string) $token->organization_id;
        if ($own === null) {
            if ((bool) config('onhost.token_organization_required', false)) {
                throw new DomainError('token_unbound', 'This API token is bound to no organization and is no longer accepted; create a new token in the organization it is for.', 403);
            }

            return null;
        }
        if ($named !== null && $named !== '' && $named !== $own) {
            throw new DomainError('token_organization_mismatch', 'This API token belongs to another organization; use a token of the organization you address.', 403);
        }

        return $own;
    }
    // ── end TASK-0039 ──

    public function sessionId(Request $request): ?string
    {
        // the token first: an Origin or Referer of a stateful domain makes Sanctum start a session for a bearer request too, and
        // that fresh session id would stand in for `token:<id>` — StepUpService then matched a session-less grant and a HIGH
        // action ran through a token (TASK-0030 review round 1). A token is a token, whatever headers it is sent with.
        $user = $request->user();
        $token = $user instanceof User || $user instanceof ServiceAccount ? $user->currentAccessToken() : null;
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

    // ── TASK-0098 (existence oracle) ──
    /**
     * authorize() for a row found by an identifier from the address: somebody with no reach into the row's organization — no
     * membership, no binding of their own there (a project role, a shared service), no staff customer view — gets the 404 a
     * missing identifier gets, not a 403 that confirms the service, the invoice number or the domain name exists. A party of
     * the organization who lacks the permission keeps the 403 that names what to ask for (SupportController::resolve).
     *
     * @throws DomainError `not_found` (404) for a stranger, `access_not_approved` (403) for a party without the permission
     */
    public function authorizeOrNotFound(Request $request, string $permission, CommandScope $scope, string $what, ?string $tokenPermission = null): void
    {
        if (! $this->reaches($request, $scope->organizationId)) {
            throw DomainError::notFound($what);
        }
        $this->authorize($request, $permission, $scope, $tokenPermission);
    }

    /**
     * Whether the caller is a party of the organization or may look at it as staff. A token sees only its own organization's
     * bindings (Authorizer::visibleBindings), so the token of A that addresses B's row is a stranger there even when its person is
     * a member of B; the membership itself counts only for the portal's own session.
     */
    public function reaches(Request $request, ?string $organizationId): bool
    {
        $principal = $request->user();
        if (! $principal instanceof User && ! $principal instanceof ServiceAccount) {
            return false;
        }
        if ($organizationId === null) {
            return $this->authorizer->can($principal, 'staff.customer.read', CommandScope::global());
        }
        if ($this->authorizer->belongsTo($principal, $organizationId)) {
            return true;
        }
        if ($principal instanceof User && TokenScopes::tokenOf($principal) === null
            && OrganizationMembership::query()->where('organization_id', $organizationId)->where('user_id', $principal->id)->current()->exists()) {
            return true;
        }

        return $this->authorizer->can($principal, 'staff.customer.read', CommandScope::organization($organizationId));
    }
    // ── end TASK-0098 ──

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

        return ($user instanceof Authenticatable || $user instanceof ServiceAccount) && $this->authorizer->can($user, $permission, $scope);
    }

    // ── TASK-0079 (audit 2026-10, D6) ──
    /**
     * The organization a service account's token acts for: the account's own, nothing else — another one named in the request is
     * refused as for any token (tokenOrganization). A disabled account opens nothing, whatever token it still has. Membership is not
     * asked: the account is the organization's, and what it may do there is its own bindings (Authorizer).
     */
    private function serviceAccountOrganization(Request $request): Organization
    {
        $account = $request->user();
        if (! $account instanceof ServiceAccount || ! $account->isActive() || $account->organization_id === null) {
            throw new DomainError('unauthenticated', 'This service account is disabled.', 401);
        }
        $named = $request->headers->get('X-Organization') ?: $request->query('organization');
        $id = self::tokenOrganization($request, is_string($named) ? $named : null) ?? (string) $account->organization_id;
        if ($id !== (string) $account->organization_id) {
            throw new DomainError('token_organization_mismatch', 'This API token belongs to another organization; use a token of the organization you address.', 403);
        }

        return Organization::query()->find($id) ?? throw DomainError::notFound('organization');
    }
    // ── end TASK-0079 ──

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
        $rows = $query->orderByDesc($defaultSort)->orderByDesc($query->getModel()->getQualifiedKeyName())->skip($offset)->take($limit)->get();

        return response()->json(['data' => $rows->map($present)->values()->all(), 'total' => $total, 'limit' => $limit, 'offset' => $offset])->header('X-Total-Count', (string) $total);
    }
}
