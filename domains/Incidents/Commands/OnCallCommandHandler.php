<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents\Commands;

use Onhost\Domain\Incidents\Models\OnCallAlert;
use Onhost\Domain\Incidents\OnCallService;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class OnCallCommandHandler implements CommandHandler
{
    public function __construct(private readonly OnCallService $oncall) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        assert($command instanceof OnCallCommand);
        $by = (string) ($context->actorId ?? $context->actorType);
        if ($command->op() === 'test') {
            return OnCallService::present($this->oncall->test($context));
        }
        $alert = OnCallAlert::query()->find((string) $command->get('alert_id')) ?? throw DomainError::notFound('oncall_alert');

        return OnCallService::present(match ($command->op()) {
            'ack' => $this->oncall->acknowledge($alert, $by, $context),
            'resolve' => $this->oncall->resolve($alert, $by, $context),
            default => throw new DomainError('oncall_op_invalid', 'Unknown on-call operation.', 422, ['field' => 'op']),
        });
    }
}
