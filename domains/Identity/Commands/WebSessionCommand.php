<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Commands;

use Onhost\Platform\Commands\GlobalCommand;

/**
 * op: end{session_id} · end_others — a person ends their own web sessions (TASK-0070, audit 2026-10 C11): one browser they no
 * longer use or do not recognise, or every one but the browser they are in. Personal, like accepting an invitation: no
 * organization and no permission — the handler acts only on the caller's own sessions (another person's session is not found),
 * and only from a web session of the caller (an API token never ends a browser). The current session is ended by signing out.
 */
final class WebSessionCommand extends GlobalCommand
{
    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return null;
    }

    public function name(): string
    {
        return 'identity.web_session.'.$this->op();
    }
}
