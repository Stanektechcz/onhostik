<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Access;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Notifications\NotificationService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationInvitation;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\Models\ProjectMembership;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceAccessGrant;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * Handing ONE service to another person to look after — the customer's freelancer, agency or colleague — with named
 * capabilities and, if wanted, until a date.
 *
 * What the person may do is carried by resource-scoped policy bindings (one per capability), so every endpoint, every
 * long-running operation and the assistant ask the same authorizer they ask for everybody else: nothing about a shared
 * service is a special case in the code that acts on it. Somebody who is not in the organization yet is invited as a
 * `guest` — a member who sees nothing of the organization by that membership: no invoices, no team, no other service.
 *
 * What is never handed out here: cancelling the service, anything about money, members or domains.
 */
final class ServiceAccessService
{
    /** capability the owner ticks => the role its binding carries */
    public const CAPABILITIES = [
        'view' => 'svc_view', 'manage' => 'svc_manage', 'console' => 'svc_console', 'backups' => 'svc_backups', 'restore' => 'svc_restore', 'assistant' => 'svc_assistant',
    ];

    public const MAX_PER_SERVICE = 25;

    /** Invitations one organization may send through sharing in 24 hours: each of them is a mail to an address the customer typed. */
    public const MAX_INVITATIONS_PER_DAY = 30;

    public function __construct(
        private readonly OrganizationService $organizations,
        private readonly Authorizer $authorizer,
        private readonly NotificationService $notifications,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    /**
     * @param  list<string>  $capabilities
     */
    public function share(Organization $organization, Service $service, string $email, array $capabilities, CommandContext $context, ?CarbonInterface $until = null, ?string $note = null): ServiceAccessGrant
    {
        $this->assertShareable($organization, $service);
        $email = mb_strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainError('email_invalid', 'Enter the e-mail address of the person the service is shared with.', 422, ['field' => 'email']);
        }
        $capabilities = $this->normalize($capabilities);
        if ($until !== null && $until->isPast()) {
            throw new DomainError('access_until_past', 'access_until must be in the future.', 422, ['field' => 'access_until']);
        }
        $actor = $context->actorType === 'user' && $context->actorId !== null ? User::query()->find($context->actorId) : null;
        if ($actor !== null && mb_strtolower((string) $actor->email) === $email) {
            throw new DomainError('cannot_share_with_self', 'You already manage this service.', 422, ['field' => 'email']);
        }
        $this->assertMayGrant($actor, $service, $capabilities, $context);

        $user = User::query()->where('email', $email)->first();
        $scope = CommandScope::resource($service->id, $service->organization_id, $service->project_id);
        if ($user !== null && $this->covers($user, $capabilities, $scope) && ! $this->openGrant($service, $email)) {
            throw new DomainError('already_has_access', 'This person already has all of that on this service through their role.', 409, ['field' => 'email']);
        }
        if (! $this->openGrant($service, $email) && ServiceAccessGrant::query()->where('service_id', $service->id)->whereIn('state', [ServiceAccessGrant::PENDING, ServiceAccessGrant::ACTIVE])->count() >= self::MAX_PER_SERVICE) {
            throw new DomainError('access_grants_limit', 'A service can be shared with at most '.self::MAX_PER_SERVICE.' people.', 422);
        }
        $member = $user !== null && OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->current()->exists();

        return DB::transaction(function () use ($organization, $service, $email, $capabilities, $context, $until, $note, $user, $member) {
            $grant = $this->openGrant($service, $email) ?? new ServiceAccessGrant(['organization_id' => $organization->id, 'service_id' => $service->id, 'email' => $email]);
            $grant->forceFill(['capabilities' => $capabilities, 'expires_at' => $until, 'note' => $note !== null ? mb_substr(trim($note), 0, 250) : $grant->note, 'granted_by' => $context->actorId]);
            $vars = ['sluzba' => $this->serviceName($service), 'organizace' => (string) $organization->name, 'opravneni' => $this->describe($capabilities, (string) ($organization->locale ?? 'cs')), 'do' => $until?->format('j. n. Y') ?? '—'];
            if ($member && $user !== null) {
                $grant->forceFill(['state' => ServiceAccessGrant::ACTIVE, 'user_id' => $user->id, 'accepted_at' => $grant->accepted_at ?? now()])->save();
                $this->bind($grant, $user);
                $this->notifications->queueMail('service-shared', $email, $vars + ['url' => rtrim((string) config('onhost.portal_url'), '/').'/panel/sluzby'], 'service_access_grant', $grant->id, $organization->id, $user->locale ?? ($organization->locale ?? 'cs'));
            } else {
                // not in the organization yet: they join as a guest through the ordinary invitation — the link proves the mailbox
                // The link that was already sent still works: what the person may do is updated and no second mail goes out. Sharing
                // again and again with one address was a way to fill somebody's mailbox from our domain — and no ceiling counted it.
                $standing = $grant->exists && $grant->invitation_id !== null ? OrganizationInvitation::query()->where('organization_id', $organization->id)->find((string) $grant->invitation_id) : null;
                if ($standing !== null && $standing->isUsable()) {
                    $grant->forceFill(['state' => ServiceAccessGrant::PENDING])->save();
                } else {
                    $this->assertInvitationBudget($organization);
                    $invited = $this->organizations->invite($organization, $email, 'guest', $context, null, 'service-shared', $vars);
                    $grant->forceFill(['state' => ServiceAccessGrant::PENDING, 'invitation_id' => $invited['invitation']->id])->save();
                }
            }
            $this->audit->record($context->withScope($organization->id), 'service.access.grant', 'succeeded', ['email' => $email, 'capabilities' => $capabilities, 'access_until' => $until?->toIso8601String(), 'state' => $grant->state], 'service', $service->id);
            $this->outbox->publish(GenericEvent::of('service.access.granted', 'service', $service->id, ['grant_id' => $grant->id, 'email' => $email, 'capabilities' => $capabilities, 'state' => $grant->state, 'expires_at' => $until?->toIso8601String(), 'service' => $this->serviceName($service)], $organization->id));

            return $grant;
        }, 3);
    }

