<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Incidents\OrganizationStatusService;
use Onhost\Domain\Notifications\NotificationService;
use Onhost\Domain\Orders\CreditOrderPolicy;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationInvitation;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Tax\Jobs\CheckVatNumber;
use Onhost\Domain\Tax\VatNumber;
use Onhost\Domain\Tax\VatStanding;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Organization lifecycle: creation with owner binding, memberships, invitations,
 * projects. Membership and policy binding are always written together so the
 * authorization model can never disagree with the membership list.
 */
final class OrganizationService
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    /** @param array<string,mixed> $attributes */
    public function create(User $owner, array $attributes, CommandContext $context): Organization
    {
        return DB::transaction(function () use ($owner, $attributes, $context) {
            $name = trim((string) ($attributes['name'] ?? $owner->name));
            $organization = Organization::query()->create([
                'slug' => $this->uniqueSlug($name),
                'name' => $name,
                'type' => in_array($attributes['type'] ?? 'person', ['person', 'company'], true) ? $attributes['type'] : 'person',
                'owner_user_id' => $owner->id,
                'ico' => $attributes['ico'] ?? null,
                'dic' => $attributes['dic'] ?? null,
                'vat_id' => $attributes['vat_id'] ?? null,
                'billing_email' => $attributes['billing_email'] ?? $owner->email,
                'street' => $attributes['street'] ?? null,
                'city' => $attributes['city'] ?? null,
                'postal_code' => $attributes['postal_code'] ?? null,
                'country' => strtoupper((string) ($attributes['country'] ?? 'CZ')),
                'currency' => strtoupper((string) ($attributes['currency'] ?? (($attributes['country'] ?? 'CZ') === 'SK' ? 'EUR' : 'CZK'))),
                'locale' => $attributes['locale'] ?? $owner->locale,
                'customer_class' => ! empty($attributes['ico']) || ! empty($attributes['vat_id']) ? 'b2b' : 'b2c',
                'partner_organization_id' => $attributes['partner_organization_id'] ?? null,
                'settings' => [],
            ]);
            $this->attachMember($organization, $owner, 'owner', $context, joinedNow: true);
            Project::query()->create(['organization_id' => $organization->id, 'slug' => 'default', 'name' => 'Default']);

            $this->audit->record($context->withScope($organization->id), 'organization.create', 'succeeded', ['name' => $name], 'organization', $organization->id);
            $this->outbox->publish(GenericEvent::of('organization.created', 'organization', $organization->id, ['name' => $name], $organization->id));
            $this->queueVatCheck($organization); // TASK-0031: the number given at registration or in the guest checkout is checked in VIES

            return $organization;
        });
    }

    /**
     * `$accessUntil` makes the membership end on a date (H343). The policy binding carries the same moment, so the
     * permission stops at that second without any job; `AccessExpiry` removes the membership afterwards, which takes the
     * person's panel accounts and SSH keys with it. The owner's access never expires.
     */
    public function attachMember(Organization $organization, User $user, string $roleKey, CommandContext $context, bool $joinedNow = false, ?CarbonInterface $accessUntil = null): OrganizationMembership
    {
        $this->assertCustomerRole($roleKey);
        if ($accessUntil !== null && ($roleKey === 'owner' || $organization->owner_user_id === $user->id)) {
            throw new DomainError('owner_access_cannot_expire', 'The owner of the organization cannot have access that ends on a date.', 422, ['field' => 'access_until']);
        }
        if ($accessUntil !== null && $accessUntil->isPast()) {
            throw new DomainError('access_until_past', 'access_until must be in the future.', 422, ['field' => 'access_until']);
        }

        return DB::transaction(function () use ($organization, $user, $roleKey, $context, $joinedNow, $accessUntil) {
            $membership = OrganizationMembership::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'user_id' => $user->id],
                ['role_key' => $roleKey, 'state' => 'active', 'joined_at' => $joinedNow ? now() : null, 'invited_by' => $context->actorId, 'expires_at' => $accessUntil],
            );
            PolicyBinding::query()->where('principal_type', 'user')->where('principal_id', $user->id)
                ->where('scope_type', 'organization')->where('scope_id', $organization->id)->delete();
            PolicyBinding::query()->create([
                'principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $roleKey,
                'scope_type' => 'organization', 'scope_id' => $organization->id, 'organization_id' => $organization->id,
                'granted_by' => $context->actorId, 'expires_at' => $accessUntil,
            ]);
            $this->audit->record($context->withScope($organization->id), 'organization.member.attach', 'succeeded', ['user_id' => $user->id, 'role' => $roleKey, 'access_until' => $accessUntil?->toIso8601String()], 'organization', $organization->id);

            return $membership;
        });
    }

    /** A new role keeps the end of the access the member already has; `$setAccess` replaces it (null = no end). */
    public function changeRole(Organization $organization, User $user, string $roleKey, CommandContext $context, bool $setAccess = false, ?CarbonInterface $accessUntil = null): OrganizationMembership
    {
        if ($organization->owner_user_id === $user->id && $roleKey !== 'owner') {
            throw new DomainError('owner_role_locked', 'The organization owner keeps the owner role; transfer ownership first.');
        }

        $current = OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->first();
        $membership = $this->attachMember($organization, $user, $roleKey, $context, joinedNow: false, accessUntil: $setAccess ? $accessUntil : $current?->expires_at);
        // a smaller role may no longer cover what the old one put on the panels (H332): the listener takes back the person's
        // SSH keys and collaborator accounts on the services they can no longer manage
        if ($current !== null && $current->role_key !== $roleKey) {
            $this->outbox->publish(GenericEvent::of('organization.member.role_changed', 'organization', $organization->id, ['user_id' => $user->id, 'email' => mb_strtolower((string) $user->email), 'from' => $current->role_key, 'to' => $roleKey], $organization->id));
        }

        return $membership;
    }

    public function removeMember(Organization $organization, User $user, CommandContext $context): void
    {
        if ($organization->owner_user_id === $user->id) {
            throw new DomainError('owner_cannot_be_removed', 'Transfer ownership before removing the owner.');
        }
        DB::transaction(function () use ($organization, $user, $context) {
            OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->delete();
            PolicyBinding::query()->where('principal_type', 'user')->where('principal_id', $user->id)
                ->where('organization_id', $organization->id)->delete();
            $this->audit->record($context->withScope($organization->id), 'organization.member.remove', 'succeeded', ['user_id' => $user->id], 'organization', $organization->id);
        });
        // panel accounts are keyed by e-mail and would outlive the membership (H333): the listener removes them through audited operations
        $this->outbox->publish(GenericEvent::of('organization.member.removed', 'organization', $organization->id, ['user_id' => $user->id, 'email' => mb_strtolower((string) $user->email)], $organization->id));
    }

    /**
     * `$mailTemplate` / `$mailVars`: an invitation that says why it was sent (a service shared with a guest) — the accept
     * link is added here and nowhere else, so the token still travels only inside the mail.
     *
     * @param  array<string,string>  $mailVars
     * @return array{invitation: OrganizationInvitation, token: string}
     */
    public function invite(Organization $organization, string $email, string $roleKey, CommandContext $context, ?CarbonInterface $accessUntil = null, string $mailTemplate = 'invitation', array $mailVars = []): array
    {
        $this->assertCustomerRole($roleKey);
        if ($accessUntil !== null && ($roleKey === 'owner' || $accessUntil->isPast())) {
            throw new DomainError('access_until_invalid', 'access_until must be in the future and cannot be set for the owner role.', 422, ['field' => 'access_until']);
        }
        $token = Str::random(48);
        $invitation = OrganizationInvitation::query()->create([
            'organization_id' => $organization->id,
            'email' => mb_strtolower(trim($email)),
            'role_key' => $roleKey,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $context->actorId,
            'expires_at' => now()->addDays(7),
            'access_expires_at' => $accessUntil,
        ]);
        $this->audit->record($context->withScope($organization->id), 'organization.member.invite', 'succeeded', ['email' => $invitation->email, 'role' => $roleKey, 'access_until' => $accessUntil?->toIso8601String()], 'organization', $organization->id);
        // The accept token travels only inside the invitation mail; the (redacted, durable) outbox never carries it.
        app(NotificationService::class)->queueMail($mailTemplate, $invitation->email, array_merge($mailVars, [
            'organizace' => $organization->name, 'role' => $roleKey, 'url' => rtrim((string) config('onhost.portal_url'), '/').'/panel/tym?pozvanka='.rawurlencode($token),
        ]), 'organization_invitation', $invitation->id, $organization->id, $organization->locale ?? 'cs');
        $this->outbox->publish(GenericEvent::of('organization.invitation.created', 'organization', $organization->id, [
            'invitation_id' => $invitation->id, 'email' => $invitation->email, 'role' => $roleKey,
        ], $organization->id));

        return ['invitation' => $invitation, 'token' => $token];
    }

    /** A pending invitation is withdrawn: the mailed link stops working at once; accepted or expired ones cannot be cancelled. */
    public function cancelInvitation(Organization $organization, string $invitationId, CommandContext $context): OrganizationInvitation
    {
        $invitation = OrganizationInvitation::query()->where('organization_id', $organization->id)->find($invitationId);
        if ($invitation === null) {
            throw new DomainError('invitation_not_found', 'This invitation does not exist.', 404);
        }
        if (! $invitation->isUsable()) {
            throw new DomainError('invitation_not_pending', 'This invitation was already accepted or has expired.', 409);
        }
        $invitation->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->audit->record($context->withScope($organization->id), 'organization.member.invite.cancel', 'succeeded', ['email' => $invitation->email, 'role' => $invitation->role_key], 'organization', $organization->id);
        $this->outbox->publish(GenericEvent::of('organization.invitation.cancelled', 'organization', $organization->id, [
            'invitation_id' => $invitation->id, 'email' => $invitation->email, 'role' => $invitation->role_key,
        ], $organization->id));

        return $invitation;
    }

    public function acceptInvitation(string $token, User $user, CommandContext $context): OrganizationMembership
    {
        $invitation = OrganizationInvitation::query()->where('token_hash', hash('sha256', $token))->first();
        if ($invitation === null || ! $invitation->isUsable() || ($invitation->access_expires_at !== null && $invitation->access_expires_at->isPast())) { // an access that has already ended is not worth joining
            throw new DomainError('invitation_invalid', 'This invitation is invalid or has expired.', 410);
        }
        if (mb_strtolower($user->email) !== $invitation->email) {
            throw new DomainError('invitation_email_mismatch', 'The invitation was issued for a different e-mail address.', 403);
        }
        $organization = Organization::query()->findOrFail($invitation->organization_id);

        return DB::transaction(function () use ($invitation, $organization, $user, $context) {
            $invitation->forceFill(['accepted_at' => now()])->save();
            // A guest invitation comes with a shared service. Somebody who became a real member in the meantime keeps the role they
            // have: attaching `guest` here replaced it — accepting the older link would have cost a developer their access.
            $current = OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->current()->first();
            if ($current !== null && $invitation->role_key === 'guest') {
                return $current;
            }

            return $this->attachMember($organization, $user, $invitation->role_key, $context, joinedNow: true, accessUntil: $invitation->access_expires_at);
        });
    }

    public function transferOwnership(Organization $organization, User $newOwner, CommandContext $context): Organization
    {
        return DB::transaction(function () use ($organization, $newOwner, $context) {
            $previous = User::query()->findOrFail($organization->owner_user_id);
            $organization->forceFill(['owner_user_id' => $newOwner->id])->save();
            $this->attachMember($organization, $newOwner, 'owner', $context, joinedNow: true);
            $this->attachMember($organization, $previous, 'org_admin', $context);
            $this->audit->record($context->withScope($organization->id), 'organization.ownership.transfer', 'succeeded', ['from' => $previous->id, 'to' => $newOwner->id], 'organization', $organization->id);

            return $organization->refresh();
        });
    }

    /** @param array<string,mixed> $attributes */
    public function update(Organization $organization, array $attributes, CommandContext $context): Organization
    {
        $allowed = ['name', 'type', 'ico', 'dic', 'vat_id', 'billing_email', 'street', 'city', 'postal_code', 'country', 'locale', 'auto_renew_default', 'ui_mode', 'domain_renewal_reserve_days', 'currency'];
        $before = $organization->only($allowed);
        $changes = array_intersect_key($attributes, array_flip($allowed));
        if (array_key_exists('auto_renew_default', $changes) && (bool) $changes['auto_renew_default'] && ! (bool) $organization->auto_renew_default) {
            // owner decision 20 (TASK-0021): the standing auto-renew default is the holder's consent to renewals paid from credit — the owner or the billing admin switches it on
            app(CreditOrderPolicy::class)->assertMaySpend($organization, $context, 'Požádejte vlastníka o zapnutí automatického prodloužení.');
        }
        if (isset($changes['currency'])) { // the account currency the customer picks (audit §5j-8): quotes, documents and the default wallet follow it; wallets are per currency already
            $changes['currency'] = strtoupper((string) $changes['currency']);
            if (! in_array($changes['currency'], ['CZK', 'EUR'], true)) {
                throw new DomainError('currency_invalid', 'Currency must be CZK or EUR.', 422, ['field' => 'currency']);
            }
        }
        if (isset($attributes['status_page']) && is_array($attributes['status_page'])) { // the organization's own status page (audit §5j-5)
            $changes['settings'] = array_merge((array) ($organization->settings ?? []), ['status_page' => OrganizationStatusService::settings($attributes['status_page'], (array) data_get($organization->settings, 'status_page', []))]);
        }
        if (isset($attributes['digest_frequency'])) { // digest tuning (audit §5f-5): weekly | monthly | off, kept in the organization's settings
            $frequency = (string) $attributes['digest_frequency'];
            if (! in_array($frequency, ['weekly', 'monthly', 'off'], true)) {
                throw new DomainError('digest_frequency_invalid', 'digest_frequency must be weekly, monthly or off.', 422, ['field' => 'digest_frequency']);
            }
            $changes['settings'] = array_merge($changes['settings'] ?? (array) ($organization->settings ?? []), ['digest' => ['frequency' => $frequency]]);
        }
        if (isset($changes['country'])) {
            $changes['country'] = strtoupper((string) $changes['country']);
        }
        if (isset($changes['vat_id']) || isset($changes['ico'])) {
            $changes['customer_class'] = ! empty($changes['vat_id'] ?? $organization->vat_id) || ! empty($changes['ico'] ?? $organization->ico) ? 'b2b' : 'b2c';
        }
        // TASK-0031: the recorded check belongs to one number. When the number the check is about changes — a new VAT ID, one
        // removed, a DIČ changed while no VAT ID is set, a country that changes an unprefixed number — the evidence goes with it
        $subjectBefore = VatNumber::forOrganization($organization)?->value;
        $subjectAfter = VatNumber::forOrganization((clone $organization)->forceFill($changes))?->value;
        if ($subjectBefore !== $subjectAfter) {
            $changes = array_merge($changes, ['vat_status' => 'unknown', 'vat_validated_at' => null, 'vat_checked_at' => null, 'vat_checked_number' => null,
                'vat_consultation_number' => null, 'vat_validation_id' => null, 'vat_status_source' => null, 'vat_override_until' => null]);
        }
        $organization->forceFill($changes)->save();
        if ($subjectBefore !== $subjectAfter || array_intersect(array_keys($attributes), ['vat_id', 'dic']) !== []) {
            $this->queueVatCheck($organization); // a re-submitted number that is not valid now is asked about again (the job skips one checked within the hour)
        }
        $this->audit->record($context->withScope($organization->id), 'organization.update', 'succeeded', ['fields' => array_keys($changes)], 'organization', $organization->id, before: $before, after: $organization->only($allowed));

        return $organization;
    }

    /** @param array<string,mixed> $attributes */
    public function createProject(Organization $organization, array $attributes, CommandContext $context): Project
    {
        $name = trim((string) $attributes['name']);
        $slug = Str::slug($name) ?: 'project';
        $base = $slug;
        $i = 2;
        while (Project::query()->where('organization_id', $organization->id)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }
        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'slug' => $slug,
            'name' => $name,
            'description' => $attributes['description'] ?? null,
            'cost_center' => $attributes['cost_center'] ?? null,
            'tags' => $attributes['tags'] ?? [],
        ]);
        $this->audit->record($context->withScope($organization->id, $project->id), 'project.create', 'succeeded', ['name' => $name], 'project', $project->id);

        return $project;
    }

    /**
     * TASK-0031 (D31.3a): the VIES check of a number the organization gave runs on the queue after the commit, as the system
     * — the customer never waits for the register, and nothing is asked while the switch is off or the number counts now.
     */
    private function queueVatCheck(Organization $organization): void
    {
        if (! (bool) config('onhost.vies.enabled', false) || VatNumber::forOrganization($organization) === null || VatStanding::effectiveStatus($organization) === VatStanding::VALID) {
            return;
        }
        CheckVatNumber::dispatch($organization->id)->afterCommit();
    }

    private function assertCustomerRole(string $roleKey): void
    {
        if (! RoleCatalog::exists($roleKey) || RoleCatalog::all()[$roleKey]['staff'] || RoleCatalog::isResourceRole($roleKey)) {
            throw new DomainError('invalid_role', "Role {$roleKey} cannot be assigned inside an organization.");
        }
    }

    private function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name) ?: 'org';
        $base = $slug;
        $i = 2;
        while (Organization::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
