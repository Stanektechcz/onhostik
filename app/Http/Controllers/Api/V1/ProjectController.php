<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Presenters\Presenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Commands\OrganizationCommand;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Organizations\Models\ProjectMembership;
use Onhost\Domain\Organizations\ProjectService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/** Projects of an organization: list with spend, detail with members and services, edits, membership and service assignment. */
final class ProjectController extends ApiController
{
    public function index(Request $request, ProjectService $projects, string $organization): JsonResponse
    {
        $org = $this->organization($request, $organization, 'organization.read');
        $spend = $projects->spend($org, (string) $org->currency);
        $byProject = collect($spend['projects'])->keyBy('project_id');
        $members = ProjectMembership::query()->whereIn('project_id', $byProject->keys()->all())->get()->groupBy('project_id')->map->count();
        $rows = Project::query()->where('organization_id', $org->id)->orderBy('name')->get()
            ->map(fn (Project $p) => Presenters::project($p) + ['members' => (int) ($members[$p->id] ?? 0)] + array_diff_key((array) $byProject->get($p->id, []), array_flip(['project_id', 'name', 'slug', 'state'])))
            ->values()->all();

        return response()->json(['data' => $rows, 'spend' => ['currency' => $spend['currency'], 'monthly_total' => $spend['monthly_total'], 'unassigned' => $spend['unassigned']]]);
    }

    public function show(Request $request, ProjectService $projects, string $organization, string $project): JsonResponse
    {
        $org = $this->organization($request, $organization, 'organization.read');
        $model = $this->project($org, $project);
        $members = ProjectMembership::query()->where('project_id', $model->id)->get()->map(function (ProjectMembership $m) {
            $user = User::query()->find($m->user_id);

            return ['user_id' => $m->user_id, 'email' => $user?->email, 'name' => $user?->name, 'role' => $m->role_key, 'since' => $m->created_at?->toIso8601String()];
        })->all();
        $services = Service::query()->where('project_id', $model->id)->orderBy('created_at')->get()->map(fn (Service $s) => Presenters::service($s))->all();
        $spend = collect($projects->spend($org, (string) $org->currency)['projects'])->firstWhere('project_id', $model->id);

        return response()->json(['data' => Presenters::project($model) + ['members' => $members, 'services' => $services, 'spend' => $spend]]);
    }

    public function update(Request $request, string $organization, string $project): JsonResponse
    {
        $org = $this->organization($request, $organization, 'project.manage');
        $model = $this->project($org, $project);
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:120'], 'description' => ['sometimes', 'nullable', 'string', 'max:500'], 'cost_center' => ['sometimes', 'nullable', 'string', 'max:80'], 'tags' => ['sometimes', 'array', 'max:20'], 'tags.*' => ['string', 'max:40']]);

        return $this->dispatch(new OrganizationCommand($org->id, $this->idempotencyKey($request, "org.project.update:{$model->id}"), ['op' => 'update_project', 'project_id' => $model->id] + $data), $this->api->context($request, $org));
    }

    public function archive(Request $request, string $organization, string $project): JsonResponse
    {
        $org = $this->organization($request, $organization, 'project.manage');
        $model = $this->project($org, $project);

        return $this->dispatch(new OrganizationCommand($org->id, $this->idempotencyKey($request, "org.project.archive:{$model->id}"), ['op' => 'archive_project', 'project_id' => $model->id]), $this->api->context($request, $org));
    }

    public function restore(Request $request, string $organization, string $project): JsonResponse
    {
        $org = $this->organization($request, $organization, 'project.manage');
        $model = $this->project($org, $project);

        return $this->dispatch(new OrganizationCommand($org->id, $this->idempotencyKey($request, "org.project.restore:{$model->id}"), ['op' => 'restore_project', 'project_id' => $model->id]), $this->api->context($request, $org));
    }

    /** Adds a member by user id or e-mail (an existing organization member) with a customer role that applies inside the project only. */
    public function addMember(Request $request, string $organization, string $project): JsonResponse
    {
        $org = $this->organization($request, $organization, 'project.manage');
        $model = $this->project($org, $project);
        $data = $request->validate(['user_id' => ['required_without:email', 'nullable', 'string', 'max:40'], 'email' => ['required_without:user_id', 'nullable', 'email'], 'role' => ['required', 'string', 'max:40']]);
        $userId = $data['user_id'] ?? null;
        if ($userId === null) {
            $userId = User::query()->where('email', strtolower((string) $data['email']))->value('id');
            if ($userId === null) {
                throw new DomainError('not_a_member', 'Invite the user to the organization first; project roles build on an organization membership.', 422, ['field' => 'email']);
            }
        }

        return $this->dispatch(new OrganizationCommand($org->id, $this->idempotencyKey($request, "org.project.member:{$model->id}"), ['op' => 'add_project_member', 'project_id' => $model->id, 'user_id' => $userId, 'role' => $data['role']]), $this->api->context($request, $org), 201);
    }

    public function removeMember(Request $request, string $organization, string $project, string $user): JsonResponse
    {
        $org = $this->organization($request, $organization, 'project.manage');
        $model = $this->project($org, $project);

        return $this->dispatch(new OrganizationCommand($org->id, $this->idempotencyKey($request, "org.project.member.remove:{$model->id}"), ['op' => 'remove_project_member', 'project_id' => $model->id, 'user_id' => $user]), $this->api->context($request, $org));
    }

    /** Moves a service into a project (or out of every project with a null project_id); needs project.manage and service.manage. */
    public function assignService(Request $request, string $service): JsonResponse
    {
        $model = Service::query()->find($service);
        if ($model === null) {
            throw DomainError::notFound('service');
        }
        $this->api->authorize($request, 'service.manage', CommandScope::resource($model->id, $model->organization_id, $model->project_id));
        $org = $this->organization($request, $model->organization_id, 'project.manage');
        $data = $request->validate(['project_id' => ['present', 'nullable', 'string', 'max:40']]);
        if ($data['project_id'] !== null) {
            $this->project($org, (string) $data['project_id']);
        }

        return $this->dispatch(new OrganizationCommand($org->id, $this->idempotencyKey($request, "org.project.assign:{$model->id}"), ['op' => 'assign_service_project', 'service_id' => $model->id, 'project_id' => $data['project_id']]), $this->api->context($request, $org));
    }

    private function organization(Request $request, string $id, string $permission): Organization
    {
        $org = Organization::query()->find($id);
        if ($org === null) {
            throw DomainError::notFound('organization');
        }
        $this->api->authorize($request, $permission, CommandScope::organization($org->id));

        return $org;
    }

    private function project(Organization $organization, string $id): Project
    {
        $project = Project::query()->where('organization_id', $organization->id)->find($id);
        if ($project === null) {
            throw DomainError::notFound('project');
        }

        return $project;
    }
}
