<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Commands;

use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Support\Models\AiRun;
use Onhost\Domain\Support\Models\Handoff;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\TicketService;
use Onhost\Domain\Support\TicketStateMachine;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/** Runs a customer's ticket write inside the bus (TicketCustomerCommand). Returns the ticket id; the controller presents it. */
final class TicketCustomerCommandHandler implements CommandHandler
{
    /** What a customer may set when opening a ticket; everything else (channel, tags, incident, e-mail) is the platform's. */
    private const CREATE_FIELDS = ['subject', 'body', 'category', 'priority', 'service_id', 'domain_id', 'attachments'];

    /** Why the assistant hands a conversation over (AssistantService::chat). */
    private const HANDOFF_REASONS = ['user_request', 'security', 'legal', 'repeated_failure', 'low_confidence'];

    public function __construct(private readonly TicketService $tickets, private readonly OutboxPublisher $outbox) {}

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
            'close' => $this->close($command, $context),
            'rate' => $this->tickets->rate($this->ticket($command), (int) $command->get('score'), $command->get('comment') === null ? null : (string) $command->get('comment'), $context),
            'handoff' => $this->handoff($command, $context, $organization, $user),
            default => throw new DomainError('op_unknown', 'Unknown ticket operation.', 422, ['field' => 'op']),
        };

        return ['ticket_id' => $ticket->id, 'op' => $command->op()];
    }

    /** The ticket the op names, locked; a ticket of another organization does not exist for this one. */
    private function ticket(TicketCustomerCommand $command): Ticket
    {
        return Ticket::query()->where('organization_id', $command->organizationId)->lockForUpdate()->find((string) $command->get('ticket_id')) ?? throw DomainError::notFound('ticket');
    }

    private function reply(TicketCustomerCommand $command, CommandContext $context, User $user): Ticket
    {
        $ticket = $this->ticket($command);
        $this->tickets->reply($ticket, 'customer', $user->id, $user->name, (string) $command->get('body', ''), $context, 'public', (array) $command->get('attachments', []));

        return $ticket;
    }

    private function close(TicketCustomerCommand $command, CommandContext $context): Ticket
    {
        $ticket = $this->ticket($command);
        if (! $ticket->isOpen()) {
            throw new DomainError('ticket_not_open', 'Tiket už je vyřešený.', 409);
        }

        // the customer's own words: public (TicketService made a transition note internal by default, TASK-0054)
        return $this->tickets->transition($ticket, TicketStateMachine::RESOLVED, $context, 'Zákazník označil požadavek za vyřešený.', 'public');
    }

    /**
     * The assistant's handoff: a ticket on the AI channel in the name of the person who was chatting, with the summary for staff,
     * the handoff record of the conversation and its `ticket.handoff` event — as AssistantService wrote them before. Channel, tags
     * and e-mail are decided here, not taken from the payload; the conversation must be this person's in this organization.
     */
    private function handoff(TicketCustomerCommand $command, CommandContext $context, Organization $organization, User $user): Ticket
    {
        $reason = (string) $command->get('reason');
        if (! in_array($reason, self::HANDOFF_REASONS, true)) {
            throw new DomainError('handoff_reason_invalid', 'Unknown handoff reason.', 422, ['field' => 'reason']);
        }
        $run = AiRun::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->find((string) $command->get('ai_run_id')) ?? throw DomainError::notFound('conversation');
        $ticket = $this->tickets->create([
            'subject' => (string) $command->get('subject'), 'body' => (string) $command->get('body'), 'category' => $command->get('category'),
            'priority' => $reason === 'security' ? 'vysoka' : null, 'channel' => 'ai', 'tags' => ['ai-handoff', $reason], 'email' => $user->email ?? $organization->billing_email,
        ], $context, $organization, $user);
        $ticket->forceFill(['ai_summary' => (string) $command->get('summary')])->save();
        $topic = (array) $command->get('topic', []);
        Handoff::query()->create(['ai_run_id' => $run->id, 'ticket_id' => $ticket->id, 'reason' => $reason, 'diagnostics' => ['facts' => (array) $command->get('facts', []), 'topic' => $topic]]);
        $this->outbox->publish(GenericEvent::of('ticket.handoff', 'ticket', $ticket->id, ['number' => $ticket->number, 'reason' => $reason, 'topic' => $topic['topic'] ?? null], $organization->id));

        return $ticket;
    }
}
