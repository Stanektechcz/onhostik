<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity;

use Carbon\CarbonInterface;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\GrantPolicy;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

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
    public function __construct(private readonly OutboxPublisher $outbox) {}

    /** @return int how many live tokens were revoked */
    public function revokePersonalTokens(User $user, ?PersonalAccessToken $keep = null): int
    {
        $live = $user->tokens()->whereNull('revoked_at');
        if ($keep !== null) {
            $live->whereKeyNot($keep->getKey()); // defensive: a bearer token cannot reach a password change (TokenRouteScope)
        }

        return $live->update(['revoked_at' => now()]);
    }

    /**
     * S1-07 red team (TASK-0042): a person removed from an organization kept their tokens bound to it. They only LOOKED dead — the
     * bindings were gone — so a restore of the access snapshot within its 90 days, or a new invitation, woke them up again, the
     * one handed to CI and the leaked one that was the reason for the removal included. Discord links and hooks stayed off after
     * a restore (TASK-0035); tokens did not. Now the tokens of the organization end with the membership, and nothing gives them
     * back: a restore brings back access, never a credential.
     *
     * `$issuedBefore`: only tokens issued before the loss (the outbox may deliver the removal after the person was let back in
     * and made a new one). `$keepScopes`: a demotion ends only a token carrying a scope outside what the person still holds
     * (null = every token of the organization). Only the person's own tokens bound to THIS organization — a token stored with no
     * organization is PA-04's, behind `ONHOST_TOKEN_ORGANIZATION_REQUIRED` (breach register).
     *
     * @param  list<string>|null  $keepScopes
     * @return int how many were revoked
     */
    public function revokeForOrganization(string $userId, string $organizationId, string $reason, ?CarbonInterface $issuedBefore = null, ?array $keepScopes = null): int
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return 0;
        }
        $revoked = 0;
        foreach ($user->tokens()->whereNull('revoked_at')->get() as $token) {
            $abilities = array_map('strval', (array) $token->abilities);
            if ((string) $token->getAttribute('organization_id') !== $organizationId && ! in_array('org:'.$organizationId, $abilities, true)) {
                continue;
            }
            if ($issuedBefore !== null && $token->created_at !== null && $token->created_at->gt($issuedBefore)) {
                continue;
            }
            $scopes = array_values(array_filter($abilities, fn (string $ability) => ! str_starts_with($ability, 'org:')));
            if ($keepScopes !== null && array_diff($scopes, $keepScopes) === []) {
                continue;
            }
            $token->forceFill(['revoked_at' => now()])->save();
            $this->outbox->publish(GenericEvent::of('api_token.revoked', 'user', $user->id, ['token_id' => (string) $token->getKey(), 'reason' => $reason], $organizationId));
            $revoked++;
        }

        return $revoked;
    }

    /**
     * The token scopes what `$userId` holds in the organization now still carries: every live binding of theirs there (the
     * organization role, project roles, `svc_*` shares) through the one permission → scope map (TokenScopes).
     *
     * @return list<string>
     */
    public static function scopesHeldIn(string $userId, string $organizationId): array
    {
        $scopes = [];
        $roles = PolicyBinding::query()->where('principal_type', 'user')->where('principal_id', $userId)->where('organization_id', $organizationId)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->pluck('role_key');
        foreach ($roles as $role) {
            foreach (GrantPolicy::permissionsOf((string) $role) ?? [] as $permission) {
                $scope = TokenScopes::for($permission);
                if ($scope !== null) {
                    $scopes[$scope] = true;
                }
            }
        }

        return array_keys($scopes);
    }
}
