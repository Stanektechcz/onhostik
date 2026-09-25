<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Commands;

use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Services\CustomerActionParams;
use Onhost\Domain\Services\Limits\LimitRaisePolicy;
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
        $action = (string) $command->get('action');
        $params = (array) $command->get('params', []);
        if (! $this->isStaff($context)) { // a customer's request keeps only what a customer may choose (H21)
            $params = CustomerActionParams::filter($action, $params);
        } elseif ($action === 'resize') { // staff repair or lower; more than the service holds is a raise, and a raise is an order (TASK-0022)
            LimitRaisePolicy::assertNoUnbilledRaise($service, (array) ($params['entitlements'] ?? []));
        }
        $operation = $this->services->requestAction($service, $action, $context, $command->idempotencyKey, $params, authorizedPermission: $command->permission()); // the permission the bus just checked is the one the run asks for again before each step (H315)

        return ['operation_id' => $operation->id, 'state' => $operation->state, 'kind' => $operation->kind, 'service_state' => $service->fresh()->state];
    }

    /** Staff work through the same command (a forced purge with a reason); everybody else is a customer, whatever they are: a person, a token, an assistant. */
    private function isStaff(CommandContext $context): bool
    {
        return $context->actorType === 'user' && $context->actorId !== null && (bool) User::query()->whereKey($context->actorId)->value('is_staff');
    }
}
