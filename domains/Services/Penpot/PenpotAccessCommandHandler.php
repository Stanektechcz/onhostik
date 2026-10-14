<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Penpot;

use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\OperationService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

/** Runs PenpotAccessCommand (TASK-0123): checks what the bus cannot know, then starts the operation that talks to the node. */
final class PenpotAccessCommandHandler implements CommandHandler
{
    public const PASSWORD_MIN = 12;

    public const PASSWORD_MAX = 128;

    public function __construct(private readonly OperationService $operations, private readonly AuditRecorder $audit) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof PenpotAccessCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $op = (string) $command->get('op');
        if (! in_array($op, PenpotAccessCommand::OPS, true)) {
            throw new DomainError('op_unknown', 'Unknown Penpot operation.', 422, ['field' => 'op']);
        }
        $service = Service::query()->where('organization_id', $command->organizationId)->find((string) $command->get('service_id'));
        if ($service === null || ! PenpotInstances::isPenpot($service)) {
            throw DomainError::notFound('service');
        }
        if (! in_array($service->state, [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED], true)) {
            throw new DomainError('service_state_invalid', 'Heslo lze nastavit jen u běžící instance Penpotu.', 409);
        }
        if ((string) data_get($service->tags, 'penpot.owner_email', '') === '') {
            throw new DomainError('penpot_owner_unknown', 'Instance zatím nemá účet vlastníka; počkejte na dokončení zřízení.', 409);
        }
        $password = (string) $command->get('password', '');
        if (strlen($password) < self::PASSWORD_MIN || strlen($password) > self::PASSWORD_MAX) {
            throw new DomainError('action_param_invalid', 'Heslo musí mít 12–128 znaků.', 422, ['field' => 'password']);
        }
        if (Operation::query()->where('service_id', $service->id)->whereIn('state', [Operation::PENDING, Operation::RUNNING, Operation::WAITING])->exists()) {
            throw new DomainError('operation_in_progress', 'Another operation is still running on this service; wait for it to finish.', 409);
        }
        $scoped = $context->withScope($service->organization_id, $service->project_id);
        $operation = $this->operations->start(
            PenpotOwnerWorkflow::class, 'penpot.owner:'.$service->id.':'.hash('sha256', $command->idempotencyKey()), ['service_id' => $service->id, 'password' => $password],
            $scoped, $service->id, $service->organization_id, null, $service->provider_instance_id, null, true, PenpotAccessCommand::PERMISSION, 'resource',
        );
        $this->audit->record($scoped, 'penpot.owner_password', 'succeeded', ['operation_id' => $operation->id, 'owner_email' => data_get($service->tags, 'penpot.owner_email')], 'service', $service->id);

        return ['operation_id' => $operation->id, 'kind' => $operation->kind, 'state' => ($operation->fresh() ?? $operation)->state];
    }
}
