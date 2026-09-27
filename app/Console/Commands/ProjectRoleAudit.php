<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\GrantPolicy;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Organizations\Models\ProjectMembership;

/**
 * `onhost:projects:role-audit --dry-run` — the project roles that were handed out before the project allow-list existed
 * (TASK-0041, permission program IF-3 / I11, P0-07 follow-up, audit TD-3).
 *
 * `add_project_member` used to skip every grant check, so a project could carry an `org_admin`, a `billing_admin` or a key
 * no role catalogue knows: organization-wide powers — the members, the money — handed out "inside a project". GrantPolicy
 * now refuses such a grant (GrantPolicy::PROJECT_ROLES), but what was granted before is still there and still counts: the
 * permissions come from the project-scope policy binding, the membership row mirrors it. Both are read, one row per person
 * and project, and nothing is changed. There is deliberately no `--apply` (program §2 principle 11, owner rule: existing
 * customers are never changed en masse): the owner decides per membership, and a change goes through the organization's
 * own `remove_project_member` / `add_project_member` (the bus, GrantPolicy, audited).
 */
final class ProjectRoleAudit extends Command
{
    protected $signature = 'onhost:projects:role-audit
        {--dry-run : list only (the default and the only mode)}
        {--apply : refused — the owner decides per membership}
        {--organization= : only this organization id}';

    protected $description = 'List project memberships whose role is outside the project-role allow-list (read-only, TASK-0041)';

    public function handle(): int
    {
        if ((bool) $this->option('apply')) {
            $this->error('There is no --apply: a project role outside the allow-list is changed by the organization, one membership at a time, after the owner decided. Nothing was changed.');

            return self::FAILURE;
        }
        $only = trim((string) ($this->option('organization') ?? ''));
        $projects = Project::withTrashed()->when($only !== '', fn ($q) => $q->where('organization_id', $only))->get(['id', 'organization_id', 'name', 'deleted_at'])->keyBy('id');
        $found = $this->found($projects->keys()->all());
        $users = User::query()->whereIn('id', $found->pluck('user_id')->unique()->all())->pluck('email', 'id');
        $organizations = Organization::query()->whereIn('id', $projects->pluck('organization_id')->unique()->all())->pluck('name', 'id');

        $rows = $found->map(function (array $row) use ($projects, $users, $organizations): array {
            $project = $projects[$row['project_id']];
            $organizationId = (string) $project->getAttribute('organization_id');
            $until = $row['until'];

            return [
                $row['membership'] ?? '—', $row['binding'] ?? '—', $organizationId.' ('.(string) ($organizations[$organizationId] ?? '?').')',
                $project->id.' ('.(string) $project->getAttribute('name').($project->getAttribute('deleted_at') !== null ? ', deleted' : '').')',
                (string) ($users[$row['user_id']] ?? $row['user_id']), $row['role'].(RoleCatalog::exists($row['role']) ? '' : ' (unknown role)'),
                $until === null ? '—' : $until->toIso8601String().($until->isPast() ? ' (lapsed)' : ''),
            ];
        })->values()->all();

        $this->table(['membership', 'binding', 'organization', 'project', 'user', 'role', 'access until'], $rows);
        $this->line(count($rows).' project memberships outside the allow-list ('.implode(', ', GrantPolicy::PROJECT_ROLES).').');
        $this->line('Nothing was changed. An unknown role grants nothing; an organization role on a project still counts there until the organization removes it or gives an allowed role instead (the owner decides, per membership).');

        return self::SUCCESS;
    }

    /**
     * Membership rows and project-scope bindings outside the allow-list, joined per project and person (either may exist alone).
     *
     * @param  list<string>  $projectIds
     * @return Collection<string, array{project_id: string, user_id: string, role: string, membership: ?string, binding: ?string, until: ?CarbonInterface}>
     */
    private function found(array $projectIds): Collection
    {
        $found = collect();
        foreach (ProjectMembership::query()->whereIn('project_id', $projectIds)->whereNotIn('role_key', GrantPolicy::PROJECT_ROLES)->orderBy('project_id')->orderBy('created_at')->get() as $m) {
            $found->put($m->project_id.'|'.$m->user_id, ['project_id' => $m->project_id, 'user_id' => $m->user_id, 'role' => $m->role_key, 'membership' => $m->id, 'binding' => null, 'until' => $m->expires_at]);
        }
        $bindings = PolicyBinding::query()->where('principal_type', 'user')->where('scope_type', 'project')->whereIn('scope_id', $projectIds)
            ->whereNotIn('role_key', GrantPolicy::PROJECT_ROLES)->orderBy('scope_id')->orderBy('created_at')->get();
        foreach ($bindings as $b) {
            $key = (string) $b->getAttribute('scope_id').'|'.(string) $b->getAttribute('principal_id');
            $until = $b->getAttribute('expires_at');
            $found->put($key, array_merge($found->get($key, [
                'project_id' => (string) $b->getAttribute('scope_id'), 'user_id' => (string) $b->getAttribute('principal_id'), 'role' => (string) $b->getAttribute('role_key'), 'membership' => null,
                'until' => $until instanceof CarbonInterface ? $until : null,
            ]), ['binding' => $b->id]));
        }

        return $found;
    }
}
