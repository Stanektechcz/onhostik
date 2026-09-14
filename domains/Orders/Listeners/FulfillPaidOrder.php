<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders\Listeners;

use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\OrderFulfilmentService;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Services\PlanChangeService;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * `onhost.order.paid` → provisioning (blueprint §5.2). Services first, domains
 * last (a domain registration is the only irreversible step in an order).
 * Idempotent: items already past `pending` are skipped, so a redelivered event is harmless.
 */
final class FulfillPaidOrder
{
    public function __construct(
        private readonly ServiceService $services,
        private readonly PlanChangeService $planChanges,
        private readonly DomainService $domains,
        private readonly CheckoutService $checkout,
        private readonly OrderFulfilmentService $fulfilment,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    public function handle(OutboxMessage $message): void
    {
        $order = Order::query()->find($message->aggregate_id);
        if ($order === null || ! in_array($order->state, [OrderStateMachine::PAID, OrderStateMachine::PROVISIONING], true)) {
            return;
        }
        $context = CommandContext::system("fulfil order {$order->number}")->withScope($order->organization_id);
        if ($order->state === OrderStateMachine::PAID) {
            $this->checkout->transition($order, OrderStateMachine::PROVISIONING, $context, 'fulfilment started');
        }
        $items = OrderItem::query()->where('order_id', $order->id)->where('state', 'pending')->orderByRaw("case when product_key = 'domain' then 1 else 0 end")->orderBy('created_at')->get();
        foreach ($items as $item) {
            try {
                if ($item->isDomain()) {
                    $this->domains->createFromOrderItem($item, $order, $context);
                } elseif (! empty($item->config['upgrade_of'])) {
                    $this->planChanges->apply($item, $order, $context);
                } else {
                    $this->services->createFromOrderItem($item, $order, $context);
                }
            } catch (DomainError $e) {
                $this->itemFailed($order, $item, $e->getMessage(), $context);
            } catch (Throwable $e) {
                if (self::transient($e)) {
                    throw $e; // a locked database or a deadlock is not the order's fault: the outbox retries the message with backoff
                }
                $this->itemFailed($order, $item, get_class($e).': '.$e->getMessage(), $context);
                report($e);
            }
        }
        $this->fulfilment->recheck($order->id, $context); // lines that settle synchronously (a plan change) settle the order here; provisioned lines settle it on activation
    }

    private static function transient(Throwable $e): bool
    {
        return (bool) preg_match('/database is locked|deadlock|could not serialize|serialization failure|lock wait timeout|connection (refused|reset|timed out)/i', $e->getMessage());
    }

    private function itemFailed(Order $order, OrderItem $item, string $reason, CommandContext $context): void
    {
        $item->forceFill(['state' => 'failed'])->save();
        $this->audit->record($context, 'order.item.failed', 'failed', ['item' => $item->id, 'sku' => $item->sku, 'reason' => $reason], 'order', $order->id);
        $this->outbox->publish(GenericEvent::of('order.fulfilment_failed', 'order', $order->id, ['number' => $order->number, 'item_id' => $item->id, 'sku' => $item->sku, 'reason' => $reason], $order->organization_id));
        $this->fulfilment->recheck($order->id, $context);
    }
}
