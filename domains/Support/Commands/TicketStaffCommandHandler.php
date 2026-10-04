<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Commands;

use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Support\Models\SupportMacro;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\TicketService;
use Onhost\Domain\Support\TicketStateMachine;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

/** Runs a support agent's ticket write inside the bus (TicketStaffCommand). Returns the ticket id; the controller presents it. */
final class TicketStaffCommandHandler implements CommandHandler
{
    public function __construct(private readonly TicketService $tickets) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof TicketStaffCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $ticket = Ticket::query()->lockForUpdate()->find((string) $command->get('ticket_id')) ?? throw DomainError::notFound('ticket');

        match ($command->op()) {
            'reply' => $this->reply($ticket, $command, $context),
            'note' => $this->tickets->reply($ticket, 'staff', $context->actorId, $this->authorName($context), (string) $command->get('body', ''), $context, 'internal'),
            'transition' => $this->tickets->transition($ticket, strtoupper((string) $command->get('to', '')), $context, $command->get('note'), (string) $command->get('note_visibility', 'internal')),
            'assign' => $this->assign($ticket, $command, $context),
            'escalate' => $this->tickets->escalate($ticket, $context, (string) $command->get('reason', '')),
            default => throw new DomainError('op_unknown', 'Unknown ticket operation.', 422, ['field' => 'op']),
        };

        return ['ticket_id' => $ticket->id, 'op' => $command->op()];
    }

    /** A public answer (or an internal one when asked), optionally from a macro whose state action follows the answer. */
    private function reply(Ticket $ticket, TicketStaffCommand $command, CommandContext $context): void
    {
        $body = (string) $command->get('body', '');
        $macro = $command->get('macro') !== null ? SupportMacro::query()->where('key', (string) $command->get('macro'))->first() : null;
        if ($command->get('macro') !== null && $macro === null) {
            throw new DomainError('macro_unknown', 'Takové makro neexistuje.', 422, ['field' => 'macro']);
        }
        if ($macro !== null) {
            $body = trim(($macro->body['cs'] ?? '')."\n\n".$body);
        }
        $visibility = $command->get('visibility') === 'internal' ? 'internal' : 'public';
        $this->tickets->reply($ticket, 'staff', $context->actorId, $this->authorName($context), $body, $context, $visibility);
        $state = (string) ($macro?->actions['state'] ?? '');
        $fresh = $ticket->fresh();
        if ($state !== '' && $fresh !== null && TicketStateMachine::machine()->canTransition($fresh->state, $state)) {
            $this->tickets->transition($fresh, $state, $context);
        }
    }

    private function assign(Ticket $ticket, TicketStaffCommand $command, CommandContext $context): void
    {
        if (array_key_exists('assignee_id', $command->payload)) {
            $this->tickets->assign($ticket, $command->get('assignee_id') ?: null, $context);
        }
        $this->tickets->route($ticket, $command->get('priority'), $command->get('queue'), $context);
    }

    private function authorName(CommandContext $context): ?string
    {
        return $context->actorId !== null ? User::query()->whereKey($context->actorId)->value('name') : null;
    }
}
