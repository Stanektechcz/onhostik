<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity;

use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Identity\Models\User;

/**
 * Ending a person's API access after their password changed or was reset (owner decision 14): somebody who changes the
 * password because something leaked must not be left with the leaked token working.
 *
 * Only the PERSON's tokens are reached, in every organization they belong to. It goes through `User::tokens()`, which
 * Sanctum scopes to `tokenable_type = User`, so a service account's tokens (CI, Terraform) and the integration secrets
 * that are not Sanctum tokens at all (action hooks, on-call feeds, SLA probes, Discord links) cannot be touched: a
 * colleague's password says nothing about the organization's pipeline. An integration token minted as a User token
 * would be revoked like any other personal token — that is what a personal token is.
 *
 * The rows are marked revoked, never deleted: the token list and the audit keep what existed.
 */
final class ApiAccessRevocation
{
    /** @return int how many live tokens were revoked */
    public function revokePersonalTokens(User $user, ?PersonalAccessToken $keep = null): int
    {
        $live = $user->tokens()->whereNull('revoked_at');
        if ($keep !== null) {
            $live->whereKeyNot($keep->getKey()); // defensive: a bearer token cannot reach a password change (TokenRouteScope)
        }

        return $live->update(['revoked_at' => now()]);
    }
}
