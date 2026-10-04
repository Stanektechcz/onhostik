<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Authorization;

use InvalidArgumentException;

/**
 * What a customer can hand a person, as a closed family × level matrix (permission program S1-03, D4, ruling #14; TASK-0043).
 *
 * Customers think "only mail", "only this server, restart but nothing else", not in permission keys — and a closed set cannot
 * escalate: every cell is one of the existing presets (or a share of one service built from the `svc_*` presets), never a free
 * custom role (owner default O6; if custom roles are ever built they extend `roles`/`role_permissions`, D4/#39). Each cell is
 * either a grant or a stated reason why it is not offered — CapabilityMatrixTest decides all 35 of them.
 *
 *  - Service families (web, mail, compute, game, database, apps) are granted per service (resource scope), exactly as a share
 *    of that service: sharing granularity is the service or the project (owner default O7, S1-10) — an access wizard gives a
 *    project level by sharing every service of that family in it (S1-04), never as a project role, because a project role does
 *    not know the family of the service it reaches.
 *  - dns (domains and DNS zones) is organization-wide (O7): its cells are organization presets, and say so.
 *
 * `operate` is the new narrow level (restart, PHP version, caches, certificates — no files, cron, databases, logins or shell)
 * and `data_delete` the tick that deletes data inside a service; both split off `service.manage`, which keeps them
 * (RoleCatalog::MANAGE_SPLIT), so `manage` still deletes what it deleted. The words — for every permission and every cell, cs
 * and en — are lang/{cs,en}/permissions.php and access.php.
 */
final class CapabilityMatrix
{
    public const FAMILIES = ['web', 'mail', 'dns', 'compute', 'game', 'database', 'apps'];

    public const LEVELS = ['view', 'operate', 'manage', 'console', 'data_delete'];

    /** matrix family => the `services.family` values it covers (a product's family, CatalogSeeder); add-ons follow their parent */
    public const SERVICE_FAMILIES = [
        'web' => ['web', 'managed'], 'mail' => ['mail'], 'dns' => ['domain'], 'compute' => ['cloud'], 'game' => ['game'], 'database' => ['data'], 'apps' => ['apps'],
    ];

    /** The family whose cells are organization presets: domains stay organization-wide (O7). */
    public const ORGANIZATION_FAMILY = 'dns';

    /** Why a cell is not offered — keys of lang/{cs,en}/access.php `reasons`. */
    public const NO_CONSOLE = 'no_console';

    public const NO_DATA_OBJECTS = 'no_data_objects';

    public const DOMAIN_NO_DELETE = 'domain_no_delete';

    /** A level of a service family: the `svc_*` presets a share of one service carries (ServiceAccessService binds one per tick). */
    private const SERVICE_LEVELS = [
        'view' => ['svc_view'],
        'operate' => ['svc_view', 'svc_operate'],
        'manage' => ['svc_view', 'svc_manage'],
        'console' => ['svc_view', 'svc_manage', 'svc_console'], // a console is more than managing, never less (H334)
        'data_delete' => ['svc_view', 'svc_data_delete'],
    ];

    /** A level of domains and DNS: the organization preset it is (O7: no domain is shared on its own). */
    private const ORGANIZATION_LEVELS = ['view' => 'viewer', 'operate' => 'dns_manager', 'manage' => 'domain_manager'];

    /** Cells a family has no use for: mail, databases and applications have no console; a VM and an application no data objects of their own. */
    private const CLOSED = [
        'mail' => ['console' => self::NO_CONSOLE],
        'dns' => ['console' => self::NO_CONSOLE, 'data_delete' => self::DOMAIN_NO_DELETE],
        'compute' => ['data_delete' => self::NO_DATA_OBJECTS], // its snapshots and backups are copies: the owner's (compute.vm.delete, D29.2)
        'database' => ['console' => self::NO_CONSOLE],
        'apps' => ['console' => self::NO_CONSOLE, 'data_delete' => self::NO_DATA_OBJECTS],
    ];

    /**
     * What a cell hands over: its scope and the catalogue roles. Null when the cell is not offered (see reason()).
     *
     * @return array{scope: 'resource'|'organization', roles: list<string>}|null
     */
    public static function grant(string $family, string $level): ?array
    {
        self::assertCell($family, $level);
        if (isset(self::CLOSED[$family][$level])) {
            return null;
        }
        if ($family === self::ORGANIZATION_FAMILY) {
            return ['scope' => 'organization', 'roles' => [self::ORGANIZATION_LEVELS[$level]]];
        }

        return ['scope' => 'resource', 'roles' => self::SERVICE_LEVELS[$level]];
    }

