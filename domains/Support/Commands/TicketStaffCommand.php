<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * A support agent's write on one ticket, dispatched by `op` (readiness audit 2026-10, P1-3 — the staff routes wrote the
 * ticket from the controller, past the bus, its idempotency and its audit):
 *  reply{ticket_id, body, macro?} · note{ticket_id, body} · transition{ticket_id, to, note?, note_visibility?}
 *  · assign{ticket_id, assignee_id?, priority?, queue?} · escalate{ticket_id, reason}
 *
 * Assigning and re-routing (priority, queue) is `support.ticket.assign` — L1 answers and resolves, L2 and up decide who
 * works on what. Nothing here moves money or touches a service: NORMAL risk, no step-up.
 */
final class TicketStaffCommand extends GlobalCommand implements RiskAwareCommand
{
    public const OPS = ['reply', 'note', 'transition', 'assign', 'escalate', 'complaint.open', 'complaint.resolve']; // L-15: a complaint marked and decided

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): string
    {
        return $this->op() === 'assign' ? 'support.ticket.assign' : 'support.ticket.manage';
    }

    public function name(): string
    {
        return 'ticket.staff.'.$this->op();
    }

    public function riskLevel(): string
    {
        return PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return false;
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