    /** The invitation was accepted: whatever was waiting for this address in this organization becomes real. */
    public function activatePending(User $user, Organization $organization): int
    {
        $activated = 0;
        $pending = ServiceAccessGrant::query()->where('organization_id', $organization->id)->where('email', mb_strtolower((string) $user->email))->where('state', ServiceAccessGrant::PENDING)->get();
        foreach ($pending as $grant) {
            if ($grant->expires_at !== null && $grant->expires_at->isPast()) {
                $grant->forceFill(['state' => ServiceAccessGrant::EXPIRED])->save();

                continue;
            }
            $grant->forceFill(['state' => ServiceAccessGrant::ACTIVE, 'user_id' => $user->id, 'accepted_at' => now()])->save();
            $this->bind($grant, $user);
            $activated++;
        }

        return $activated;
    }

    public function revoke(Organization $organization, Service $service, string $grantId, CommandContext $context, string $state = ServiceAccessGrant::REVOKED): ServiceAccessGrant
    {
        $grant = ServiceAccessGrant::query()->where('organization_id', $organization->id)->where('service_id', $service->id)->find($grantId);
        if ($grant === null) {
            throw DomainError::notFound('service_access_grant');
        }
        if (! in_array($grant->state, [ServiceAccessGrant::PENDING, ServiceAccessGrant::ACTIVE], true)) {
            return $grant;
        }

        return DB::transaction(function () use ($organization, $service, $grant, $context, $state) {
            $user = $grant->user_id !== null ? User::query()->find($grant->user_id) : null;
            if ($user !== null) {
                $this->unbind($user, $service->id);
            }
            if ($grant->state === ServiceAccessGrant::PENDING && $grant->invitation_id !== null) {
                $this->cancelInvitation($organization, (string) $grant->invitation_id, $context);
            }
            $grant->forceFill(['state' => $state, 'revoked_at' => now(), 'revoked_by' => $state === ServiceAccessGrant::REVOKED ? $context->actorId : null])->save();
            $this->audit->record($context->withScope($organization->id), $state === ServiceAccessGrant::EXPIRED ? 'service.access.expire' : 'service.access.revoke', 'succeeded', ['email' => $grant->email], 'service', $service->id);
            // what the person put on the panel under their own name (SSH keys, game collaborator accounts) goes with the access (H333)
            $this->outbox->publish(GenericEvent::of($state === ServiceAccessGrant::EXPIRED ? 'service.access.expired' : 'service.access.revoked', 'service', $service->id, [
                'grant_id' => $grant->id, 'user_id' => $grant->user_id, 'email' => $grant->email, 'organization_id' => $organization->id, 'service' => $this->serviceName($service),
            ], $organization->id));
            if ($user !== null) {
                $this->releaseGuest($organization, $user, $context);
            }

            return $grant;
        }, 3);
    }

