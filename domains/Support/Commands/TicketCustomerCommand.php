<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\TicketVisibility;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * A customer's write on their organization's tickets, dispatched by `op` (TASK-0098 — the portal opened and answered tickets
 * in TicketService straight from the controller, past the bus, its idempotency and its audit of the command):
 *  create{subject, body, category?, priority?, service_id?, domain_id?, attachments?} · reply{ticket_id, body, attachments?}
 *
 * The scope is what the ticket concerns, as TicketVisibility reads it: a ticket about a service is written at that service (a
 * project developer opens one about their own service), any other at the organization. The service and its project are the
 * stored ones, never the payload's. Nothing here moves money or touches a service: NORMAL risk, no step-up.
 *
 * What the customer wrote stays out of the bus's audit row (a pasted password in a ticket must not land in the audit log);
 * TicketService records the ticket number, topic and priority as before.
 */
final class TicketCustomerCommand extends OrganizationCommand implements RiskAwareCommand
{
    public const OPS = ['create', 'reply'];

    protected const AUDIT_STRIP = ['password', 'auth_info', 'secret', 'code', 'token', 'totp', 'recovery_code', 'subject', 'body', 'attachments'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): string
    {
        return TicketVisibility::WRITE;
    }

    public function name(): string
    {
        return 'ticket.customer.'.$this->op();
    }

    public function scope(): CommandScope
    {
        $serviceId = $this->op() === 'reply'
            ? Ticket::query()->where('organization_id', $this->organizationId)->whereKey((string) $this->get('ticket_id'))->value('service_id')
            : $this->get('service_id');
        if (! is_string($serviceId) || $serviceId === '') {
            return CommandScope::organization($this->organizationId);
        }
        $service = Service::query()->withTrashed()->where('organization_id', $this->organizationId)->find($serviceId);

        return $service === null
            ? CommandScope::organization($this->organizationId) // somebody else's service: the handler answers 404 for it
            : CommandScope::resource($service->id, $this->organizationId, $service->project_id);
    }

    /** The ticket as it is now: a double click on the same ticket is one reply, the next message on a changed ticket a new one. */
    public static function versionOf(Ticket $ticket): string
    {
        return substr(hash('sha256', implode('|', [$ticket->state, $ticket->updated_at?->format('U.u'), $ticket->messages()->count()])), 0, 16);
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
