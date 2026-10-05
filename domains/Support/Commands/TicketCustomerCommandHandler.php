<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Commands;

use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\TicketService;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

/** Runs a customer's ticket write inside the bus (TicketCustomerCommand). Returns the ticket id; the controller presents it. */
final class TicketCustomerCommandHandler implements CommandHandler
{
    /** What a customer may set when opening a ticket; everything else (channel, tags, incident, e-mail) is the platform's. */
    private const CREATE_FIELDS = ['subject', 'body', 'category', 'priority', 'service_id', 'domain_id', 'attachments'];

    public function __construct(private readonly TicketService $tickets) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof TicketCustomerCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $organization = Organization::query()->find($command->organizationId) ?? throw DomainError::notFound('organization');
        $user = $context->actorType === 'user' && $context->actorId !== null ? User::query()->find($context->actorId) : null;
        if ($user === null) {
            throw DomainError::forbidden('A ticket is written by a signed-in person.');
        }

        $ticket = match ($command->op()) {
            'create' => $this->tickets->create(array_intersect_key($command->payload, array_flip(self::CREATE_FIELDS)) + ['channel' => 'portal'], $context, $organization, $user),
            'reply' => $this->reply($command, $context, $user),
            default => throw new DomainError('op_unknown', 'Unknown ticket operation.', 422, ['field' => 'op']),
        };

        return ['ticket_id' => $ticket->id, 'op' => $command->op()];
    }

    private function reply(TicketCustomerCommand $command, CommandContext $context, User $user): Ticket
    {
        // a ticket of another organization does not exist for this one
        $ticket = Ticket::query()->where('organization_id', $command->organizationId)->lockForUpdate()->find((string) $command->get('ticket_id')) ?? throw DomainError::notFound('ticket');
        $this->tickets->reply($ticket, 'customer', $user->id, $user->name, (string) $command->get('body', ''), $context, 'public', (array) $command->get('attachments', []));

        return $ticket;
    }
}
