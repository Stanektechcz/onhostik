<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Orders\Models\Cart;
use Onhost\Domain\Orders\Models\Quote;

/**
 * Commerce housekeeping: every cart change with the drawer open produces a quote (valid two hours) and every visitor
 * gets a server cart, so the tables grow with browsing, not with orders. The nightly prune drops open quotes whose
 * validity ended more than a day ago and that no order references, and open carts that expired more than a week ago
 * and were never converted. Accepted quotes and converted carts are the order's paper trail and stay.
 */
final class CommerceHousekeeping
{
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
