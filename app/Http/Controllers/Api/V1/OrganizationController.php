<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Presenters\Presenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Notifications\DigestService;
use Onhost\Domain\Organizations\AccessSnapshots;
use Onhost\Domain\Organizations\Commands\AcceptInvitationCommand;
use Onhost\Domain\Organizations\Commands\CreateOrganizationCommand;
use Onhost\Domain\Organizations\Commands\OrganizationCommand;
use Onhost\Domain\Organizations\Commands\OwnershipCommand;
use Onhost\Domain\Organizations\Models\AccessSnapshot;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationInvitation;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Organizations\OwnerRecoveries;
use Onhost\Domain\Organizations\OwnershipTransfers;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

final class OrganizationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->api->user($request);
        $memberships = OrganizationMembership::query()->with('organization')->where('user_id', $user->id)->current()->get();

        return response()->json(['data' => $memberships->map(fn ($m) => $m->organization ? Presenters::organization($m->organization, $m->role_key) : null)->filter()->values()->all()]);
    }

    /** A customer profile for the signed-in user (audit §5z): the order centre creates it before the first order. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:190'], 'type' => ['required', 'in:person,company'], 'ico' => ['nullable', 'required_if:type,company', 'string', 'max:20'], 'dic' => ['nullable', 'string', 'max:20'], 'vat_id' => ['nullable', 'string', 'max:20'],
            'billing_email' => ['nullable', 'email'], 'street' => ['required', 'string', 'max:190'], 'city' => ['required', 'string', 'max:120'], 'postal_code' => ['required', 'string', 'max:12'], 'country' => ['nullable', 'string', 'size:2'],
        ]);

        return $this->dispatch(new CreateOrganizationCommand($this->idempotencyKey($request, 'org.create:'.(string) $request->user()?->getAuthIdentifier()), $data), $this->api->context($request), 201);
    }

    public function show(Request $request, string $organization): JsonResponse
    {
        $org = $this->resolve($request, $organization, 'organization.read');
        $members = OrganizationMembership::query()->with('user')->where('organization_id', $org->id)->get()->map(fn ($m) => ['user_id' => $m->user_id, 'email' => $m->user?->email, 'name' => $m->user?->name, 'role' => $m->role_key, 'state' => $m->state, 'joined_at' => $m->joined_at?->toIso8601String(), 'access_until' => $m->expires_at?->toIso8601String()])->all();
        $projects = Project::query()->where('organization_id', $org->id)->orderBy('name')->get()->map(fn ($p) => Presenters::project($p))->all();
        // pending invitations (not accepted, not expired) — the team page lists and cancels them; the accept token never leaves the mail
        $invitations = OrganizationInvitation::query()->where('organization_id', $org->id)->whereNull('accepted_at')->where('expires_at', '>', now())->orderBy('created_at')->get()
            ->map(fn ($i) => ['id' => $i->id, 'email' => $i->email, 'role' => $i->role_key, 'expires_at' => $i->expires_at?->toIso8601String(), 'access_until' => $i->access_expires_at?->toIso8601String(), 'created_at' => $i->created_at?->toIso8601String()])->all();

        // TASK-0042: an ownership offer waiting for its heir and an owner recovery in its notice period are shown to every member
        $transfer = app(OwnershipTransfers::class)->current($org);
        $recovery = OwnerRecoveries::pending($org);

        return response()->json(['data' => Presenters::organization($org) + ['members' => $members, 'projects' => $projects, 'invitations' => $invitations,
            'ownership_transfer' => $transfer === null ? null : OwnershipTransfers::present($transfer), 'owner_recovery' => $recovery === null ? null : OwnerRecoveries::present($recovery)]]);
    }

    public function cancelInvitation(Request $request, string $organization, string $invitation): JsonResponse
    {
        $org = $this->resolve($request, $organization, 'organization.members.manage');

        return $this->dispatch(new OrganizationCommand($org->id, "org.invitation.cancel:{$invitation}", ['op' => 'cancel_invitation', 'invitation_id' => $invitation]), $this->api->context($request, $org));
    }

    public function update(Request $request, string $organization): JsonResponse
    {
        $org = $this->resolve($request, $organization, 'organization.manage');
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:190'], 'billing_email' => ['sometimes', 'email'], 'currency' => ['sometimes', 'string', 'size:3'], 'status_page' => ['sometimes', 'array'], 'status_page.enabled' => ['sometimes', 'boolean'], 'status_page.title' => ['sometimes', 'nullable', 'string', 'max:80'], 'status_page.show_monitors' => ['sometimes', 'boolean'], 'status_page.show_incidents' => ['sometimes', 'boolean'], 'status_page.domain' => ['sometimes', 'nullable', 'string', 'max:253'], 'street' => ['sometimes', 'nullable', 'string', 'max:190'], 'city' => ['sometimes', 'nullable', 'string', 'max:120'], 'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'], 'country' => ['sometimes', 'string', 'size:2'], 'ico' => ['sometimes', 'nullable', 'string', 'max:20'], 'dic' => ['sometimes', 'nullable', 'string', 'max:20'], 'vat_id' => ['sometimes', 'nullable', 'string', 'max:20'], 'locale' => ['sometimes', 'in:cs,sk,en'], 'auto_renew_default' => ['sometimes', 'boolean'], 'digest_frequency' => ['sometimes', 'in:weekly,monthly,off']]);

        return $this->dispatch(new OrganizationCommand($org->id, $this->idempotencyKey($request, 'org.update'), ['op' => 'update'] + $data), $this->api->context($request, $org));
    }

    /** The digest as it would go out now, with the organization's frequency (audit §5f-5). */
    public function digest(Request $request, DigestService $digests, string $organization): JsonResponse
    {
        $org = $this->resolve($request, $organization, 'organization.read');

        return response()->json(['data' => ['frequency' => DigestService::frequency($org), 'preview' => $digests->customerWeekly($org)]]);
    }

    public function invite(Request $request, string $organization): JsonResponse
    {
        $org = $this->resolve($request, $organization, 'organization.members.manage');
        $data = $request->validate(['email' => ['required', 'email'], 'role' => ['required', 'string', 'max:40'], 'access_until' => ['nullable', 'date', 'after:now']]); // access_until: the membership ends on that date (H343)

        return $this->dispatch(new OrganizationCommand($org->id, $this->idempotencyKey($request, 'org.invite'), ['op' => 'invite'] + $data), $this->api->context($request, $org), 201);
    }

    /**
     * TASK-0042 (permission program I8, S1-02; audit TD-7): the link is accepted through the bus — audited as the person who
     * clicked, the sender re-checked at that moment, the services shared with the address activated as far as their sharer still
     * backs them. The answer is the one it always was.
     */
    public function acceptInvitation(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:200']]);
        $this->api->user($request);
        // a key of its own per click: accepting is idempotent by the link itself (it is spent once), and a replayed "accepted" for a
        // link that is dead since — the person was removed meanwhile — would say something that is no longer true
        $result = $this->bus->dispatch(new AcceptInvitationCommand('org.invite.accept:'.Str::ulid(), ['token' => $data['token']]), $this->api->context($request));

        return response()->json(['data' => $result]);
    }

    /** The access snapshots of the organization's people (I10): what was taken away in the last 90 days and how to give it back. */
    public function accessSnapshots(Request $request, AccessSnapshots $snapshots, string $organization): JsonResponse
    {
        $org = $this->resolve($request, $organization, 'organization.members.manage');
        $rows = AccessSnapshot::query()->where('organization_id', $org->id)->where('expires_at', '>', now())->orderByDesc('created_at')->limit(200)->get();
        $users = User::query()->whereIn('id', $rows->pluck('user_id')->unique()->all())->get()->keyBy('id');

        return response()->json(['data' => $rows->map(fn (AccessSnapshot $s) => $snapshots->present($s, $users->get($s->user_id)))->values()->all()]);
    }

    /** Gives a person back exactly what a snapshot recorded (GrantPolicy::assertMayRestore; HIGH). */
    public function restoreAccess(Request $request, string $organization): JsonResponse
    {
        $org = $this->resolve($request, $organization, 'organization.members.manage');
        $data = $request->validate(['snapshot_id' => ['required', 'string', 'max:40']]);

        return $this->dispatch(new OrganizationCommand($org->id, $this->idempotencyKey($request, 'org.access.restore:'.$data['snapshot_id']), ['op' => 'restore_access', 'snapshot_id' => $data['snapshot_id']]), $this->api->context($request, $org));
    }

    /** The owner offers the ownership to a current member (I4, TD-9); nothing moves until the member accepts. */
    public function offerOwnership(Request $request, string $organization): JsonResponse
    {
        $org = $this->resolve($request, $organization, 'organization.close');
        $data = $request->validate(['user_id' => ['required', 'string', 'max:40']]);

        return $this->dispatch(new OwnershipCommand($org->id, $this->idempotencyKey($request, 'org.ownership.offer'), ['op' => 'offer', 'user_id' => $data['user_id']]), $this->api->context($request, $org), 201);
    }

    public function acceptOwnership(Request $request, string $organization): JsonResponse
    {
        return $this->ownership($request, $organization, 'accept', 'organization.read');
    }

    public function declineOwnership(Request $request, string $organization): JsonResponse
    {
        return $this->ownership($request, $organization, 'decline', 'organization.read');
    }

    public function cancelOwnership(Request $request, string $organization): JsonResponse
    {
        return $this->ownership($request, $organization, 'cancel', 'organization.close');
    }

    /** Any member manager (an org_admin, the owner) stops an owner recovery during its notice period (D21). */
    public function cancelOwnerRecovery(Request $request, string $organization): JsonResponse
    {
        $org = $this->resolve($request, $organization, 'organization.members.manage');
        // TASK-0044: anybody but the person recovered says why (OwnerRecoveries::cancel decides who must)
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        return $this->dispatch(new OrganizationCommand($org->id, $this->onceKey($request, 'org.owner_recovery.cancel'), ['op' => 'cancel_owner_recovery', 'reason' => (string) ($data['reason'] ?? '')]), $this->api->context($request, $org));
    }

    private function ownership(Request $request, string $organization, string $op, string $permission): JsonResponse
    {
        $org = $this->resolve($request, $organization, $permission);

        return $this->dispatch(new OwnershipCommand($org->id, $this->onceKey($request, 'org.ownership.'.$op), ['op' => $op]), $this->api->context($request, $org));
    }

    public function changeRole(Request $request, string $organization, string $user): JsonResponse
    {
        $org = $this->resolve($request, $organization, 'organization.members.manage');
        $data = $request->validate(['role' => ['required', 'string', 'max:40'], 'access_until' => ['sometimes', 'nullable', 'date', 'after:now']]); // absent = keep the end the member has; null = no end

        return $this->dispatch(new OrganizationCommand($org->id, $this->idempotencyKey($request, 'org.role'), ['op' => 'change_role', 'user_id' => $user] + $data), $this->api->context($request, $org));
    }

    public function removeMember(Request $request, string $organization, string $user): JsonResponse
    {
        $org = $this->resolve($request, $organization, 'organization.members.manage');

        return $this->dispatch(new OrganizationCommand($org->id, $this->idempotencyKey($request, 'org.remove'), ['op' => 'remove_member', 'user_id' => $user]), $this->api->context($request, $org));
    }

    public function createProject(Request $request, string $organization): JsonResponse
    {
        $org = $this->resolve($request, $organization, 'project.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'key' => ['nullable', 'string', 'max:40'], 'description' => ['nullable', 'string', 'max:500']]);

        return $this->dispatch(new OrganizationCommand($org->id, $this->idempotencyKey($request, 'org.project'), ['op' => 'create_project'] + $data), $this->api->context($request, $org), 201);
    }

    public function audit(Request $request, string $organization): JsonResponse
    {
        $org = $this->resolve($request, $organization, 'audit.read');
        $query = AuditEvent::query()->where('organization_id', $org->id);
        if ($request->filled('action')) {
            $query->where('action', 'like', (string) $request->query('action').'%');
        }

        return $this->api->paginate($request, $query, fn (AuditEvent $e) => ['id' => $e->id, 'at' => $e->created_at?->toIso8601String(), 'actor' => ['type' => $e->actor_type, 'id' => $e->actor_id], 'action' => $e->action, 'result' => $e->result, 'resource' => [$e->resource_type, $e->resource_id], 'detail' => $e->detail, 'reason' => $e->reason, 'ip' => $e->ip, 'step_up' => $e->step_up_method, 'hash' => $e->hash]);
    }

    private function resolve(Request $request, string $id, string $permission): Organization
    {
        $org = Organization::query()->find($id);
        if ($org === null) {
            throw DomainError::notFound('organization');
        }
        $this->api->authorize($request, $permission, CommandScope::organization($org->id));

        return $org;
    }
}
