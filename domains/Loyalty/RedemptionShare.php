<?php

declare(strict_types=1);

namespace Onhost\Domain\Loyalty;

use Illuminate\Support\Collection;
use Onhost\Domain\Invoicing\Models\InvoiceLine;
use Onhost\Domain\Orders\Models\OrderItem;

/**
 * The redemption line follows the lines it discounted (owner decision G-R2: a refund returns points proportionally).
 *
 * Points discount the order as one line of their own. When some of the lines they discounted are given back — not delivered,
 * a withdrawal, the unused part of a cancelled service, a credit note by hand — the same share of the redemption line goes back
 * with them: the customer gets the money they paid for those lines (their list total less their share of the discount) and the
 * points of that share. Giving the full list total back would have turned points into credit; keeping the whole discount on what
 * stays would have pushed it past the cap.
 *
 * The share is cumulative: of the discounted lines' total `E`, `credited` has been given back so far, so the redemption line
 * (`whole`) owes `whole × credited ∕ E` in all, rounded down; the last credit — everything given back — takes the rest. What
 * earlier credit notes took is subtracted, so nothing is credited twice and nothing is lost to rounding.
 */
final class RedemptionShare
{
    /** The part of the redemption line (gross, positive) that goes with `credited` of the discounted lines' total `eligible`. */
    public static function target(int $whole, int $eligible, int $credited): int
    {
        if ($whole <= 0 || $eligible <= 0 || $credited <= 0) {
            return 0;
        }

        return $credited >= $eligible ? $whole : intdiv($whole * $credited, $eligible);
    }

    /**
     * The credit-note row for the redemption line that has to go with `$rows` (rows of a credit note being written for named
     * lines), or null. `$before` is what earlier credit notes took per line (InvoiceService::creditedByLine).
     *
     * @param  Collection<int, InvoiceLine>  $lines  every line of the original document
     * @param  list<array<string,mixed>>  $rows
     * @param  array<string, array{net:int, tax:int, total:int}>  $before
     * @return ?array<string,mixed>
     */
    public static function companion(Collection $lines, array $rows, array $before): ?array
    {
        $redemption = $lines->first(fn (InvoiceLine $l) => $l->sku === LoyaltyRedemptions::SKU && (int) $l->total_minor < 0);
        if ($redemption === null || collect($rows)->contains(fn (array $r) => ($r['corrects_line_id'] ?? null) === $redemption->id)) {
            return null; // no redemption on the document, or this credit note credits the line itself
        }
        $eligible = self::eligibleLines($lines, $redemption);
        if ($eligible === []) {
            return null;
        }
        $now = 0;
        foreach ($rows as $row) {
            if (in_array($row['corrects_line_id'] ?? null, $eligible, true)) {
                $now += -(int) $row['total'];
            }
        }
        if ($now <= 0) {
            return null;
        }
        $total = 0;
        $credited = $now;
        foreach ($lines as $line) {
            if (in_array($line->id, $eligible, true)) {
                $total += (int) $line->total_minor;
                $credited += (int) ($before[$line->id]['total'] ?? 0);
            }
        }
        $whole = -(int) $redemption->total_minor;
        $wholeTax = -(int) $redemption->tax_minor;
        $done = -(int) ($before[$redemption->id]['total'] ?? 0);
        $doneTax = -(int) ($before[$redemption->id]['tax'] ?? 0);
        $target = self::target($whole, $total, $credited);
        $share = $target - $done;
        if ($share <= 0) {
            return null;
        }
        $tax = ($target === $whole ? $wholeTax : (int) round($wholeTax * $target / $whole)) - $doneTax;
        $net = $share - $tax;

        return [
            'qty' => 1, 'unit_net' => $net, 'discount' => 0, 'sku' => $redemption->sku, 'description' => 'Dobropis: '.$redemption->description, 'unit' => $redemption->unit,
            'net' => $net, 'tax_rate' => $redemption->tax_rate, 'tax_category' => $redemption->tax_category, 'tax' => $tax, 'total' => $share,
            'period_from' => $redemption->period_from?->toDateString(), 'period_to' => $redemption->period_to?->toDateString(),
            'service_id' => null, 'order_item_id' => $redemption->order_item_id, 'corrects_line_id' => $redemption->id,
        ];
    }

    /**
     * The settlement of an order whose lines ended: how much of the redemption stays with the delivered lines and how much goes
     * back with the undelivered ones (gross and VAT, positive numbers).
     *
     * @param  Collection<int, OrderItem>  $items  the order's lines without the redemption line
     * @param  Collection<int, OrderItem>  $undelivered
     * @return array{back:int, back_tax:int, kept:int, kept_tax:int, eligible:list<string>}
     */
    public static function settle(OrderItem $redemption, Collection $items, Collection $undelivered): array
    {
        $whole = -(int) $redemption->total_minor;
        $wholeTax = -(int) $redemption->tax_minor;
        $eligible = array_map('strval', (array) data_get($redemption->config, 'loyalty.eligible_lines', []));
        $isEligible = fn (OrderItem $i) => in_array((string) ($i->config['line_id'] ?? ''), $eligible, true);
        $total = (int) $items->filter($isEligible)->sum('total_minor');
        $back = self::target($whole, $total, (int) $undelivered->filter($isEligible)->sum('total_minor'));
        $backTax = $back === $whole ? $wholeTax : ($whole > 0 ? (int) round($wholeTax * $back / $whole) : 0);

        return ['back' => $back, 'back_tax' => $backTax, 'kept' => $whole - $back, 'kept_tax' => $wholeTax - $backTax, 'eligible' => $items->filter($isEligible)->pluck('id')->map(fn ($id) => (string) $id)->values()->all()];
    }

    /**
     * The document lines the redemption discounted: the lines of the order items whose cart line the quote named.
     *
     * @param  Collection<int, InvoiceLine>  $lines
     * @return list<string> invoice line ids
     */
    private static function eligibleLines(Collection $lines, InvoiceLine $redemption): array
    {
        $item = $redemption->order_item_id !== null ? OrderItem::query()->find($redemption->order_item_id) : null;
        $wanted = array_map('strval', (array) data_get($item?->config, 'loyalty.eligible_lines', []));
        if ($wanted === []) {
            return [];
        }
        $itemIds = $lines->pluck('order_item_id')->filter()->all();
        $lineOf = OrderItem::query()->whereIn('id', $itemIds)->get()->mapWithKeys(fn (OrderItem $i) => [$i->id => (string) ($i->config['line_id'] ?? '')]);

        return $lines->filter(fn (InvoiceLine $l) => $l->order_item_id !== null && $l->id !== $redemption->id && in_array($lineOf[$l->order_item_id] ?? '', $wanted, true))->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
    }
}
