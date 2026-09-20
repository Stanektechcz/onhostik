<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders;

use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Platform\Commands\CommandContext;

/**
 * Derives the order state from its items (blueprint §5.2): every item active →
 * ACTIVE, some → PARTIALLY_ACTIVE, none → FAILED. Called by every domain that
 * finishes or fails an item (services, addons, domains).
 */
final class OrderFulfilmentService
{
    public function __construct(private readonly CheckoutService $checkout, private readonly OrderSettlement $settlement) {}

    public function recheck(?string $orderId, CommandContext $context): void
    {
        if ($orderId === null) {
            return;
        }
        $order = Order::query()->find($orderId);
        if ($order === null || ! in_array($order->state, [OrderStateMachine::PROVISIONING, OrderStateMachine::PARTIALLY_ACTIVE], true)) {
            return;
        }
        $states = OrderItem::query()->where('order_id', $order->id)->pluck('state')->all();
        if ($states === [] || in_array('pending', $states, true) || in_array('provisioning', $states, true)) {
            return;
        }
        $active = count(array_filter($states, fn ($s) => $s === 'active'));
        $target = $active === count($states) ? OrderStateMachine::ACTIVE : ($active > 0 ? OrderStateMachine::PARTIALLY_ACTIVE : OrderStateMachine::FAILED);
        if ($order->state !== $target && in_array($target, OrderStateMachine::machine()->nextStates($order->state), true)) {
            $this->checkout->transition($order, $target, $context->withScope($order->organization_id), 'fulfilment');
        }
        $this->settlement->settle($order->id, $context); // every line is final: delivered lines are charged, the rest goes back to the customer
    }

    public function itemFor(?string $itemId): ?OrderItem
    {
        return $itemId === null ? null : OrderItem::query()->find($itemId);
    }
}
