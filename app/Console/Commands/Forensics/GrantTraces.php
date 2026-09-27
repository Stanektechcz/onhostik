<?php

declare(strict_types=1);

namespace App\Console\Commands\Forensics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\RoleResolver;

/**
 * Grants nothing in the platform should have made, for `onhost:forensics:lookback` (TASK-0038, review round 2): TD-2 (a
 * membership without an accepted invitation, program §1/§9, closed by IF-2), TD-3 (project roles past `mayGrant`,
 * closed by IF-3) and the explanation of an attach that the IF-8 staff self-grant check shares. Read-only: SELECTs.
 */
final class GrantTraces
{
    /** How far an explaining event may lie from the attach it wrote: the same transaction, a slow request at most. */
    private const EXPLAIN_SECONDS = 60;

    /** @var array<string, Collection<int, array{organization_id:string, from:string, to:string, at:CarbonImmutable}>>|null */
    private ?array $transfers = null;

    /** @var array<string, list<array{id:string, kind:string, user:string, role:string, at:CarbonImmutable}>> */
    private array $projectEvents = [];

    /** @param array{0:CarbonImmutable, 1:CarbonImmutable} $window */
    public function __construct(private readonly MembershipHistory $history, private readonly array $window) {}

    /**
     * TD-2: `change_role` took any `user_id`, and `attachMember` → `updateOrCreate` made the membership — the organization
     * then saw the person's e-mail. The trace is an attach row whose target was not a member just before and that no
     * accepted invitation of the same role, the creation of the organization or an ownership transfer explains.
     *
     * @return array{checked:array<string,int>, hits:list<array<string,mixed>>, unknowns:list<string>}
     */
    public function withoutInvitation(): array
    {
        $hits = [];
        $unknown = 0;
        $rows = 0;
        foreach ($this->rows(['organization.member.attach']) as $row) {
            $rows++;
            $detail = self::json($row->detail);
            $user = (string) ($detail['user_id'] ?? '');
            $role = (string) ($detail['role'] ?? '');
            $at = CarbonImmutable::parse($row->created_at);
            if ($row->organization_id === null || $user === '' || $this->explained((string) $row->organization_id, $user, $role, $at)) {
                continue;
            }
            [$before, $afterRemoval] = $this->history->memberBeforeRow((string) $row->organization_id, $user, (string) $row->id, $at);
            if ($before === true) {
                continue; // a role change of a member: TD-5 territory, not an entry
            }
            if ($before === null) {
                $unknown++;

                continue;
            }
            $hits[] = ['kind' => 'member_attached_without_invitation', 'confidence' => $this->renamedAcceptance((string) $row->organization_id, $role, $at) ? 'possible' : 'confirmed',
                'audit_event_id' => $row->id, 'organization_id' => $row->organization_id, 'user_id' => $user, 'role' => $role, 'actor_type' => $row->actor_type, 'actor_id' => $row->actor_id,
                'actor_staff' => $row->actor_id === null ? false : $this->history->isStaff((string) $row->actor_id), 're_entry' => $afterRemoval, 'at' => $at->toIso8601String()];
        }

        return ['checked' => ['audit_events' => $rows, 'organization_memberships' => $this->history->judgedCount()], 'hits' => $hits,
            'unknowns' => $unknown > 0 ? ["{$unknown} attach row(s) in an organization older than the audit name somebody with no recorded past there: whether they were already a member cannot be told."] : []];
    }

    /**
     * TD-3: `add_project_member` never went through `mayGrant` — nothing stopped a person granting a project role to
     * themselves or a role their own did not cover. The organization owner covers every role and may edit their own.
     *
     * @return array{checked:array<string,int>, hits:list<array<string,mixed>>, unknowns:list<string>}
     */
    public function projectGrants(): array
    {
        $hits = [];
        $unknown = 0;
        $rows = 0;
        foreach ($this->rows(['project.member.add']) as $row) {
            $rows++;
            $detail = self::json($row->detail);
            $organizationId = (string) $row->organization_id;
            $actor = (string) $row->actor_id;
            $at = CarbonImmutable::parse($row->created_at);
            if ($row->actor_type !== 'user' || $row->organization_id === null || $row->actor_id === null || $actor === $this->ownerAt($organizationId, $at)) {
                continue;
            }
            $target = (string) ($detail['user_id'] ?? '');
            $staff = $this->history->isStaff($actor);
            [$actorRole, $fromAudit] = $this->history->roleAt($organizationId, $actor, $at);
            $projectRole = $this->projectRoleBefore((string) $row->resource_id, $actor, (string) $row->id);
            // role definitions come through RoleResolver, not the raw catalogue (TASK-0037, program D6/P0-11); grantable() of an
            // unknown role is nothing, exactly as the catalogue's `?? []` answered before
            $held = [...RoleResolver::grantable((string) $actorRole), ...RoleResolver::grantable((string) $projectRole)];
            $missing = array_values(array_diff(RoleResolver::grantable((string) ($detail['role'] ?? '')), $held));
            $self = $target === $actor;
            if (! $self && ($staff || $missing === [])) {
                continue; // staff are not bound by mayGrant by design (their self-grants are); a covered role is an ordinary grant
            }
            if (! $self && $actorRole === null && ! $fromAudit) {
                $unknown++;

                continue;
            }
            $hits[] = ['kind' => $self ? 'project_self_grant' : 'project_grant_above_own', 'confidence' => $self || $fromAudit ? 'confirmed' : 'possible', 'audit_event_id' => $row->id,
                'organization_id' => $organizationId, 'project_id' => $row->resource_id, 'user_id' => $target, 'actor_id' => $actor, 'actor_staff' => $staff, 'role' => (string) ($detail['role'] ?? ''),
                'actor_role' => $actorRole, 'actor_project_role' => $projectRole, 'missing' => array_slice($missing, 0, 5), 'at' => $at->toIso8601String()];
        }

        return ['checked' => ['audit_events' => $rows, 'organization_memberships' => $this->history->judgedCount()], 'hits' => $hits,
            'unknowns' => $unknown > 0 ? ["{$unknown} project grant(s) by somebody whose role at that moment is not recorded and who is no member now: whether their role covered the grant cannot be told."] : []];
    }

