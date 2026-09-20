<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\Models\JitElevation;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Commands\CommandScope;

/**
 * Evaluates capability checks against policy bindings + active JIT elevations.
 * Scope semantics: global bindings apply everywhere; organization bindings apply
 * to the organization, its projects and its resources; project bindings only to
 * that project. Results are memoised per principal for the request lifetime.
 */
final class Authorizer
{
    /** @var array<string, list<array{role:string, scope_type:string, scope_id:?string, organization_id:?string}>> */
    private array $bindingCache = [];

    /** @var array<string, list<string>> */
    private array $rolePermissionCache = [];

    public function can(Authenticatable|ServiceAccount $principal, string $permission, ?CommandScope $scope = null): bool
    {
        if (! PermissionCatalog::exists($permission)) {
            return false;
        }
        if ($principal instanceof User && ! $principal->isActive()) {
            return false;
        }
        if ($principal instanceof ServiceAccount && ! $principal->isActive()) {
            return false;
        }
        $scope ??= CommandScope::global();
        foreach ($this->bindings($principal) as $binding) {
            if (! $this->bindingCovers($binding, $scope)) {
                continue;
            }
            if (in_array($permission, $this->rolePermissions($binding['role']), true)) {
                return true;
            }
        }

        return false;
    }

    /** All permissions the principal holds at a scope (for /v1/me and UI gating). @return list<string> */
    public function permissionsAt(Authenticatable|ServiceAccount $principal, ?CommandScope $scope = null): array
    {
        $scope ??= CommandScope::global();
        $permissions = [];
        foreach ($this->bindings($principal) as $binding) {
            if ($this->bindingCovers($binding, $scope)) {
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
        foreach ($this->bindings($principal) as $binding) {
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
        foreach ($this->bindings($principal) as $binding) {
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
        foreach ($this->bindings($principal) as $binding) {
            if ($binding['organization_id'] !== null) {
                $ids[] = $binding['organization_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    public function hasGlobalBinding(Authenticatable|ServiceAccount $principal): bool
    {
        foreach ($this->bindings($principal) as $binding) {
            if ($binding['scope_type'] === 'global') {
                return true;
            }
        }

        return false;
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
    }

    /** @return list<array{role:string, scope_type:string, scope_id:?string, organization_id:?string}> */
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
        $bindings = $rows->map(fn ($r) => ['role' => $r->role_key, 'scope_type' => $r->scope_type, 'scope_id' => $r->scope_id, 'organization_id' => $r->organization_id])->all();

        if ($type === 'user') {
            $elevations = JitElevation::query()
                ->where('user_id', $principal->getAuthIdentifier())
                ->where('state', 'approved')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->get(['role_key', 'scope_type', 'scope_id']);
            foreach ($elevations as $e) {
                $bindings[] = ['role' => $e->role_key, 'scope_type' => $e->scope_type, 'scope_id' => $e->scope_id, 'organization_id' => $e->scope_type === 'organization' ? $e->scope_id : null];
            }
        }

        return $this->bindingCache[$key] = $bindings;
    }

    /** @param array{role:string, scope_type:string, scope_id:?string, organization_id:?string} $binding */
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
