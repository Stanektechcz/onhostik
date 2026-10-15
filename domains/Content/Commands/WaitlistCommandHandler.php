<?php

declare(strict_types=1);

namespace Onhost\Domain\Content\Commands;

use Onhost\Domain\Content\Waitlist\WaitlistService;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class WaitlistCommandHandler implements CommandHandler
{
    public function __construct(private readonly WaitlistService $waitlist) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof WaitlistCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }

        return match ($command->op()) {
            'signup' => $this->waitlist->signup((string) $command->get('email'), (string) $command->get('product'), (string) $command->get('consent_version'), (string) $command->get('locale', 'cs'), $context->ip),
            'confirm' => $this->waitlist->confirm((string) $command->get('token')),
            'withdraw' => $this->waitlist->withdraw((string) $command->get('token')),
            default => throw new DomainError('waitlist_op_unknown', "Unknown waitlist operation {$command->op()}.", 422, ['field' => 'op']),
        };
    }
}
