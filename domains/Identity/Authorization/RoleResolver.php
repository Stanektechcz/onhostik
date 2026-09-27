<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Authorization;

/**
 * The one place that turns a role key into the permissions it carries (permission program D6, P0-11).
 *
 * Code that decides about GRANTING read `RoleCatalog::all()[$key]['permissions'] ?? []` in several places and treated an
 * unknown key as "carries nothing" — right for granting, wrong for taking away: a member with a role the catalogue no longer
 * knows could be removed or demoted by anybody, because covering "nothing" is free (`OrganizationsCommandHandler.php:97`).
 * The answer now depends on the direction, and fails closed both ways:
 *
 *  - grantable(): what giving this role hands over — an unknown role hands over nothing (it cannot be granted);
 *  - coveredForRevoke(): what somebody must hold to take this role away from another person — an unknown role counts as
 *    everything, so only whoever holds everything may remove it.
 *
 * RiskFloorTest keeps new readers of the raw catalogue out (an allow-list that only shrinks).
 */
final class RoleResolver
{
    public static function exists(string $role): bool
    {
        return RoleCatalog::exists($role);
    }

    /** @return list<string> */
    public static function grantable(string $role): array
    {
        return self::exists($role) ? RoleCatalog::all()[$role]['permissions'] : [];
    }

    /** @return list<string> */
    public static function coveredForRevoke(string $role): array
    {
        return self::exists($role) ? RoleCatalog::all()[$role]['permissions'] : PermissionCatalog::keys();
    }

    /**
     * Every catalogue role with the permissions it carries — what AuthorizationSeeder writes to `role_permissions`.
     *
     * @return array<string, list<string>>
     */
    public static function catalogue(): array
    {
        return array_map(fn (array $role) => $role['permissions'], RoleCatalog::all());
    }
}
