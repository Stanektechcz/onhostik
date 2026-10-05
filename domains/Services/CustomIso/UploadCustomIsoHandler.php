<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\CustomIso;

use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

/** @implements CommandHandler<UploadCustomIsoCommand> */
final class UploadCustomIsoHandler implements CommandHandler
{
    public function __construct(private readonly CustomIsoLibrary $library) {}

    /** @return array<string,mixed> */
    public function handle(Command $command, CommandContext $context): array
    {
        if (! $command instanceof UploadCustomIsoCommand) {
            throw new DomainError('command_unsupported', 'Unsupported command.', 500);
        }
        $service = Service::query()->where('organization_id', $command->organizationId)->find((string) $command->get('service_id', ''))
            ?? throw DomainError::notFound('service');
        $iso = $this->library->store($service, (string) $command->get('token', ''), $context);

        return ['data' => CustomIsoLibrary::present($iso, $service)];
    }
}
