<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Models\StepUpGrant;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Services\Console\ConsoleSessions;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;

/**
 * Ends what a person already has OPEN when their account is in doubt (permission program D20, S1-08, TASK-0044).
 *
 * Revoking bindings, tokens and panel identities closes the doors; it did not end the channels already through them. An MFA
 * reset (support believed somebody else holds the second factor) and an owner taken out by an owner recovery (the account may be
 * in an attacker's hands) left every signed-in browser signed in, the "remember me" cookie working, a fresh step-up usable for
 * its minutes, and an open root console typing. So, at once and not when the outbox delivers an event:
 *  · every web session of the person (the `sessions` table of the database session driver) and the "remember me" token;
 *  · every step-up grant (a step-up says "this is the person" — exactly what is in doubt);
 *  · their console tickets and open consoles (ConsoleSessions::endFor) — in one organization, or everywhere when `$organizationId`
 *    is null; the relay's next alive check closes an open socket.
 * API tokens are the caller's to decide (ApiAccessRevocation): an MFA reset ends them all, a removal those of the organization.
 * Panel sign-on sessions a panel offers no way to end are the residual window (docs/runbooks/console-relay.md).
 */
final class SessionKill
{
    public function __construct(private readonly AuditRecorder $audit, private readonly ConsoleSessions $consoles) {}

    /** @return array{web_sessions: int, step_up_grants: int, consoles: string} */
    public function end(User $user, string $reason, ?string $organizationId, CommandContext $context): array
    {
        $web = 0;
        if (config('session.driver') === 'database') {
            $web = DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        $stepUp = StepUpGrant::query()->where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $this->consoles->endFor($user->id, $organizationId);
        $ended = ['web_sessions' => $web, 'step_up_grants' => $stepUp, 'consoles' => $organizationId ?? '*'];
        $this->audit->record($organizationId !== null ? $context->withScope($organizationId) : $context, 'identity.sessions.end', 'succeeded', ['user_id' => $user->id, 'reason' => $reason] + $ended, 'user', $user->id);

        return $ended;
    }
}