    /** Accesses whose date has come. The bindings stopped working at that second by themselves; this closes the record. */
    public function expire(int $limit = 500): int
    {
        $closed = 0;
        $due = ServiceAccessGrant::query()->whereIn('state', [ServiceAccessGrant::PENDING, ServiceAccessGrant::ACTIVE])->whereNotNull('expires_at')->where('expires_at', '<=', now())->limit($limit)->get();
        foreach ($due as $grant) {
            try {
                $organization = Organization::query()->find($grant->organization_id);
                $service = Service::query()->find($grant->service_id);
                if ($organization === null || $service === null) {
                    $grant->forceFill(['state' => ServiceAccessGrant::EXPIRED])->save();

                    continue;
                }
                $this->revoke($organization, $service, $grant->id, CommandContext::system('service access expired'), ServiceAccessGrant::EXPIRED);
                $closed++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $closed;
    }

    /** The person left the organization (or was removed): their bindings are gone with the membership, the records close. */
    public function closeForMember(Organization $organization, string $userId, ?string $email): int
    {
        return ServiceAccessGrant::query()->where('organization_id', $organization->id)->whereIn('state', [ServiceAccessGrant::PENDING, ServiceAccessGrant::ACTIVE])
            ->where(fn ($q) => $q->where('user_id', $userId)->when($email !== null && $email !== '', fn ($q) => $q->orWhere('email', mb_strtolower((string) $email))))
            ->update(['state' => ServiceAccessGrant::REVOKED, 'revoked_at' => now()]);
    }

    /** @return list<array<string,mixed>> what the owner sees on the service */
    public function forService(Service $service): array
    {
        $grants = ServiceAccessGrant::query()->where('service_id', $service->id)->orderByRaw("case state when 'active' then 0 when 'pending' then 1 else 2 end")->orderByDesc('created_at')->limit(100)->get();
        $users = User::query()->whereIn('id', $grants->pluck('user_id')->merge($grants->pluck('granted_by'))->filter()->unique()->all())->get()->keyBy('id');

        return $grants->map(fn (ServiceAccessGrant $g) => $this->present($g) + ['name' => $g->user_id !== null ? $users->get($g->user_id)?->name : null, 'granted_by' => $g->granted_by !== null ? $users->get($g->granted_by)?->name : null])->values()->all();
    }

    /** @return list<array<string,mixed>> what was shared with this person, across organizations */
    public function sharedWith(User $user): array
    {
        $grants = ServiceAccessGrant::query()->where('user_id', $user->id)->where('state', ServiceAccessGrant::ACTIVE)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->get();
        $services = Service::query()->whereIn('id', $grants->pluck('service_id')->all())->whereNotIn('state', [ServiceStateMachine::TERMINATED])->get()->keyBy('id');
        $organizations = Organization::query()->whereIn('id', $grants->pluck('organization_id')->unique()->all())->get()->keyBy('id');

        return $grants->filter(fn (ServiceAccessGrant $g) => $services->has($g->service_id))->map(fn (ServiceAccessGrant $g) => $this->present($g) + [
            'service' => ['id' => $g->service_id, 'name' => $this->serviceName($services[$g->service_id]), 'family' => $services[$g->service_id]->family, 'state' => $services[$g->service_id]->state],
            'organization' => ['id' => $g->organization_id, 'name' => $organizations->get($g->organization_id)?->name],
        ])->values()->all();
    }

    /** @return list<array{key:string, role:string, permissions:list<string>}> */
    public static function catalogue(): array
    {
        $roles = RoleCatalog::all();

        return array_map(fn (string $key, string $role) => ['key' => $key, 'role' => $role, 'permissions' => $roles[$role]['permissions'] ?? []], array_keys(self::CAPABILITIES), self::CAPABILITIES);
    }

    /** @return array<string,mixed> */
    public function present(ServiceAccessGrant $grant): array
    {
        $state = $grant->state;
        if (in_array($state, [ServiceAccessGrant::PENDING, ServiceAccessGrant::ACTIVE], true) && $grant->expires_at !== null && $grant->expires_at->isPast()) {
            $state = ServiceAccessGrant::EXPIRED; // the sweep closes the record within minutes; the answer is right at once
        }

        return [
            'id' => $grant->id, 'service_id' => $grant->service_id, 'email' => $grant->email, 'user_id' => $grant->user_id, 'capabilities' => array_values((array) $grant->capabilities), 'state' => $state, 'note' => $grant->note,
            'expires_at' => $grant->expires_at?->toIso8601String(), 'accepted_at' => $grant->accepted_at?->toIso8601String(), 'revoked_at' => $grant->revoked_at?->toIso8601String(), 'created_at' => $grant->created_at?->toIso8601String(),
        ];
    }

    // ── internals ─────────────────────────────────────────────────────────────────────────────────

    private function assertShareable(Organization $organization, Service $service): void
    {
        if ($service->organization_id !== $organization->id) {
            throw DomainError::notFound('service');
        }
        if (in_array($service->state, [ServiceStateMachine::TERMINATED, ServiceStateMachine::TERMINATING], true)) {
            throw new DomainError('service_state_invalid', 'A service that is being cancelled cannot be shared.', 409);
        }
    }

    /**
     * @param  list<string>  $capabilities
     * @return list<string>
     */
    private function normalize(array $capabilities): array
    {
        $wanted = array_values(array_unique(array_map(fn ($c) => strtolower(trim((string) $c)), $capabilities)));
        $unknown = array_diff($wanted, array_keys(self::CAPABILITIES));
        if ($wanted === [] || $unknown !== []) {
            throw new DomainError('capabilities_invalid', 'Choose what the person may do: '.implode(', ', array_keys(self::CAPABILITIES)).'.', 422, ['field' => 'capabilities', 'unknown' => array_values($unknown)]);
        }
        $wanted[] = 'view'; // whoever may do something on a service sees it
        if (in_array('console', $wanted, true)) {
            $wanted[] = 'manage'; // a shell on the service is more than managing it, never less (H334): the owner sees both ticked
        }

        return array_values(array_intersect(array_keys(self::CAPABILITIES), array_unique($wanted))); // catalogue order
    }

    /**
     * Nobody hands out what they do not hold themselves on that service (the rule of the team page, asked at the service).
     *
     * @param  list<string>  $capabilities
     */
    private function assertMayGrant(?User $actor, Service $service, array $capabilities, CommandContext $context): void
    {
        if ($actor === null || $actor->is_staff) {
            return; // the platform and its staff act under their own permissions (checked by the command)
        }
        $scope = CommandScope::resource($service->id, $service->organization_id, $service->project_id);
        foreach ($this->permissionsOf($capabilities) as $permission) {
            if (! $this->authorizer->can($actor, $permission, $scope)) {
                throw new DomainError('capability_above_own', "You cannot hand out {$permission}: you do not hold it on this service yourself.", 403, ['field' => 'capabilities']);
            }
        }
    }

    /** @param list<string> $capabilities */
    private function covers(User $user, array $capabilities, CommandScope $scope): bool
    {
        foreach ($this->permissionsOf($capabilities) as $permission) {
            if (! $this->authorizer->can($user, $permission, $scope)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $capabilities
     * @return list<string>
     */
    private function permissionsOf(array $capabilities): array
    {
        $roles = RoleCatalog::all();
        $permissions = [];
        foreach ($capabilities as $capability) {
            $permissions = array_merge($permissions, $roles[self::CAPABILITIES[$capability]]['permissions'] ?? []);
        }

        return array_values(array_unique($permissions));
    }

    /** Every guest invitation is a mail to an address the customer typed: an organization sends only so many of them a day. */
    private function assertInvitationBudget(Organization $organization): void
    {
        $sent = OrganizationInvitation::query()->where('organization_id', $organization->id)->where('role_key', 'guest')->where('created_at', '>', now()->subDay())->count();
        if ($sent >= self::MAX_INVITATIONS_PER_DAY) {
            throw new DomainError('share_invitations_limit', 'Too many invitations were sent from this organization today; try again tomorrow or contact support.', 429);
        }
    }

    private function openGrant(Service $service, string $email): ?ServiceAccessGrant
    {
        return ServiceAccessGrant::query()->where('service_id', $service->id)->where('email', $email)->whereIn('state', [ServiceAccessGrant::PENDING, ServiceAccessGrant::ACTIVE])->first();
    }

    private function bind(ServiceAccessGrant $grant, User $user): void
    {
        $this->unbind($user, $grant->service_id);
        foreach ((array) $grant->capabilities as $capability) {
            PolicyBinding::query()->create([
                'principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => self::CAPABILITIES[$capability], 'scope_type' => 'resource', 'scope_id' => $grant->service_id,
                'organization_id' => $grant->organization_id, 'granted_by' => $grant->granted_by, 'expires_at' => $grant->expires_at,
            ]);
        }
        $this->authorizer->forget($user);
    }

    private function unbind(User $user, string $serviceId): void
    {
        PolicyBinding::query()->where('principal_type', 'user')->where('principal_id', $user->id)->where('scope_type', 'resource')->where('scope_id', $serviceId)->whereIn('role_key', array_values(self::CAPABILITIES))->delete();
        $this->authorizer->forget($user);
    }

    private function cancelInvitation(Organization $organization, string $invitationId, CommandContext $context): void
    {
        $invitation = OrganizationInvitation::query()->where('organization_id', $organization->id)->find($invitationId);
        if ($invitation !== null && $invitation->isUsable()) {
            $this->organizations->cancelInvitation($organization, $invitation->id, $context);
        }
    }

    /** A guest with nothing shared any more has no reason to stay a member. */
    private function releaseGuest(Organization $organization, User $user, CommandContext $context): void
    {
        $membership = OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->first();
        if ($membership === null || $membership->role_key !== 'guest') {
            return;
        }
        $open = ServiceAccessGrant::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->where('state', ServiceAccessGrant::ACTIVE)->exists();
        $projects = ProjectMembership::query()->where('user_id', $user->id)->whereIn('project_id', DB::table('projects')->where('organization_id', $organization->id)->select('id'))->exists();
        if (! $open && ! $projects) {
            $this->organizations->removeMember($organization, $user, $context);
        }
    }

    /** @param list<string> $capabilities */
    private function describe(array $capabilities, string $locale): string
    {
        $labels = $locale === 'en'
            ? ['view' => 'view', 'manage' => 'manage and configure', 'console' => 'console and terminal', 'backups' => 'download backups', 'restore' => 'restore from a backup', 'assistant' => 'AI assistant']
            : ['view' => 'zobrazení', 'manage' => 'správa a nastavení', 'console' => 'konzole a terminál', 'backups' => 'stahování záloh', 'restore' => 'obnova ze zálohy', 'assistant' => 'AI asistent'];

        return implode(', ', array_map(fn (string $c) => $labels[$c] ?? $c, $capabilities));
    }

    private function serviceName(Service $service): string
    {
        return (string) ($service->label ?: ($service->hostname ?: $service->name));
    }
}
