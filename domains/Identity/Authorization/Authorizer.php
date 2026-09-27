<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Authorization;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\Models\JitElevation;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Commands\CommandScope;
use Throwable;

/**
 * Evaluates capability checks against policy bindings + active JIT elevations.
 * Scope semantics: global bindings apply everywhere; organization bindings apply
 * to the organization, its projects and its resources; project bindings only to
 * that project. Results are memoised per principal for the request lifetime.
 *
 * TASK-0039 (permission program §3, D2/D3, IF-4/IF-5) — two views narrow that:
 *  · staff reach: a global binding (every staff role) or a JIT elevation that grants a CUSTOMER-audience permission is the
 *    platform's reach, not a customer's grant (`Authorizer.php:171` treated `global` as covering any scope — the review's
 *    BLOCKER). Staff-audience permissions are untouched. While `onhost.staff_reach_enforced` is off (the shadow release) such
 *    an allow is still given and written down once per person, key, organization and day (security_events
 *    `authz.staff_reach`, read by `operator:authz:staff-reach`); switched on after 7 days of an empty log, it is refused.
 *  · a token (or any credential with an organization) sees only the bindings of its own organization — never a global binding,
 *    never an elevation. A token bound to no organization sees its person's organization bindings until
 *    `onhost.token_organization_required` is switched on after the notice (`operator:tokens:unbound`), then nothing.
 */
final class Authorizer
{
    /** The permission comes from a binding of the organization acted on (organization, project or resource). */
    public const BASIS_MEMBER = 'member';

    /** The permission comes only from the platform's reach: a global binding or a JIT elevation. */
    public const BASIS_STAFF = 'staff';

    /** security_events kind of the shadow log (P0-08: enforced after 7 days of an empty log). */
    public const SHADOW_KIND = 'authz.staff_reach';

    /** @var array<string, list<array{role:string, scope_type:string, scope_id:?string, organization_id:?string, elevation:bool}>> */
    private array $bindingCache = [];

    /** @var array<string, list<string>> */
    private array $rolePermissionCache = [];

    /** @var array<string, true> shadow entries already written in this unit of work */
    private array $shadowed = [];

    public function can(Authenticatable|ServiceAccount $principal, string $permission, ?CommandScope $scope = null): bool
    {
        return $this->basis($principal, $permission, $scope) !== null;
    }

    /**
     * On what the principal holds `$permission` at `$scope`: BASIS_MEMBER (a binding of the organization acted on), BASIS_STAFF
     * (only a global binding or a JIT elevation — the platform's reach), or null (not at all). The bus asks this to keep the
     * catalogue's risk for staff reach (IdentityCommandAuthorizer): a customer CRITICAL key floors at HIGH only for a customer.
     */
    public function basis(Authenticatable|ServiceAccount $principal, string $permission, ?CommandScope $scope = null): ?string
    {
        if (! PermissionCatalog::exists($permission)) {
            return null;
        }
        if (($principal instanceof User || $principal instanceof ServiceAccount) && ! $principal->isActive()) {
            return null;
        }
        $scope ??= CommandScope::global();
        $roles = [];
        foreach ($this->visibleBindings($principal) as $binding) {
            if (! $this->bindingCovers($binding, $scope) || ! in_array($permission, $this->rolePermissions($binding['role']), true)) {
                continue;
            }
            if (! self::isReach($binding)) {
                return self::BASIS_MEMBER;
            }
            $roles[] = $binding['role'];
        }
        if ($roles === []) {
            return null;
        }
        if (self::audienceOf($permission) === 'customer') {
            if (self::enforced()) {
                return null;
            }
            $this->shadow($principal, $permission, $scope, $roles);
        }

        return self::BASIS_STAFF;
    }

    /** All permissions the principal holds at a scope (for /v1/me and UI gating). @return list<string> */
    public function permissionsAt(Authenticatable|ServiceAccount $principal, ?CommandScope $scope = null): array
    {
        $scope ??= CommandScope::global();
        $enforced = self::enforced();
        $permissions = [];
        foreach ($this->visibleBindings($principal) as $binding) {
            if (! $this->bindingCovers($binding, $scope)) {
                continue;
            }
            $granted = $this->rolePermissions($binding['role']);
            if ($enforced && self::isReach($binding)) {
                $granted = array_filter($granted, fn (string $permission) => self::audienceOf($permission) !== 'customer');
            }
            $permissions = array_merge($permissions, $granted);
        }

        return array_values(array_unique($permissions));
    }

