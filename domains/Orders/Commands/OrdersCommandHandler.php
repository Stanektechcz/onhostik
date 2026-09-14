<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders\Commands;

use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class OrdersCommandHandler implements CommandHandler
{
    public function __construct(private readonly CheckoutService $checkout) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if ($command instanceof ReviewOrderCommand) { // staff decision on a held order (audit §5f-8)
            $order = Order::query()->find((string) $command->get('order_id'));
            if ($order === null) {
                throw DomainError::notFound('order');
            }
            $reviewed = $this->checkout->review($order, (string) $command->get('decision'), $context->withScope($order->organization_id), $command->get('reason'));

            return ['order_id' => $reviewed->id, 'number' => $reviewed->number, 'state' => $reviewed->state, 'review' => $reviewed->meta['review'] ?? null];
        }
        if (! $command instanceof PlaceOrderCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $organization = Organization::query()->findOrFail($command->organizationId);
        $quote = Quote::query()->find((string) $command->get('quote_id'));
        if ($quote === null) {
            throw new DomainError('quote_not_found', 'The quote does not exist; refresh the cart.', 404, ['field' => 'quote_id']);
        }
        $user = $context->actorType === 'user' && $context->actorId !== null ? User::query()->find($context->actorId) : null;
        $result = $this->checkout->placeOrder($quote, $organization, $user, (array) $command->get('consents', []), (array) $command->get('payment', ['mode' => 'wallet']), $command->idempotencyKey, $context, (string) $command->get('source', 'web'));

        $order = $result['order']->fresh();

        return ['order_id' => $order->id, 'number' => $order->number, 'state' => $order->state, 'redirect_url' => $result['redirect_url'], 'payment_intent_id' => $result['payment_intent_id'], 'bank_instructions' => $result['bank_instructions'], 'total' => (int) $order->total_minor, 'subtotal' => (int) $order->subtotal_minor, 'discount' => (int) $order->discount_minor, 'tax' => (int) $order->tax_minor, 'currency' => $order->currency, 'payment_mode' => $order->payment_mode];
    }
}
