<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Commands;

use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class ServicesCommandHandler implements CommandHandler
{
    /**
     * What a customer may say about a core action (Brain card H21). The workflow reads its parameters from the same
     * bag the platform's own callers fill — the size to resize to, whether the archive before a deletion is skipped,
     * the kind and retention of a backup, where a restore lands. None of that is a customer's to choose: a request
     * from the API keeps only the keys listed here, everything else is dropped before it reaches the workflow.
     * Feature actions are not listed: `ServiceService::featureParams()` rebuilds their parameters from scratch.
     * A core action missing from the list keeps nothing — the safe side for an action added later.
     *
     * @var array<string, list<string>>
     */
    private const CUSTOMER_PARAMS = [
        'power' => ['power_action', 'reason'],
        'suspend' => ['reason'],
        'resume' => ['reason'],
        'terminate' => ['reason'],
        'purge' => ['reason'],
        'backup' => ['reason'],
        'restore' => ['backup_id', 'reason'],
        'archive.restore' => ['backup_id', 'reason'],
        'snapshot' => ['name', 'description', 'reason'],
        'rollback_snapshot' => ['name', 'reason'],
    ];

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
        if (in_array($action, ServiceActionWorkflow::CORE_ACTIONS, true) && ! $this->isStaff($context)) {
            if ($action === 'resize') { // the size of a service is what was paid for: it changes through a plan change order, which resizes after the payment
                throw new DomainError('resize_requires_plan_change', 'The resources of a service follow its plan. Change the plan to change them.', 403, ['hint' => 'order a plan change (cart line with config.upgrade_of)']);
            }
            $params = array_intersect_key($params, array_flip(self::customerParams($action)));
        }
        $operation = $this->services->requestAction($service, $action, $context, $command->idempotencyKey, $params, authorizedPermission: $command->permission()); // the permission the bus just checked is the one the run asks for again before each step (H315)

        return ['operation_id' => $operation->id, 'state' => $operation->state, 'kind' => $operation->kind, 'service_state' => $service->fresh()->state];
    }

    /** @return list<string> the keys a customer's request keeps for this core action; an action nobody listed keeps nothing */
    private static function customerParams(string $action): array
    {
        return self::CUSTOMER_PARAMS[$action] ?? [];
    }

    /** Staff work through the same command (a forced purge with a reason); everybody else is a customer, whatever they are: a person, a token, an assistant. */
    private function isStaff(CommandContext $context): bool
    {
        return $context->actorType === 'user' && $context->actorId !== null && (bool) User::query()->whereKey($context->actorId)->value('is_staff');
    }
}