    /** Why a cell is not offered (a key of access.php `reasons`), null for an offered one. */
    public static function reason(string $family, string $level): ?string
    {
        self::assertCell($family, $level);

        return self::CLOSED[$family][$level] ?? null;
    }

    /**
     * Every cell, keyed `family:level` (the program's `fam:<family>:<level>`).
     *
     * @return array<string, array{family: string, level: string, grant: array{scope: 'resource'|'organization', roles: list<string>}|null, reason: ?string}>
     */
    public static function cells(): array
    {
        $cells = [];
        foreach (self::FAMILIES as $family) {
            foreach (self::LEVELS as $level) {
                $cells["{$family}:{$level}"] = ['family' => $family, 'level' => $level, 'grant' => self::grant($family, $level), 'reason' => self::reason($family, $level)];
            }
        }

        return $cells;
    }

    /**
     * The permissions a cell carries — what its roles grant, read through RoleResolver (D6). A cell not offered carries nothing.
     *
     * @return list<string>
     */
    public static function permissions(string $family, string $level): array
    {
        $permissions = [];
        foreach (self::grant($family, $level)['roles'] ?? [] as $role) {
            $permissions = [...$permissions, ...RoleResolver::grantable($role)];
        }

        return array_values(array_unique($permissions));
    }

    /** The matrix family of a service family (`services.family`), null for one the matrix does not offer (an add-on). */
    public static function familyOf(?string $serviceFamily): ?string
    {
        foreach (self::SERVICE_FAMILIES as $family => $serviceFamilies) {
            if (in_array($serviceFamily, $serviceFamilies, true)) {
                return $family;
            }
        }

        return null;
    }

    /**
     * What a person holding `$permission` will be able to do, in plain words (ruling #20): `can`, and `cannot` / `warning` where
     * it matters. A permission with no line reads as its catalogue description — CapabilityMatrixTest keeps every key described.
     *
     * @return array{can: string, cannot: ?string, warning: ?string}
     */
    public static function sentence(string $permission, string $locale): array
    {
        $line = self::lines('permissions', $locale)[$permission] ?? null;
        $line = is_array($line) ? $line : [];

        return [
            'can' => self::text($line['can'] ?? null) ?? (string) (PermissionCatalog::all()[$permission]['description'] ?? $permission),
            'cannot' => self::text($line['cannot'] ?? null),
            'warning' => self::text($line['warning'] ?? null),
        ];
    }

    /** @return list<string> the permissions lang/{locale}/permissions.php has a line for */
    public static function describedPermissions(string $locale): array
    {
        return array_map('strval', array_keys(self::lines('permissions', $locale)));
    }

    /**
     * A cell in words: the family and level names, and either what the person can / cannot do and what to know first, or why
     * the cell is not offered.
     *
     * @return array{family: string, level: string, can: ?string, cannot: ?string, warning: ?string, reason: ?string}
     */
    public static function describe(string $family, string $level, string $locale): array
    {
        $words = self::lines('access', $locale);
        $reason = self::reason($family, $level);
        $cell = $reason === null ? (array) ($words['cells'][$family][$level] ?? []) : [];

        return [
            'family' => (string) ($words['families'][$family] ?? $family),
            'level' => (string) ($words['levels'][$level] ?? $level),
            'can' => $reason === null ? self::text($cell['can'] ?? null) : null,
            'cannot' => $reason === null ? self::text($cell['cannot'] ?? null) : null,
            'warning' => $reason === null ? self::text($cell['warning'] ?? null) : null,
            'reason' => $reason === null ? null : self::text($words['reasons'][$reason] ?? null) ?? $reason,
        ];
    }

    /** The two languages the portal speaks; anything else reads Czech, the portal's own. */
    public static function locale(?string $locale): string
    {
        return $locale === 'en' ? 'en' : 'cs';
    }

    /** @return array<array-key, mixed> a whole lang group (the keys of permissions.php hold dots, so no dotted lookup) */
    private static function lines(string $group, string $locale): array
    {
        $lines = app('translator')->get($group, [], self::locale($locale), false);

        return is_array($lines) ? $lines : [];
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private static function assertCell(string $family, string $level): void
    {
        if (! in_array($family, self::FAMILIES, true) || ! in_array($level, self::LEVELS, true)) {
            throw new InvalidArgumentException("Unknown matrix cell {$family}:{$level}.");
        }
    }
}
