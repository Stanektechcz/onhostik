<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Commands;

use Onhost\Domain\Billing\ChargebackAnalyst;
use Onhost\Domain\Billing\ChargebackService;
use Onhost\Domain\Billing\Models\ChargebackRequest;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class ChargebackCommandHandler implements CommandHandler
{
    public function __construct(private readonly ChargebackService $chargebacks) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if ($command instanceof ChargebackCommand) {
            $service = Service::query()->where('organization_id', $command->organizationId)->whereKey((string) $command->get('service_id'))->first();
            if ($service === null) {
                throw DomainError::notFound('service');
            }

            return match ($command->op()) {
                'request' => $this->chargebacks->present($this->chargebacks->request($service, $context->actorType === 'user' && $context->actorId ? User::query()->find($context->actorId) : null, (string) $command->get('reason', ''), $context)),
                'cancel' => (function () use ($service, $context, $command) {
                    $open = $this->chargebacks->open($service);
                    if ($open === null) {
                        throw new DomainError('chargeback_not_approved', 'There is no approved chargeback request for this service.', 409);
                    }

                    return $this->chargebacks->present($this->chargebacks->cancelService($open, $context, $command->permission()));
                })(),
                default => throw new DomainError('op_unknown', 'Unknown chargeback operation.', 422),
            };
        }
        if ($command instanceof ChargebackStaffCommand) {
            return match ($command->op()) {
                'decide' => (function () use ($command, $context) {
                    $request = ChargebackRequest::query()->find((string) $command->get('chargeback_id'));
                    if ($request === null) {
                        throw DomainError::notFound('chargeback');
                    }

                    return $this->chargebacks->present($this->chargebacks->decide($request, (string) $command->get('decision'), $command->get('reason') !== null ? (string) $command->get('reason') : null, $context));
                })(),
                'analyse' => ['opened' => app(ChargebackAnalyst::class)->run()],
                'settings' => ['percent' => $this->chargebacks->setPercent((int) $command->get('percent'), $context->actorType.':'.($context->actorId ?? 'system'))],
                default => throw new DomainError('op_unknown', 'Unknown chargeback operation.', 422),
            };
        }
        throw new \LogicException('Unsupported command '.get_class($command));
    }
}