    /**
     * Did an event that writes this very attach write it? The CLOSEST explaining event within a minute decides, and only
     * when its role is the attach's role (review round 2: any event in the window explained any role, so a staff user who
     * accepted a viewer invitation could raise themselves to org_admin seconds later unseen). An accepted invitation
     * explains its own role; the creation explains the first owner; a transfer explains `owner` for its `to` and
     * `org_admin` for its `from`.
     */
    public function explained(string $organizationId, string $userId, string $role, CarbonImmutable $at): bool
    {
        $near = [$at->subSeconds(self::EXPLAIN_SECONDS), $at->addSeconds(self::EXPLAIN_SECONDS)];
        $candidates = [];
        $email = $this->history->emailOf($userId);
        if ($email !== null) {
            foreach (DB::table('organization_invitations')->where('organization_id', $organizationId)->whereBetween('accepted_at', $near)->whereRaw('lower(email) = ?', [$email])->get(['role_key', 'accepted_at']) as $invitation) {
                $candidates[] = [CarbonImmutable::parse($invitation->accepted_at), (string) $invitation->role_key];
            }
        }
        foreach (DB::table('audit_events')->where('organization_id', $organizationId)->whereBetween('created_at', $near)->whereIn('action', ['organization.create', 'organization.ownership.transfer'])->get(['action', 'detail', 'created_at']) as $event) {
            $when = CarbonImmutable::parse($event->created_at);
            $detail = self::json($event->detail);
            $explains = match (true) {
                $event->action === 'organization.create' => $this->ownerAt($organizationId, $when) === $userId ? 'owner' : null,
                (string) ($detail['to'] ?? '') === $userId => 'owner',
                (string) ($detail['from'] ?? '') === $userId => 'org_admin',
                default => null,
            };
            if ($explains !== null) {
                $candidates[] = [$when, $explains];
            }
        }
        usort($candidates, fn (array $a, array $b) => abs($a[0]->diffInSeconds($at)) <=> abs($b[0]->diffInSeconds($at)));

        return $candidates !== [] && $candidates[0][1] === $role;
    }

    /** An invitation of that role accepted beside the attach under an address no account carries now: the person may have changed it. */
    private function renamedAcceptance(string $organizationId, string $role, CarbonImmutable $at): bool
    {
        $emails = DB::table('organization_invitations')->where('organization_id', $organizationId)->where('role_key', $role)
            ->whereBetween('accepted_at', [$at->subSeconds(self::EXPLAIN_SECONDS), $at->addSeconds(self::EXPLAIN_SECONDS)])->pluck('email')->map(fn ($e) => mb_strtolower((string) $e))->unique()->values()->all();

        return $emails !== [] && $this->history->addressesWithoutAccount($emails) > 0;
    }

    private function ownerAt(string $organizationId, CarbonImmutable $at): string
    {
        $this->transfers ??= DB::table('audit_events')->where('action', 'organization.ownership.transfer')->orderBy('created_at')->orderBy('id')->get(['organization_id', 'detail', 'created_at'])
            ->map(fn ($t) => ['organization_id' => (string) $t->organization_id, 'from' => (string) (self::json($t->detail)['from'] ?? ''), 'to' => (string) (self::json($t->detail)['to'] ?? ''), 'at' => CarbonImmutable::parse($t->created_at)])
            ->groupBy('organization_id')->all();

        return $this->history->ownerAt(DB::table('organizations')->where('id', $organizationId)->first(['owner_user_id']), $this->transfers[$organizationId] ?? collect(), $at);
    }

    /** The project role the person held in that project just before the row (project.member.add / .remove rows). */
    private function projectRoleBefore(string $projectId, string $userId, string $eventId): ?string
    {
        $this->projectEvents[$projectId] ??= DB::table('audit_events')->where('resource_type', 'project')->where('resource_id', $projectId)
            ->whereIn('action', ['project.member.add', 'project.member.remove'])->orderBy('created_at')->orderBy('id')->get(['id', 'action', 'detail', 'created_at'])
            ->map(fn ($r) => ['id' => (string) $r->id, 'kind' => $r->action === 'project.member.remove' ? 'remove' : 'add', 'user' => (string) (self::json($r->detail)['user_id'] ?? ''),
                'role' => (string) (self::json($r->detail)['role'] ?? ''), 'at' => CarbonImmutable::parse($r->created_at)])->all();
        $role = null;
        foreach ($this->projectEvents[$projectId] as $event) {
            if ($event['id'] === $eventId) {
                break;
            }
            if ($event['user'] === $userId) {
                $role = $event['kind'] === 'add' ? $event['role'] : null;
            }
        }

        return $role;
    }

    /** @param list<string> $actions */
    private function rows(array $actions): iterable
    {
        return DB::table('audit_events')->whereIn('action', $actions)->whereBetween('created_at', $this->window)->lazyById(1000, 'id');
    }

    /** @return array<string,mixed> */
    private static function json(mixed $value): array
    {
        $decoded = is_array($value) ? $value : (is_string($value) ? json_decode($value, true) : null);

        return is_array($decoded) ? $decoded : [];
    }
}
