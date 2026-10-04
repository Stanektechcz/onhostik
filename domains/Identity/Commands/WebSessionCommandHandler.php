<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Commands;

use Illuminate\Support\Str;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\WebSessions;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

/**
 * Ends the caller's own web sessions (TASK-0070). The session the request comes from (`keep`, taken by the controller from the
 * server-side session, never from the request body) is never ended here — that is a sign-out. A session of somebody else is
 * "not found", the same answer as an id that never existed, so ids of other people cannot be probed.
 *
 * The "remember me" token is rotated as well: one token serves every remembered browser of a person, and a browser whose session
 * was ended would otherwise sign itself straight back in with it. The browser this runs from stays signed in (its session is not
 * ended); it asks for the password again only once its session expires.
 */
final class WebSessionCommandHandler implements CommandHandler
{
    public function __construct(private readonly WebSessions $sessions) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof WebSessionCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $user = $context->actorType === 'user' && $context->actorId !== null ? User::query()->find($context->actorId) : null;
        if ($user === null || $context->sessionId === null || str_starts_with($context->sessionId, 'token:')) {
            throw DomainError::forbidden('Web sessions are ended from a signed-in browser of the same person.');
        }
        $keep = $command->get('keep');
        $keep = is_string($keep) && $keep !== '' ? $keep : null;

        return match ($command->op()) {
            'end' => $this->endOne($user, (string) $command->get('session_id', ''), $keep),
            'end_others' => $this->endOthers($user, $keep),
            default => throw new DomainError('web_session_op_unknown', "Unknown session operation {$command->op()}.", 422),
        };
    }

    /** @return array{ended: int, id: string} */
    private function endOne(User $user, string $id, ?string $keep): array
    {
        if ($keep !== null && $id === $keep) {
            throw new DomainError('web_session_current', 'This is the session you are using; sign out instead.', 422, ['field' => 'session']);
        }
        if ($id === '' || $this->sessions->findOpen($id, (string) $user->id) === null) {
            throw DomainError::notFound('session');
        }
        $this->sessions->end($id, (string) $user->id, WebSessions::ENDED_BY_USER);
        $this->forgetRemembered($user);

        return ['ended' => 1, 'id' => $id];
    }

    /** @return array{ended: int} */
    private function endOthers(User $user, ?string $keep): array
    {
        if ($keep === null) {
            // without the caller's own row "every other" would be every one — including the browser asking
            throw new DomainError('web_session_unknown', 'This browser\'s session is not known yet; reload the page and try again.', 409);
        }
        $ended = $this->sessions->endAllBut((string) $user->id, $keep, WebSessions::ENDED_BY_USER);
        $this->forgetRemembered($user);

        return ['ended' => $ended];
    }

    private function forgetRemembered(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
