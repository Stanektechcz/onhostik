<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders\Listeners;

use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Payments\Events\PaymentSucceeded;

/** After a verified gateway/bank payment for an order: mark the order paid (idempotent) and close the proforma. */
final class SettleOrderPayment
{
    public function __construct(private readonly CheckoutService $checkout) {}

    public function handle(PaymentSucceeded $event): void
    {
        $intent = $event->intent;
        if ($intent->purpose !== 'order' || $intent->reference_type !== 'order') {
            return;
        }
        $order = Order::query()->find($intent->reference_id);
        if ($order === null || in_array($order->state, [OrderStateMachine::CANCELLED], true)) {
            return;
        }
        if ($order->invoice_id !== null) {
            $proforma = Invoice::query()->find($order->invoice_id);
            if ($proforma !== null && $proforma->type === 'proforma' && $proforma->state !== Invoice::PAID) {
                $proforma->forceFill(['state' => Invoice::PAID, 'paid_minor' => $proforma->total_minor, 'paid_at' => now(), 'payment_method' => $intent->method ?? $intent->provider])->save();
            }
        }
        $this->checkout->markPaid($order, $event->context, $intent->method ?? $intent->provider, $intent->id);
    }
}
