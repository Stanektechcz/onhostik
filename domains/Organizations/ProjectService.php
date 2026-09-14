<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Organizations\Models\ProjectMembership;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\WalletLedger\Models\Budget;
use Onhost\Domain\WalletLedger\WalletForecast;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/**
 * Projects split an organization: services are assigned to a project, spend is reported per project, and a member's
 * project role (developer, cloud operator, …) applies to that project's services only — on top of the organization
 * role, which stays the floor. Archiving needs the project empty; the last active project stays.
 */
final class ProjectService
{
    public function __construct(private readonly AuditRecorder $audit, private readonly WalletForecast $forecast) {}

    /** @param array<string,mixed> $attributes */
    public function update(Organization $organization, Project $project, array $attributes, CommandContext $context): Project
    {
        $this->assertOwned($organization, $project);
        $changes = array_intersect_key($attributes, array_flip(['name', 'description', 'cost_center', 'tags']));
        if (array_key_exists('name', $changes)) {
            $changes['name'] = trim((string) $changes['name']);
            if ($changes['name'] === '') {
                throw new DomainError('project_name_required', 'A project needs a name.', 422, ['field' => 'name']);
            }
        }
        if (array_key_exists('tags', $changes)) {
            $changes['tags'] = array_values(array_unique(array_filter(array_map(fn ($t) => trim((string) $t), (array) $changes['tags']), fn ($t) => $t !== '')));
        }
        if ($changes === []) {
            return $project;
        }
        $before = $project->only(array_keys($changes));
        $project->forceFill($changes)->save();
        $this->audit->record($context->withScope($organization->id, $project->id), 'project.update', 'succeeded', $changes, 'project', $project->id, before: $before, after: $changes);

        return $project;
    }

    public function archive(Organization $organization, Project $project, CommandContext $context): Project
    {
        $this->assertOwned($organization, $project);
        if ($project->state === 'archived') {
            return $project;
        }
        $live = Service::query()->where('project_id', $project->id)->where('state', '!=', ServiceStateMachine::TERMINATED)->count();
        if ($live > 0) {
            throw new DomainError('project_has_services', "Move or terminate the {$live} service(s) of this project before archiving it.", 409, ['services' => $live]);
        }
        if (Project::query()->where('organization_id', $organization->id)->where('state', 'active')->where('id', '!=', $project->id)->doesntExist()) {
            throw new DomainError('last_project', 'An organization keeps at least one active project.', 409);
        }
        $project->forceFill(['state' => 'archived'])->save();
        $this->audit->record($context->withScope($organization->id, $project->id), 'project.archive', 'succeeded', [], 'project', $project->id);

        return $project;
    }

    public function restore(Organization $organization, Project $project, CommandContext $context): Project
    {
        $this->assertOwned($organization, $project);
        if ($project->state !== 'active') {
            $project->forceFill(['state' => 'active'])->save();
            $this->audit->record($context->withScope($organization->id, $project->id), 'project.restore', 'succeeded', [], 'project', $project->id);
        }

        return $project;
    }

    /** A project role builds on an organization membership: the member keeps the organization role everywhere and gains this role inside the project. */
    public function addMember(Organization $organization, Project $project, User $user, string $roleKey, CommandContext $context): ProjectMembership
    {
        $this->assertOwned($organization, $project);
        if (! RoleCatalog::exists($roleKey) || RoleCatalog::all()[$roleKey]['staff'] || $roleKey === 'owner') {
            throw new DomainError('invalid_role', "Role {$roleKey} cannot be assigned inside a project.", 422, ['field' => 'role']);
        }
        $member = OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->where('state', 'active')->exists();
        if (! $member) {
            throw new DomainError('not_a_member', 'Invite the user to the organization first; project roles build on an organization membership.', 422, ['field' => 'user_id']);
        }

        return DB::transaction(function () use ($organization, $project, $user, $roleKey, $context) {
            $membership = ProjectMembership::query()->updateOrCreate(['project_id' => $project->id, 'user_id' => $user->id], ['role_key' => $roleKey]);
            PolicyBinding::query()->where('principal_type', 'user')->where('principal_id', $user->id)->where('scope_type', 'project')->where('scope_id', $project->id)->delete();
            PolicyBinding::query()->create([
                'principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $roleKey,
                'scope_type' => 'project', 'scope_id' => $project->id, 'organization_id' => $organization->id, 'granted_by' => $context->actorId,
            ]);
            $this->audit->record($context->withScope($organization->id, $project->id), 'project.member.add', 'succeeded', ['user_id' => $user->id, 'role' => $roleKey], 'project', $project->id);

            return $membership;
        });
    }

