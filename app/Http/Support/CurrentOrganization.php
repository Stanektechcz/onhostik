<?php

declare(strict_types=1);

namespace App\Http\Support;

use Illuminate\Http\Request;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Platform\Errors\DomainError;

/**
 * The organization a person in several organizations has chosen to work in (TASK-0070, audit 2026-10 C11).
 *
 * The portal acted for the first membership, always: the boot object, the panel's data script and every API call that named no
 * organization. A person in two companies could never see the second. The choice is kept in the web session (`SESSION_KEY`) and
 * counts only while the membership is current — a person removed from the organization, or whose access ended, falls back to the
 * default on the next request. Nothing here widens access: the choice is one of the person's own memberships, and every
 * request that names an organization is still checked against the membership by ApiContext.
 */
final class CurrentOrganization
{
    public const SESSION_KEY = 'onhost_organization';

    /** The organization chosen in this session, when the person is still a current member of it; null otherwise. */
    public static function chosen(Request $request, User $user): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }
        $id = $request->session()->get(self::SESSION_KEY);
        if (! is_string($id) || $id === '') {
            return null;
        }
        if (! self::isMember($user, $id)) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return $id;
    }

    /**
     * Makes `$organizationId` the session's organization. Only a current member may choose it — staff included: the staff
     * console reaches other organizations by its own routes, never by choosing one here.
     *
     * @throws DomainError 403 not a member · 409 no web session (an API token chooses nothing)
     */
    public static function choose(Request $request, User $user, string $organizationId): void
    {
        if (! self::isMember($user, $organizationId)) {
            throw DomainError::forbidden('You are not a member of this organization.');
        }
        if (! $request->hasSession()) {
            throw new DomainError('organization_switch_needs_session', 'The organization is chosen in the signed-in portal.', 409);
        }
        $request->session()->put(self::SESSION_KEY, $organizationId);
    }

    private static function isMember(User $user, string $organizationId): bool
    {
        return OrganizationMembership::query()->where('user_id', $user->id)->where('organization_id', $organizationId)->current()->exists();
    }
}
