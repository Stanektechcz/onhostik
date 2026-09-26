<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Orders\Models\Cart;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Platform\Commands\CommandContext;
use Throwable;

/**
 * Commerce housekeeping: every cart change with the drawer open produces a quote (valid two hours) and every visitor
 * gets a server cart, so the tables grow with browsing, not with orders. The nightly prune drops open quotes whose
 * validity ended more than a day ago and that no order references, and open carts that expired more than a week ago
 * and were never converted. Accepted quotes and converted carts are the order's paper trail and stay.
 */
final class CommerceHousekeeping
{
    public function __construct(private readonly CheckoutService $checkout) {}

    /**
     * An order nobody paid does not wait for ever: its transfer stayed matchable, so a proforma paid seven months later
     * was provisioned at the prices, the plan version and the VAT rate of the day it was placed. After the deadline the
     * order is cancelled — the proforma is voided and the transfer stops being matched (a late payment then lands with
     * finance as an unmatched line, not as an order).
     */
    public function expireUnpaid(?int $days = null): int
    {
        $days = max(1, $days ?? (int) config('onhost.orders.unpaid_expire_days', 14));
        $expired = 0;
        foreach (Order::query()->where('state', OrderStateMachine::PENDING_PAYMENT)->where('created_at', '<', now()->subDays($days))->orderBy('created_at')->limit(500)->get() as $order) {
            try {
                $this->checkout->transition($order, OrderStateMachine::CANCELLED, CommandContext::system('unpaid order expiry')->withScope($order->organization_id), "nezaplaceno do {$days} dnů");
                $expired++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        // TASK-0021: a credit order nobody approved is unpaid as well; it expires after its own deadline (credit_approval.expire_days)
        return $expired + app(CreditOrderApprovals::class)->expirePending();
    }

    /** @return array{quotes:int, carts:int} */
    public function prune(int $quoteGraceHours = 24, int $cartGraceDays = 7): array
    {
        $quotes = Quote::query()
            ->where('state', 'open')
            ->where('valid_until', '<', now()->subHours(max(1, $quoteGraceHours)))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('orders')->whereColumn('orders.quote_id', 'quotes.id'))
            ->delete();
        $carts = Cart::query()
            ->where('state', 'open')
            ->whereNull('converted_order_id')
            ->where('expires_at', '<', now()->subDays(max(1, $cartGraceDays)))
            ->delete();

        return ['quotes' => (int) $quotes, 'carts' => (int) $carts];
    }
}
