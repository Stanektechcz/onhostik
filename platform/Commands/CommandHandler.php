<?php

declare(strict_types=1);

namespace Onhost\Platform\Commands;

/**
 * @template TCommand of Command
 */
interface CommandHandler
{
    /** @param TCommand $command */
    public function handle(Command $command, CommandContext $context): mixed;
}