    public function removeMember(Organization $organization, Project $project, User $user, CommandContext $context): void
    {
        $this->assertOwned($organization, $project);
        DB::transaction(function () use ($organization, $project, $user, $context) {
            ProjectMembership::query()->where('project_id', $project->id)->where('user_id', $user->id)->delete();
            PolicyBinding::query()->where('principal_type', 'user')->where('principal_id', $user->id)->where('scope_type', 'project')->where('scope_id', $project->id)->delete();
            $this->audit->record($context->withScope($organization->id, $project->id), 'project.member.remove', 'succeeded', ['user_id' => $user->id], 'project', $project->id);
        });
    }

    public function assignService(Organization $organization, Service $service, ?Project $project, CommandContext $context): Service
    {
        if ($service->organization_id !== $organization->id) {
            throw DomainError::notFound('service');
        }
        if ($project !== null) {
            $this->assertOwned($organization, $project);
            if ($project->state !== 'active') {
                throw new DomainError('project_archived', 'Assign services to an active project.', 409);
            }
        }
        $from = $service->project_id;
        if ($from === $project?->id) {
            return $service;
        }
        $service->forceFill(['project_id' => $project?->id])->save();
        $this->audit->record($context->withScope($organization->id, $project?->id), 'project.service.assign', 'succeeded', ['service_id' => $service->id, 'from' => $from, 'to' => $project?->id], 'service', $service->id);

        return $service;
    }

    /**
     * Spend per project: the gross monthly renewal cost of the active subscriptions behind each project's services
     * (yearly plans spread over twelve months), the share of the organization's total, and the project budget if one is set.
     *
     * @return array{currency:string, monthly_total:Money, projects:list<array<string,mixed>>, unassigned:array<string,mixed>}
     */
    public function spend(Organization $organization, string $currency): array
    {
        $projects = Project::query()->where('organization_id', $organization->id)->orderBy('name')->get();
        $services = Service::query()->where('organization_id', $organization->id)->where('state', '!=', ServiceStateMachine::TERMINATED)->get(['id', 'project_id']);
        $subscriptions = Subscription::query()->where('organization_id', $organization->id)->where('state', 'active')->where('currency', $currency)
            ->whereIn('service_id', $services->pluck('id')->all())->get()->keyBy('service_id');
        $budgets = Budget::query()->where('organization_id', $organization->id)->where('currency', $currency)->whereNotNull('project_id')->get()->keyBy('project_id');

        $rows = [];
        $total = 0;
        foreach ($services as $service) {
            $key = $service->project_id ?? '';
            $rows[$key] ??= ['services' => 0, 'monthly_minor' => 0];
            $rows[$key]['services']++;
            $subscription = $subscriptions->get($service->id);
            $monthly = $subscription === null ? 0 : $this->monthlyGross($organization, $subscription);
            $rows[$key]['monthly_minor'] += $monthly;
            $total += $monthly;
        }
        $line = function (?Project $project, array $row) use ($currency, $total, $budgets): array {
            $budget = $project === null ? null : $budgets->get($project->id);

            return [
                'project_id' => $project?->id, 'name' => $project?->name ?? 'Nezařazeno', 'slug' => $project?->slug, 'state' => $project?->state ?? 'active',
                'services' => $row['services'], 'monthly' => Money::minor($row['monthly_minor'], $currency),
                'share' => $total > 0 ? round($row['monthly_minor'] / $total * 100, 1) : 0.0,
                'budget' => $budget === null ? null : ['limit' => Money::minor((int) $budget->limit_minor, $currency), 'spent' => Money::minor((int) $budget->spent_minor, $currency), 'hard' => (bool) $budget->hard],
            ];
        };

        return [
            'currency' => $currency,
            'monthly_total' => Money::minor($total, $currency),
            'projects' => $projects->map(fn (Project $p) => $line($p, $rows[$p->id] ?? ['services' => 0, 'monthly_minor' => 0]))->values()->all(),
            'unassigned' => $line(null, $rows[''] ?? ['services' => 0, 'monthly_minor' => 0]),
        ];
    }

    private function monthlyGross(Organization $organization, Subscription $subscription): int
    {
        $gross = $this->forecast->gross($organization, $subscription)->minor;

        return $subscription->period === 'year' ? intdiv($gross, 12) : $gross;
    }

    private function assertOwned(Organization $organization, Project $project): void
    {
        if ($project->organization_id !== $organization->id) {
            throw DomainError::notFound('project');
        }
    }
}
