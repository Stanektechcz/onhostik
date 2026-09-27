<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Commands;

use Onhost\Domain\Billing\ServiceReinstatement;
use Onhost\Domain\Identity\Authorization\StaffActor;
use Onhost\Domain\Services\CustomerActionParams;
use Onhost\Domain\Services\Limits\LimitRaisePolicy;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceArchiveService;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class ServicesCommandHandler implements CommandHandler
{
    /**
     * A cancelled service brought back after its paid period ran out, whose customer had auto-renew off (TASK-0027 review
     * round 1): billing restarts today, but nobody who may spend the credit agreed to renew it, so it ends again at the next
     * renewal pass. The resume goes ahead; whoever sent it (in practice staff — a customer is asked to pay first) is told.
     */
    private const ENDS_AGAIN = 'restore_ends_at_renewal';

    private const ENDS_AGAIN_MESSAGE = 'Služba se obnoví, ale zaplacené období už skončilo a automatické prodlužování zůstává vypnuté, jak ho zákazník nechal: při příštím průchodu obnov služba znovu skončí, pokud ji zákazník nezaplatí (obnovení s platbou) nebo vlastník či správce fakturace nezapne automatické prodlužování.';

    public function __construct(private readonly ServiceService $services, private readonly ServiceArchiveService $archives) {}

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
        if (! StaffActor::may($context, StaffActor::SERVICE_KEY)) { // a customer's request keeps only what a customer may choose (H21) — a member of staff on a customer route included (TASK-0039, IF-8), and one without the staff key on the staff route (P0-16 re-check)
            $params = CustomerActionParams::filter($action, $params);
        } elseif ($action === 'resize') { // staff repair or lower; more than the service holds is a raise, and a raise is an order (TASK-0022)
            LimitRaisePolicy::assertNoUnbilledRaise($service, (array) ($params['entitlements'] ?? []));
            LimitRaisePolicy::assertNoUnbilledLimits($service, (array) ($params['limits'] ?? []));
        }
        $endsAgain = $action === 'resume' && app(ServiceReinstatement::class)->restoreEndsAgain($service); // read before the resume undoes the cancellation
        // an archive goes back through the one path that asks about the SOURCE too (TASK-0035, IF-11 / audit SE-14): the bus
        // checked backup.restore on this service only, and a single shared service is no right to another service's archive
        $operation = $action === 'archive.restore'
            ? $this->archives->restore($this->archives->archive((string) ($params['backup_id'] ?? ''), $service->organization_id), $service, $context, $command->idempotencyKey)
            : $this->services->requestAction($service, $action, $context, $command->idempotencyKey, $params, authorizedPermission: StaffActor::permissionOf($command, $context)); // the permission the bus just checked — the staff key in staff mode — is the one the run asks for again before each step (H315)

        return ['operation_id' => $operation->id, 'state' => $operation->state, 'kind' => $operation->kind, 'service_state' => $service->fresh()->state]
            + ($endsAgain ? ['warning' => ['code' => self::ENDS_AGAIN, 'message' => self::ENDS_AGAIN_MESSAGE]] : []);
    }
}
