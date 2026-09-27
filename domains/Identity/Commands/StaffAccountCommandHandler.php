<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Commands;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\ApprovalService;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\RoleResolver;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Audit\HashChain;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

/**
 * Makes a staff account (TASK-0041, P0-16 red team; owning task TASK-0037, IF-10).
 *
 * The solo operator's own critical action waits a time lock; `onhost:staff:create --role=platform_owner` handed them a second
 * approver at once, and with it the approval of their own requests. A further approver asked for from the command line is
 * therefore a time-locked request of its own (`ApprovalService::commandLineTimeLock`): the approvers hear of it at once
 * (`iam.approval.time_locked`) and can cancel it on the approvals page; repeating the command after the lock makes the account.
 * The first approver of an installation, and staff who decide no approvals, are made at once, as before.
 */
final class StaffAccountCommandHandler implements CommandHandler
{
    /** Who asked, on the request: nobody signed in on the command line. */
    public const REQUESTER = 'cli:staff:create';

    public function __construct(private readonly ApprovalService $approvals, private readonly AuditRecorder $audit) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof StaffAccountCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $email = mb_strtolower(trim((string) $command->get('email')));
        $role = (string) $command->get('role');
        $password = (string) $command->get('password');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainError('email_invalid', 'A valid e-mail address is required.', 422, ['field' => 'email']);
        }
        if (! RoleResolver::exists($role)) {
            throw new DomainError('invalid_role', "Unknown role {$role}.", 422, ['field' => 'role']);
        }
        if (strlen($password) < 12) {
            throw new DomainError('password_too_short', 'The password must have at least 12 characters.', 422, ['field' => 'password']);
        }
        if (User::query()->where('email', $email)->exists()) {
            throw DomainError::conflict('staff_exists', "{$email} already exists.");
        }
        if (self::decidesApprovals($role) && ApprovalService::deciders()->isNotEmpty()
            && ApprovalService::releaseTimeLock($command->name(), HashChain::hashPayload($command->toAudit()), self::REQUESTER) === null) {
            $lock = $this->approvals->commandLineTimeLock($command, $context, self::REQUESTER);

            return ['created' => false, 'approval_id' => $lock->id, 'not_before' => ApprovalService::timeLockOf($lock)?->toIso8601String()];
        }

        $user = User::query()->create(['name' => (string) ($command->get('name') ?: explode('@', $email)[0]), 'email' => $email, 'password' => $password, 'locale' => 'cs', 'timezone' => 'Europe/Prague', 'is_staff' => true, 'state' => 'active', 'email_verified_at' => now()]);
        PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $role, 'scope_type' => 'global', 'scope_id' => null, 'organization_id' => null]);
        $this->audit->record($context, 'identity.staff.created', 'succeeded', ['email' => $email, 'role' => $role, 'decides_approvals' => self::decidesApprovals($role)], 'user', $user->id);

        return ['created' => true, 'user_id' => $user->id];
    }

    /** The role carries `iam.approval.decide` — by the catalogue or by the permissions stored for it. */
    public static function decidesApprovals(string $role): bool
    {
        return in_array(ApprovalService::PERMISSION, RoleResolver::grantable($role), true)
            || DB::table('role_permissions')->where('role_key', $role)->where('permission_key', ApprovalService::PERMISSION)->exists();
    }
}
