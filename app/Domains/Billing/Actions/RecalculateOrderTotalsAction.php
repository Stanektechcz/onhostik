<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Models\Order;
use Brick\Math\RoundingMode;
use Brick\Money\Money;

/**
 * Recomputes an order's subtotal / VAT / total from its current line items.
 *
 * Needed once admins can add, remove or reprice lines (audit G95): editing a
 * line without this would leave the order header showing the old amount, and
 * the header is what gets invoiced.
 *
 * The VAT rate is taken from the lines themselves, not re-resolved from the
 * customer — an order that was placed under one rate must keep that rate even
 * if the customer's country or VAT status changes later.
 */
final class RecalculateOrderTotalsAction
{
    public function execute(Order $order): Order
    {
        $order->load('items');

        $currency = $order->currency->value;
        $subtotal = Money::zero($currency);

        foreach ($order->items as $item) {
            $subtotal = $subtotal->plus($item->total);
        }

        // Weighted VAT: each line may legitimately carry its own rate.
        $tax = Money::zero($currency);

        foreach ($order->items as $item) {
            $tax = $tax->plus(
                $item->total->multipliedBy((float) $item->vat_rate / 100, RoundingMode::HALF_UP)
            );
        }

        $discount = $order->discount_amount ?? Money::zero($currency);
        $net      = $subtotal->minus($discount);

        if ($net->isNegative()) {
            $net = Money::zero($currency);
        }

        $order->update([
            'subtotal'   => $subtotal,
            'tax_amount' => $tax,
            'total'      => $net->plus($tax),
        ]);

        return $order->fresh() ?? $order;
    }
}
