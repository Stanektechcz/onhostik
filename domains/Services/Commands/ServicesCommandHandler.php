<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Commands;

use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class ServicesCommandHandler implements CommandHandler
{
    public function __construct(private readonly ServiceService $services) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        $service = Service::query()->find((string) $command->get('service_id'));
        if ($service === null || $service->organization_id !== $command->organizationId) {
            throw DomainError::notFound('service');
        }

        return match (true) {
            $command instanceof ServiceActionCommand => $this->action($command, $service, $context),
            $command instanceof IssueConsoleTokenCommand => $this->services->consoleAccess($service, $context),
            default => throw new \LogicException('Unsupported command '.get_class($command)),
        };
    }

    private function action(ServiceActionCommand $command, Service $service, CommandContext $context): array
    {
        $operation = $this->services->requestAction($service, (string) $command->get('action'), $context, $command->idempotencyKey, (array) $command->get('params', []));

        return ['operation_id' => $operation->id, 'state' => $operation->state, 'kind' => $operation->kind, 'service_state' => $service->fresh()->state];
    }
}
