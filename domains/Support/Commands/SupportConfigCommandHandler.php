<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Commands;

use Onhost\Domain\Support\SupportConfigService;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class SupportConfigCommandHandler implements CommandHandler
{
    public function __construct(private readonly SupportConfigService $config) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof SupportConfigCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $kind = $command->kind();
        $data = (array) $command->get('data', []);

        $id = (string) $command->get('id');
        if ($command->op() === 'delete') {
            $this->config->delete($kind, $id, $context);

            return ['data' => ['id' => $id, 'deleted' => true]];
        }

        return match ($command->op()) {
            'create' => ['data' => SupportConfigService::present($kind, $this->config->create($kind, $data, $context))],
            'update' => ['data' => SupportConfigService::present($kind, $this->config->update($kind, $id, $data, $context))],
            default => throw new DomainError('op_unknown', 'Unknown support setting operation.', 422, ['field' => 'op']),
        };
    }
}
