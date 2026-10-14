<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Commands;

use Carbon\CarbonImmutable;
use Onhost\Domain\Billing\Models\Withdrawal;
use Onhost\Domain\Billing\WithdrawalPolicy;
use Onhost\Domain\Billing\WithdrawalService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class WithdrawalCommandHandler implements CommandHandler
{
    public function __construct(private readonly WithdrawalService $withdrawals) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if ($context->actorType === 'ai') {
            throw new DomainError('ai_action_forbidden', 'An assistant may not withdraw from a contract.', 403);
        }
        if (($command instanceof WithdrawalCommand || $command instanceof WithdrawalStaffCommand) && ($command->get('topup_id') !== null || ($command instanceof WithdrawalCommand && $command->op() === 'topup'))) {
            WithdrawalPolicy::refuseTopUp(); // H0 (H-R5): no withdrawal from a credit top-up, whoever records it
        }
        if ($command instanceof WithdrawalCommand) { // the consumer, in the panel: the notice is sent now
            $by = $context->actorType === 'user' ? $context->actorId : null;
            $statement = $command->get('statement') !== null ? (string) $command->get('statement') : null;

            return $this->withdrawals->present(match ($command->op()) {
                'service' => $this->withdrawals->withdrawService($this->service($command->organizationId, (string) $command->get('service_id')), $context, Withdrawal::PANEL, CarbonImmutable::now(), $statement, $by, self::method($command)),
                'order' => $this->withdrawals->withdrawOrder($this->order($command->organizationId, (string) $command->get('order_id')), $context, Withdrawal::PANEL, CarbonImmutable::now(), $statement, $by, self::method($command)),
                default => throw new DomainError('op_unknown', 'Unknown withdrawal operation.', 422),
            });
        }
        if ($command instanceof WithdrawalStaffCommand) { // a letter or an e-mail, with the day it was sent
            $organizationId = (string) $command->get('organization_id');
            $sentAt = CarbonImmutable::parse((string) $command->get('sent_at'));
            if ($sentAt->isFuture()) {
                throw new DomainError('withdrawal_sent_in_future', 'The notice cannot have been sent in the future.', 422, ['field' => 'sent_at']);
            }
            $statement = 'Zaznamenal tým: '.mb_substr((string) $command->get('reason', ''), 0, 1900);
            $scoped = $context->withScope($organizationId);

            return $this->withdrawals->present($command->get('service_id') !== null
                ? $this->withdrawals->withdrawService($this->service($organizationId, (string) $command->get('service_id')), $scoped, Withdrawal::STAFF, $sentAt, $statement, $context->actorId, self::method($command))
                : $this->withdrawals->withdrawOrder($this->order($organizationId, (string) $command->get('order_id')), $scoped, Withdrawal::STAFF, $sentAt, $statement, $context->actorId, self::method($command)));
        }
        throw new \LogicException('Unsupported command '.get_class($command));
    }

    /** L-06: the credit only on the consumer's express agreement; anything else is the statutory default (back the way it was paid). */
    private static function method(WithdrawalCommand|WithdrawalStaffCommand $command): string
    {
        return $command->get('refund_method') === Withdrawal::METHOD_CREDIT ? Withdrawal::METHOD_CREDIT : Withdrawal::METHOD_SOURCE;
    }

    private function service(string $organizationId, string $id): Service
    {
        return Service::query()->where('organization_id', $organizationId)->whereKey($id)->first() ?? throw DomainError::notFound('service');
    }

    private function order(string $organizationId, string $id): Order
    {
        if (WithdrawalPolicy::isTopUpReference($id, $organizationId)) {
            WithdrawalPolicy::refuseTopUp(); // H0 (review M2): a top-up named by its document, its payment or itself
        }

        return Order::query()->where('organization_id', $organizationId)->whereKey($id)->first() ?? throw DomainError::notFound('order');
    }
}
