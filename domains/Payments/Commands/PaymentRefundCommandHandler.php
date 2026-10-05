<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Commands;

use Carbon\CarbonImmutable;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentRefund;
use Onhost\Domain\Payments\OrderPaymentRefunds;
use Onhost\Domain\Payments\PaymentService;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

final class PaymentRefundCommandHandler implements CommandHandler
{
    public function __construct(private readonly OrderPaymentRefunds $refunds, private readonly PaymentService $payments) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof PaymentRefundCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }

        return match ($command->op()) {
            'refund.withdrawal' => (function () use ($command, $context): array {
                $intent = PaymentIntent::query()->findOrFail((string) $command->get('payment_id'));
                $amount = Money::minor((int) $command->get('amount_minor'), (string) $command->get('currency'));
                $done = $this->refunds->refundOnWithdrawal($intent, $amount, CarbonImmutable::parse((string) $command->get('sent_at')), (string) $command->get('reason'), 'withdrawal-refund:'.$command->idempotencyKey(), $context);

                return PaymentService::presentRefund($done['refund'], $intent->refresh(), (string) $done['credit_note']->number);
            })(),
            'refund.confirm' => (function () use ($command, $context): array {
                $refund = $this->payments->confirmRefund(PaymentRefund::query()->findOrFail((string) $command->get('refund_id')), (string) $command->get('reference'), $context);

                return PaymentService::presentRefund($refund, PaymentIntent::query()->find($refund->payment_intent_id), $refund->credit_note_id === null ? null : (string) Invoice::query()->whereKey($refund->credit_note_id)->value('number'));
            })(),
            default => throw new DomainError('payment_refund_op_unknown', "Unknown refund operation {$command->op()}.", 422),
        };
    }
}
