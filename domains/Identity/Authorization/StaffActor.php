<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Authorization;

use Onhost\Domain\Identity\Authorization\Models\JitElevation;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Commands\CommandContext;

/**
 * The one answer to "does this context act as staff?" (permission program P0-08, IF-8, principle 2).
 *
 * `is_staff` used to be read as that answer wherever a customer protection could be skipped: the customer's parameter filter
 * (a forced purge inside the restore window, a skipped final archive), ONhost's own holds (a quarantine lifted by whoever is
 * staff), a panel under maintenance, the credit gate of a restore, and every console pre-flight. A member of staff who is also a
 * member of an organization — an operator's own company, a test tenant — got all of that on the CUSTOMER's routes (audit SS-1,
 * EXPL-1..3). Now a context acts as staff only in staff mode (CommandContext::$staffMode, set for /v1/staff/* by ApiContext),
 * for a person, not on anybody's behalf, whose account is staff and active. Everywhere else a member of staff is the customer
 * they act as. StaffModeTest keeps the remaining reads of `is_staff` on an allow-list.
 */
final class StaffActor
{
    /**
     * Which service families a staff role may open a console or a panel sign-on of (P0-14, program §3 "staff.console of the
     * service family"). Family-scoped bindings arrive with S2-01; until then the family is read from the staff role that
     * carries `staff.console`. `*` = every family. A role not named here (a role made later in the database) opens nothing —
     * fail closed; support L2/L3 keep every family until S2-01 scopes them per queue.
     */
    public const CONSOLE_FAMILIES = [
        'platform_owner' => ['*'],
        'infrastructure_admin' => ['cloud', 'data'],
        'shared_hosting_admin' => ['web', 'mail'],
        'managed_hosting_admin' => ['web', 'managed'],
        'cloud_vps_admin' => ['cloud'],
        'game_admin' => ['game'],
        'database_admin' => ['data'],
        'support_l2' => ['*'],
        'support_l3' => ['*'],
    ];

    /** Whether the context acts as staff: staff mode, a person acting for themselves, a staff account that is active. */
    public static function acts(CommandContext $context): bool
    {
        if (! $context->staffMode || $context->actorType !== 'user' || $context->actorId === null || $context->onBehalfOfUserId !== null) {
            return false;
        }
        $user = User::query()->find($context->actorId);

        return $user !== null && self::account($user) && $user->isActive();
    }

    /**
     * Whether the account is a staff account at all, whatever mode it acts in. Not a permission: the one use is refusing what a
     * member of staff did as a customer where only a customer's act counts — a ticket that lets staff into a panel, a consent on it
     * (program D7, TASK-0039 review round 1: a member of staff who is also a member of the organization satisfied their own ticket).
     */
    public static function account(?User $user): bool
    {
        return $user !== null && (bool) $user->is_staff;
    }

    /** The staff person behind a context that acts as staff, or null. */
    public static function user(CommandContext $context): ?User
    {
        return self::acts($context) ? User::query()->find((string) $context->actorId) : null;
    }

    /**
     * Whether the person holds `staff.console` through a staff role whose families cover `$family` — a global binding or a live
     * JIT elevation of a staff role (a customer role lent by JIT opens no staff console).
     */
    public static function consoleCovers(User $user, string $family, Authorizer $authorizer): bool
    {
        foreach (self::staffRoles($user) as $role) {
            $families = self::CONSOLE_FAMILIES[$role] ?? [];
            if ((in_array('*', $families, true) || in_array($family, $families, true)) && in_array('staff.console', $authorizer->roleCarries($role), true)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> the staff role keys the person holds at platform level (global bindings, live JIT elevations) */
    private static function staffRoles(User $user): array
    {
        $bound = PolicyBinding::query()->where('principal_type', 'user')->where('principal_id', $user->id)->where('scope_type', 'global')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->pluck('role_key')->all();
        $lent = JitElevation::query()->where('user_id', $user->id)->where('state', 'approved')->whereNull('revoked_at')->where('expires_at', '>', now())
            ->where('scope_type', 'global')->pluck('role_key')->all();

        return array_values(array_unique(array_map('strval', [...$bound, ...$lent])));
    }
}
