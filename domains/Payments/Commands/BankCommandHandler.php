<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Commands;

use Onhost\Domain\Payments\BankStatementImporter;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class BankCommandHandler implements CommandHandler
{
    public function __construct(private readonly BankStatementImporter $importer) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof BankCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }

        return match ($command->op()) {
            'bank.line.record' => (function () use ($command, $context): array {
                $outcome = $this->importer->record((array) $command->get('line', []), $context);

                return [
                    'result' => $outcome['result'], 'created' => $outcome['created'], 'line' => BankStatementImporter::presentLine($outcome['line']),
                    'payment_intent_id' => $outcome['intent']?->id, 'payment_state' => $outcome['intent']?->state, 'purpose' => $outcome['intent']?->purpose,
                ];
            })(),
            'bank.sync' => $this->importer->syncFio($context, $command->get('from') ?: null, $command->get('to') ?: null),
            default => throw new DomainError('bank_op_unknown', "Unknown bank operation {$command->op()}.", 422),
        };
    }
}
