<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\ServiceAccounts;

use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Errors\DomainError;

/**
 * What an organization's service account may be (TASK-0079, D6) — asked by the controller before anything is dispatched (so a
 * non-owner hears "owner only" and not "step up first") and again by the handler, which is the decision.
 */
final class ServiceAccountRules
{
    /** Roles never given to a pipeline: the owner's own, and a guest (which holds nothing by itself). */
    private const NEVER = ['owner', 'guest'];

    /**
     * The organization roles an account can be given — the customer roles below the owner. Never a single-service capability
     * (`svc_*`, handed out by ServiceAccessService to a person) and never a staff role.
     *
     * @return list<string>
     */
    public static function roles(): array
    {
        return array_values(array_filter(RoleCatalog::customerRoleKeys(), fn (string $key) => ! in_array($key, self::NEVER, true)));
    }

    /** Only the organization's owner manages its service accounts; an organization admin keeps their own personal tokens. */
    public static function assertOwner(Organization $organization, ?string $userId): void
    {
        if ($userId === null || (string) $organization->owner_user_id !== $userId) {
            throw DomainError::forbidden('Only the owner of the organization manages its service accounts.');
        }
    }

    /** The account of `$organization` with this id — another organization's (or a removed one) is not found. */
    public static function accountOf(Organization $organization, string $accountId): ServiceAccount
    {
        return ServiceAccount::query()->where('organization_id', $organization->id)->whereKey($accountId)->first()
            ?? throw DomainError::notFound('service account');
    }

    /** @return list<string> the documented scopes asked for, at least one */
    public static function scopes(mixed $asked): array
    {
        $scopes = array_values(array_unique(array_map('strval', is_array($asked) ? $asked : [])));
        $unknown = array_values(array_diff($scopes, TokenScopes::ALL));
        if ($scopes === [] || $unknown !== []) {
            throw new DomainError('api_token_scopes_invalid', 'Choose at least one documented scope: '.implode(', ', TokenScopes::ALL), 422, ['field' => 'scopes', 'unknown' => $unknown]);
        }

        return $scopes;
    }

    public static function role(mixed $asked): string
    {
        $role = is_string($asked) ? $asked : '';
        if (! in_array($role, self::roles(), true)) {
            throw new DomainError('service_account_role_invalid', 'Choose an organization role below the owner: '.implode(', ', self::roles()), 422, ['field' => 'role']);
        }

        return $role;
    }

    public static function name(mixed $asked, string $field = 'name'): string
    {
        $name = trim(is_string($asked) ? $asked : '');
        if ($name === '' || mb_strlen($name) > 120) {
            throw new DomainError('service_account_name_required', 'Name it (at most 120 characters) so it can be recognised in the audit log.', 422, ['field' => $field]);
        }

        return $name;
    }
}