    /**
     * What the principal holds at a customer scope as a CUSTOMER: its organization, project and resource bindings only. A global
     * binding (every staff role, platform_owner above all) and a JIT elevation are the platform's reach, never a customer's grant
     * right — GrantPolicy and the service share skipped every rule for `is_staff` instead, so a staff account holding
     * organization.members.manage globally made anybody anything in any customer organization, itself included (red-team round
     * of the Phase-0 chain, audit SS-1). Staff tooling that changes customer memberships needs a staff permission of its own
     * (P0-08). An inactive principal holds nothing. @return list<string>
     */
    public function customerPermissionsAt(Authenticatable|ServiceAccount $principal, CommandScope $scope): array
    {
        if (($principal instanceof User || $principal instanceof ServiceAccount) && ! $principal->isActive()) {
            return [];
        }
        $permissions = [];
        foreach ($this->visibleBindings($principal) as $binding) {
            if (! self::isReach($binding) && $this->bindingCovers($binding, $scope)) {
                $permissions = array_merge($permissions, $this->rolePermissions($binding['role']));
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Projects of an organization where a project-scoped binding grants the permission — what a member sees when the
     * organization role alone does not grant it. @return list<string>
     */
    public function projectIdsWhere(Authenticatable|ServiceAccount $principal, string $permission, string $organizationId): array
    {
        $ids = [];
        foreach ($this->visibleBindings($principal) as $binding) {
            if ($binding['scope_type'] === 'project' && $binding['organization_id'] === $organizationId && $binding['scope_id'] !== null
                && in_array($permission, $this->rolePermissions($binding['role']), true)) {
                $ids[] = $binding['scope_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Services of an organization where a resource-scoped binding grants the permission — what somebody sees who was
     * given single services and nothing of the organization (a guest). @return list<string>
     */
    public function resourceIdsWhere(Authenticatable|ServiceAccount $principal, string $permission, string $organizationId): array
    {
        $ids = [];
        foreach ($this->visibleBindings($principal) as $binding) {
            if ($binding['scope_type'] === 'resource' && $binding['organization_id'] === $organizationId && $binding['scope_id'] !== null
                && in_array($permission, $this->rolePermissions($binding['role']), true)) {
                $ids[] = $binding['scope_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /** Organizations where the principal has at least one binding. @return list<string> */
    public function organizationIds(Authenticatable|ServiceAccount $principal): array
    {
        $ids = [];
        foreach ($this->visibleBindings($principal) as $binding) {
            if ($binding['organization_id'] !== null) {
                $ids[] = $binding['organization_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    // ── TASK-0039 P0-16 re-check ──
    /**
     * Whether the principal is a party of the organization: a binding of its own there (organization, project or a shared single
     * service) — never a global binding or a JIT elevation. A member of staff acting in staff mode in such an organization takes a
     * second person (IdentityCommandAuthorizer): the operator's own company is not theirs alone to lift a hold in.
     */
    public function belongsTo(Authenticatable|ServiceAccount $principal, string $organizationId): bool
    {
        foreach ($this->visibleBindings($principal) as $binding) {
            if (! self::isReach($binding) && $binding['organization_id'] === $organizationId) {
                return true;
            }
        }

        return false;
    }
    // ── end TASK-0039 P0-16 re-check ──

    public function hasGlobalBinding(Authenticatable|ServiceAccount $principal): bool
    {
        foreach ($this->visibleBindings($principal) as $binding) {
            if ($binding['scope_type'] === 'global') {
                return true;
            }
        }

        return false;
    }

    /** The permissions a role carries as stored in `role_permissions` (StaffActor asks which staff role gives the console). @return list<string> */
    public function roleCarries(string $role): array
    {
        return $this->rolePermissions($role);
    }

    public function forget(Authenticatable|ServiceAccount $principal): void
    {
        unset($this->bindingCache[$this->cacheKey($principal)]);
    }

    /** Everything remembered is dropped: called after every request and before every queued job, so an answer is never older than the unit of work that asks. */
    public function flush(): void
    {
        $this->bindingCache = [];
        $this->rolePermissionCache = [];
        $this->shadowed = [];
    }

    /** Whether staff reach on customer permissions is refused (P0-08 after the shadow release; default off, program §10). */
    public static function enforced(): bool
    {
        return (bool) config('onhost.staff_reach_enforced', false);
    }

    /**
     * The bindings this principal may use here: all of them for a person in the portal; for a token only those of the token's
     * own organization (program principle 3, IF-5) — a global binding and an elevation never, whatever the organization.
     *
     * @return list<array{role:string, scope_type:string, scope_id:?string, organization_id:?string, elevation:bool}>
     */
    private function visibleBindings(Authenticatable|ServiceAccount $principal): array
    {
        $bindings = $this->bindings($principal);
        $token = TokenScopes::tokenOf($principal);
        if (! $token instanceof PersonalAccessToken) {
            return $bindings;
        }
        $organizationId = $token->organization_id ?? ($principal instanceof ServiceAccount ? $principal->organization_id : null);
        if ($organizationId === null && (bool) config('onhost.token_organization_required', false)) {
            return [];
        }

        return array_values(array_filter($bindings, fn (array $binding) => ! self::isReach($binding)
            && ($organizationId === null || $binding['organization_id'] === $organizationId)));
    }

    /** @return list<array{role:string, scope_type:string, scope_id:?string, organization_id:?string, elevation:bool}> */
    private function bindings(Authenticatable|ServiceAccount $principal): array
    {
        $key = $this->cacheKey($principal);
        if (isset($this->bindingCache[$key])) {
            return $this->bindingCache[$key];
        }
        $type = $principal instanceof ServiceAccount ? 'service_account' : 'user';
        $rows = PolicyBinding::query()
            ->where('principal_type', $type)
            ->where('principal_id', $principal->getAuthIdentifier())
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get(['role_key', 'scope_type', 'scope_id', 'organization_id']);
        $bindings = $rows->map(fn ($r) => ['role' => $r->role_key, 'scope_type' => $r->scope_type, 'scope_id' => $r->scope_id, 'organization_id' => $r->organization_id, 'elevation' => false])->all();

        if ($type === 'user') {
            $elevations = JitElevation::query()
                ->where('user_id', $principal->getAuthIdentifier())
                ->where('state', 'approved')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->get(['role_key', 'scope_type', 'scope_id']);
            foreach ($elevations as $e) {
                $bindings[] = ['role' => $e->role_key, 'scope_type' => $e->scope_type, 'scope_id' => $e->scope_id, 'organization_id' => $e->scope_type === 'organization' ? $e->scope_id : null, 'elevation' => true];
            }
        }

        return $this->bindingCache[$key] = $bindings;
    }

    /** @param array{role:string, scope_type:string, scope_id:?string, organization_id:?string, elevation:bool} $binding */
    private function bindingCovers(array $binding, CommandScope $scope): bool
    {
        return match ($binding['scope_type']) {
            'global' => true,
            'organization' => $scope->organizationId !== null && $binding['scope_id'] === $scope->organizationId,
            'project' => $binding['scope_id'] !== null && $binding['scope_id'] === $scope->projectId, // the project itself or a resource inside it
            'resource' => $scope->type === 'resource' && $binding['scope_id'] === $scope->id,
            default => false,
        };
    }

    /** A global binding or a JIT elevation: the platform's reach, never a customer's grant. @param array{scope_type:string, elevation:bool} $binding */
    private static function isReach(array $binding): bool
    {
        return $binding['scope_type'] === 'global' || $binding['elevation'];
    }

    private static function audienceOf(string $permission): string
    {
        return PermissionCatalog::all()[$permission]['audience'] ?? 'customer';
    }

    /**
     * The shadow log of P0-08: one row per person, customer key, organization and day in `security_events` — what would be
     * refused once `onhost.staff_reach_enforced` is on, with the roles and the route that relied on it. It never decides
     * anything: a row that cannot be written is logged and the answer stays what it was.
     *
     * Review round 2: the row is written once the caller's transaction has ended, whichever way (afterTransaction). Written inside
     * it, a later refusal (a DomainError that rolls back the bus or a handler) took the row with it while the day's cache mark
     * stayed — the rest of the day went unlogged, and a rarely used staff workflow could leave the 7-day log empty and the switch
     * be flipped too early. On PostgreSQL a failing insert inside the transaction also aborted it, try/catch or not. The cache
     * mark now stands only for a written row.
     *
     * @param  list<string>  $roles
     */
    private function shadow(Authenticatable|ServiceAccount $principal, string $permission, CommandScope $scope, array $roles): void
    {
        $principalId = (string) $principal->getAuthIdentifier();
        $key = 'onhost:authz:shadow:'.sha1(implode('|', [$principalId, $permission, (string) $scope->organizationId, now()->toDateString()]));
        if (isset($this->shadowed[$key])) {
            return;
        }
        $this->shadowed[$key] = true;
        $request = app()->bound('request') ? request() : null; // taken now: the row may be written after the request's work
        $row = [
            'id' => 'sev_'.strtolower((string) Str::ulid()), 'kind' => self::SHADOW_KIND, 'severity' => 'info',
            'user_id' => $principal instanceof ServiceAccount ? null : $principalId, 'organization_id' => $scope->organizationId, 'ip' => $request?->ip(),
            'detail' => json_encode([
                'permission' => $permission, 'roles' => array_values(array_unique($roles)), 'scope_type' => $scope->type, 'scope_id' => $scope->id,
                'principal_type' => $principal instanceof ServiceAccount ? 'service_account' : 'user', 'principal_id' => $principalId,
                'route' => $request === null ? null : $request->method().' /'.ltrim($request->path(), '/'), 'enforced' => false,
            ], JSON_UNESCAPED_SLASHES),
            'created_at' => now(), 'updated_at' => now(),
        ];
        $this->afterTransaction(fn () => $this->writeShadow($key, $row, $permission));
    }

    /** @param  array<string,mixed>  $row */
    private function writeShadow(string $key, array $row, string $permission): void
    {
        try {
            if (! Cache::add($key, true, now()->endOfDay())) {
                return; // written already today, by this or another process
            }
        } catch (Throwable $e) {
            unset($this->shadowed[$key]);
            Log::warning('authz.staff_reach shadow entry not written', ['permission' => $permission, 'error' => $e->getMessage()]);

            return;
        }
        try {
            DB::table('security_events')->insert($row);
        } catch (Throwable $e) {
            unset($this->shadowed[$key]); // the mark stands only for a written row: the next allow tries again
            try {
                Cache::forget($key);
            } catch (Throwable) {
                // the cache is what failed as well: the mark expires at the end of the day
            }
            Log::warning('authz.staff_reach shadow entry not written', ['permission' => $permission, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Runs `$write` once the transaction the caller is in has ended — committed OR rolled back — or at once outside one. A
     * savepoint rolled back inside a unit that goes on waits for that unit too. "Open" is what Laravel's own after-commit
     * callbacks count as open (the manager's applicable transactions), so a test's wrapping transaction is not waited for.
     */
    private function afterTransaction(Closure $write): void
    {
        $connection = DB::connection();
        $manager = app()->bound('db.transactions') ? app('db.transactions') : null;
        $open = $connection->transactionLevel() > 0 && $manager instanceof DatabaseTransactionsManager
            && $manager->callbackApplicableTransactions()->contains(fn ($transaction) => $transaction->connection === $connection->getName());
        if (! $open) {
            $write();

            return;
        }
        $done = false;
        $once = function () use (&$done, $write): void {
            if ($done) {
                return;
            }
            $done = true;
            $this->afterTransaction($write);
        };
        $connection->afterCommit($once);
        $connection->afterRollBack($once);
    }

    /** @return list<string> */
    private function rolePermissions(string $role): array
    {
        if (! isset($this->rolePermissionCache[$role])) {
            $this->rolePermissionCache[$role] = DB::table('role_permissions')->where('role_key', $role)->pluck('permission_key')->all();
        }

        return $this->rolePermissionCache[$role];
    }

    private function cacheKey(Authenticatable|ServiceAccount $principal): string
    {
        return ($principal instanceof ServiceAccount ? 'sa:' : 'user:').$principal->getAuthIdentifier();
    }
}
