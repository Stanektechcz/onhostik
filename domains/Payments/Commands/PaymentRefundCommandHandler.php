<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Commands;

use Carbon\CarbonImmutable;
use Onhost\Domain\Billing\Models\Withdrawal;
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
                $done = $this->refunds->refundOnWithdrawal($intent, $amount, CarbonImmutable::parse((string) $command->get('sent_at')), (string) $command->get('reason'), 'withdrawal-refund:'.$command->idempotencyKey(), $context, $command->requiresApproval(), (string) $command->get('ticket_id', ''));

                return PaymentService::presentRefund($done['refund'], $intent->refresh(), (string) $done['credit_note']->number);
            })(),
            'refund.withdrawal_payout' => (function () use ($command, $context): array {
                $withdrawal = Withdrawal::query()->findOrFail((string) $command->get('withdrawal_id'));
                $done = $this->refunds->payoutWithdrawal($withdrawal, 'withdrawal-payout:'.$command->idempotencyKey(), $context, $command->requiresApproval());

                return PaymentService::presentRefund($done['refund'], PaymentIntent::query()->find($done['refund']->payment_intent_id), $done['credit_note'] === null ? null : (string) $done['credit_note']->number);
            })(),
            'refund.statutory' => (function () use ($command, $context): array {
                $intent = PaymentIntent::query()->findOrFail((string) $command->get('payment_id'));
                $amount = Money::minor((int) $command->get('amount_minor'), (string) $command->get('currency'));
                $done = $this->refunds->refundStatutory($intent, $amount, (string) $command->get('basis'), (string) $command->get('reason'), 'statutory-refund:'.$command->idempotencyKey(), $context, $command->requiresApproval(),
                    (string) $command->get('ticket_id', ''), $command->get('credit_note_id') !== null ? (string) $command->get('credit_note_id') : null);

                return PaymentService::presentRefund($done['refund'], $intent->refresh(), (string) $done['credit_note']->number);
            })(),
            'refund.confirm' => (function () use ($command, $context): array {
                $refund = $this->payments->confirmRefund(PaymentRefund::query()->findOrFail((string) $command->get('refund_id')), (string) $command->get('reference'), $context);

                return PaymentService::presentRefund($refund, PaymentIntent::query()->find($refund->payment_intent_id), $refund->credit_note_id === null ? null : (string) Invoice::query()->whereKey($refund->credit_note_id)->value('number'));
            })(),
            'refund.cancel' => (function () use ($command, $context): array {
                $refund = $this->payments->cancelRefund(PaymentRefund::query()->findOrFail((string) $command->get('refund_id')), $context);

                return PaymentService::presentRefund($refund, PaymentIntent::query()->find($refund->payment_intent_id), $refund->credit_note_id === null ? null : (string) Invoice::query()->whereKey($refund->credit_note_id)->value('number'));
            })(),
            default => throw new DomainError('payment_refund_op_unknown', "Unknown refund operation {$command->op()}.", 422),
        };
    }
}
