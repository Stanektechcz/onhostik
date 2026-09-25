<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/**
 * Who may spend the organization's credit (owner decision 20, TASK-0021): the organization owner and the billing admin —
 * whoever holds `billing.wallet.spend` in the organization. Anybody else who places an order paid from credit (wallet or
 * postpaid) gets it held for their approval; anybody else who would pay from credit at once (an invoice, a domain renewal, a
 * marketplace order, approving paid support work, an archive download) is refused with a message that says whom to ask.
 *
 * The acting person is read from the command context, never from the `$user` a caller passes (a service account or an AI run
 * has none), and it fails closed: an actor nobody can name is not a holder. The platform itself (`system`: renewals, automatic
 * upgrades, housekeeping) is not a member and spends what the customer already agreed to.
 *
 * Everything here is behind `onhost.orders.credit_approval.enabled`, off by default: existing customers keep today's path until
 * an operator turns it on (after reading `onhost:orders:credit-approval-report`).
 */
final class CreditOrderPolicy
{
    public const PERMISSION = 'billing.wallet.spend';

    public function __construct(private readonly Authorizer $authorizer) {}

    public static function enabled(): bool
    {
        return (bool) config('onhost.orders.credit_approval.enabled', false);
    }

    /** @return list<string> the payment modes of an order that spend credit */
    public static function gatedModes(): array
    {
        return array_values(array_map('strval', (array) config('onhost.orders.credit_approval.modes', ['wallet', 'postpaid'])));
    }

    /** An order that must wait for an owner or billing admin before its credit is reserved. */
    public function mustAwaitApproval(Organization $organization, CommandContext $context, string $mode, string $source, Money $total): bool
    {
        if (! self::enabled() || ! in_array($mode, self::gatedModes(), true) || $total->isZero() || $context->actorType === 'system') {
            return false;
        }
        $principal = $this->principal($context);
        if ($principal === null) {
            return true;
        }
        // an assisted order: staff place it on the customer's request, and the note says whose request it was
        if ($source === 'staff' && $principal instanceof User && $this->authorizer->can($principal, 'staff.customer.manage', CommandScope::global())) {
            return false;
        }

        return ! $this->holds($principal, $organization);
    }

    /** Whether the acting person may pay from the organization's credit at once. */
    public function maySpend(Organization|string $organization, CommandContext $context): bool
    {
        if (! self::enabled() || $context->actorType === 'system') {
            return true;
        }
        $organization = $organization instanceof Organization ? $organization : Organization::query()->find($organization);
        if ($organization === null) {
            return false;
        }
        $principal = $this->principal($context);

        return $principal !== null && $this->holds($principal, $organization);
    }

    /**
     * Refuses paying from credit at once to anybody who does not hold the right. `$ask` finishes the sentence with what the
     * member should ask the owner for (Czech, the customer reads it).
     */
    public function assertMaySpend(Organization|string $organization, CommandContext $context, string $ask): void
    {
        if ($this->maySpend($organization, $context)) {
            return;
        }

        throw new DomainError('credit_spend_not_allowed', 'Z kreditu organizace může platit jen její vlastník nebo fakturační správce. '.$ask, 403, ['permission' => self::PERMISSION]);
    }

    public function mayApprove(User $user, Organization $organization): bool
    {
        return $this->holds($user, $organization);
    }

    /**
     * The people who decide a held order: active users bound in the organization by a role that grants the permission while
     * their membership is current (SECURITY_RULES §2 — a binding left behind by an ended membership tells a former member
     * nothing), and the owner. Staff are never among them (a global binding is not an organization role).
     *
     * @return Collection<int, User>
     */
    public static function approvers(string $organizationId): Collection
    {
        $roles = DB::table('role_permissions')->where('permission_key', self::PERMISSION)->pluck('role_key')->all();
        $ids = PolicyBinding::query()->where('principal_type', 'user')->where('scope_type', 'organization')->where('scope_id', $organizationId)->whereIn('role_key', $roles)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereIn('principal_id', OrganizationMembership::query()->current()->where('organization_id', $organizationId)->select('user_id'))
            ->pluck('principal_id')->all();
        $owner = Organization::query()->whereKey($organizationId)->value('owner_user_id');
        if (is_string($owner) && $owner !== '') {
            $ids[] = $owner;
        }

        return User::query()->whereIn('id', array_values(array_unique($ids)))->where('state', 'active')->orderBy('created_at')->get();
    }

    private function holds(User|ServiceAccount $principal, Organization $organization): bool
    {
        if ($principal instanceof ServiceAccount) {
            // a machine never spends the credit on its own: its orders wait for a person. (The Authorizer cannot read a service
            // account's bindings yet — ServiceAccount is not Authenticatable — so asking it would fail anyway, not answer.)
            return false;
        }
        if ($principal->id === $organization->owner_user_id && $principal->isActive()) {
            return true; // the literal owner, even if their owner binding were ever missing
        }

        return $this->authorizer->can($principal, self::PERMISSION, CommandScope::organization($organization->id));
    }

    /** The person the command acts for — the same reading as the bus's authorizer (IdentityCommandAuthorizer). */
    private function principal(CommandContext $context): User|ServiceAccount|null
    {
        if ($context->actorId === null && $context->onBehalfOfUserId === null) {
            return null;
        }

        return match ($context->actorType) {
            'user', 'ai' => User::query()->find($context->onBehalfOfUserId ?? $context->actorId),
            'service_account' => $context->actorId === null ? null : ServiceAccount::query()->find($context->actorId),
            default => null,
        };
    }
}
