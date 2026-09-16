<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Commands;

use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceArchiveService;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class ServiceArchiveCommandHandler implements CommandHandler
{
    public function __construct(private readonly ServiceArchiveService $archives) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof ServiceArchiveCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $backup = $this->archives->archive((string) $command->get('backup_id'), $command->organizationId);

        return match ((string) $command->get('op')) {
            'download' => $this->download($backup, $context),
            'restore' => $this->restore($command, $backup, $context),
            default => throw new DomainError('archive_op_unknown', 'Unknown archive operation.', 422),
        };
    }

    private function download(Backup $backup, CommandContext $context): array
    {
        $package = $this->archives->download($backup, $context);

        return ['backup_id' => $backup->id, 'filename' => $package['filename'], 'bytes' => $package['bytes'], 'sha256' => $package['sha256'], 'fee_minor' => $package['fee_minor'], 'charged' => $package['charged']];
    }

    private function restore(ServiceArchiveCommand $command, Backup $backup, CommandContext $context): array
    {
        $target = Service::query()->where('organization_id', $command->organizationId)->find((string) $command->get('service_id'));
        if ($target === null) {
            throw DomainError::notFound('service');
        }
        $operation = $this->archives->restore($backup, $target, $context, $command->idempotencyKey);

        return ['operation_id' => $operation->id, 'state' => $operation->state, 'service_id' => $target->id, 'backup_id' => $backup->id, 'fee_minor' => 0];
    }
}
