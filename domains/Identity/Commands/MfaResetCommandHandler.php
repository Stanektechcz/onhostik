<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Commands;

use Onhost\Domain\Identity\Models\TrustedDevice;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\Models\WebAuthnCredential;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Resets a person's second factor (TASK-0042, permission program D21): the authenticator, its recovery codes, security keys and
 * the devices trusted to skip it — they sign in with the password and enrol again. The person is told at once (`security.mfa`,
 * a mandatory mail). The owner of a customer organization is not reset here: OwnerRecoveries does it after its notice period.
 *
 * Review round 1 (TASK-0042): D21's route one role below the owner. A reset of an org_admin or of a staff account was one
 * iam_admin's word, and the organization never heard of it. It now takes a second person (MfaResetCommand::asksSecondPerson)
 * and every organization the person is a member of is told (`organization.member.mfa_reset`: its owner and admins in person) —
 * except those an owner recovery already told for a week.
 */
final class MfaResetCommandHandler implements CommandHandler
{
    public function __construct(private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox) {}

    /** @return array{reset: true, user_id: string} */
    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof MfaResetCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $user = User::query()->find((string) $command->get('user_id')) ?? throw DomainError::notFound('user');
        $reason = trim((string) $command->get('reason', ''));
        if (mb_strlen($reason) < 10) {
            throw new DomainError('reason_required', 'Say why the second factor is reset (at least 10 characters) and how the person was verified.', 422, ['field' => 'reason']);
        }
        if (Organization::query()->where('owner_user_id', $user->id)->exists()) {
            throw DomainError::conflict('owner_recovery_required', 'This person owns a customer organization: their access is recovered by an owner recovery (a second person, a week of notice to the members), not by a reset.', ['help' => '/v1/staff/customers/{organization}/owner-recovery']);
        }
        $this->reset($user, $context, $reason);

        return ['reset' => true, 'user_id' => $user->id];
    }

    /**
     * The reset itself — also what a completed owner recovery of mode `mfa_reset` runs.
     *
     * @param  list<string>  $told  organizations that heard of it already (the owner recovery's own rows)
     */
    public function reset(User $user, CommandContext $context, string $reason, array $told = []): void
    {
        $user->forceFill(['totp_secret' => null, 'totp_confirmed_at' => null, 'recovery_codes' => null])->save();
        WebAuthnCredential::query()->where('user_id', $user->id)->delete();
        TrustedDevice::query()->where('user_id', $user->id)->delete();
        $this->audit->record($context, 'identity.mfa.reset', 'succeeded', ['user_id' => $user->id, 'reason' => mb_substr($reason, 0, 250)], 'user', $user->id);
        $this->outbox->publish(GenericEvent::of('security.mfa', 'user', $user->id, ['email' => $user->email, 'change' => 'reset_by_support']));
        $organizations = OrganizationMembership::query()->where('user_id', $user->id)->current()->whereNotIn('organization_id', $told)->pluck('organization_id');
        foreach ($organizations as $organizationId) {
            $this->outbox->publish(GenericEvent::of('organization.member.mfa_reset', 'organization', (string) $organizationId, [
                'user_id' => $user->id, 'email' => mb_strtolower((string) $user->email), 'name' => $user->name, 'via' => $told === [] ? 'support' : 'owner_recovery',
            ], (string) $organizationId));
        }
    }
}
