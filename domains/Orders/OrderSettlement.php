<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Loyalty\LoyaltyRedemptions;
use Onhost\Domain\Loyalty\RedemptionShare;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\WalletLedger\Models\WalletHold;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * What an order's money becomes once its lines reached their end (blueprint §5.2).
 *
 * A paid order RESERVES its total in the customer's credit; a reservation is not a charge. Nothing ever captured it, so
 * the hold ran out after a day, the money went back to the customer while the service kept running, revenue was never
 * booked and the same credit could buy again the next morning. And a line that could not be delivered gave nothing back:
 * the mail promised „platbu vracíme na kredit“ and no code did it.
 *
 * Delivered lines are captured (revenue by family, VAT apart). Lines that could not be delivered are given back: their
 * share of the reservation is released, the tax document is corrected by a credit note for exactly those lines, and the
 * customer is told. A postpaid order has no money to capture — its claim is the receivable; its reservation keeps the
 * credit line occupied until the invoice is paid.
 */
final class OrderSettlement
{
    private const OPEN = ['pending', 'provisioning'];

    public function __construct(
        private readonly WalletService $wallets,
        private readonly InvoiceService $invoices,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    /**
     * Idempotent; does nothing while a line is still being delivered.
     *
     * @return array{captured_minor:int, returned_minor:int, credit_note_id:?string}|null
     */
    public function settle(Order|string $order, CommandContext $context): ?array
    {
        $orderId = $order instanceof Order ? $order->id : $order;

        return DB::transaction(function () use ($orderId, $context) {
            $order = Order::query()->lockForUpdate()->find($orderId);
            if ($order === null || isset($order->meta['settlement']) || ! in_array($order->state, [OrderStateMachine::ACTIVE, OrderStateMachine::PARTIALLY_ACTIVE, OrderStateMachine::FAILED], true)) {
                return $order?->meta['settlement'] ?? null;
            }
            $items = OrderItem::query()->where('order_id', $order->id)->get();
            // G3 (G-R2): a points discount is a line of its own that is applied, not delivered; its share follows the lines it discounted
            $redemption = $items->first(fn (OrderItem $i) => LoyaltyRedemptions::isRedemption($i));
            $items = $items->reject(fn (OrderItem $i) => LoyaltyRedemptions::isRedemption($i))->values();
            if ($items->isEmpty() || $items->contains(fn (OrderItem $i) => in_array($i->state, self::OPEN, true))) {
                return null;
            }
            $context = $context->withScope($order->organization_id);
            $delivered = $items->where('state', 'active');
            $undelivered = $items->where('state', 'failed');
            $captured = (int) $delivered->sum('total_minor');
            $returned = (int) $undelivered->sum('total_minor');
            $share = $redemption !== null ? RedemptionShare::settle($redemption, $items, $undelivered) : null;
            if ($share !== null) { // what was delivered is charged after its share of the points, what goes back goes back after its own
                $captured -= $share['kept'];
                $returned -= $share['back'];
            }
            $postpaid = $order->payment_mode === 'postpaid';
            $hold = $order->wallet_hold_id !== null ? WalletHold::query()->find($order->wallet_hold_id) : null;

            if (! $postpaid && $hold !== null) {
                $this->takeDelivered($order, $hold, $delivered->all(), $captured, $context, $share);
            } elseif ($postpaid && $hold !== null && $captured === 0 && $hold->isActive()) {
                $this->wallets->release($hold, "order {$order->number}: nothing was delivered", $context);
            }

            $creditNote = $returned > 0 ? $this->credit($order, $undelivered->pluck('id')->all(), $context) : null;
            if ($returned > 0) {
                OrderItem::query()->whereIn('id', $undelivered->pluck('id')->all())->update(['state' => 'refunded']);
            }
            $settlement = ['captured_minor' => $captured, 'returned_minor' => $returned, 'credit_note_id' => $creditNote?->id, 'at' => now()->toIso8601String()];
            $order->forceFill(['meta' => array_merge((array) $order->meta, ['settlement' => $settlement])])->save();
            $this->audit->record($context, 'order.settle', 'succeeded', ['number' => $order->number, 'captured' => $captured, 'returned' => $returned, 'credit_note' => $creditNote?->number], 'order', $order->id);
            if ($returned > 0) { // the customer hears it from us: what could not be delivered, how much came back and where it is
                $this->outbox->publish(GenericEvent::of('order.refunded', 'order', $order->id, [
                    'number' => $order->number, 'amount' => Money::minor($returned, $order->currency), 'to' => $postpaid ? 'invoice' : 'credit', 'credit_note' => $creditNote?->number,
                    'items' => $undelivered->map(fn (OrderItem $i) => $i->name)->values()->all(), 'nothing_delivered' => $captured === 0,
                ], $order->organization_id));
            }

            return $settlement;
        }, 3);
    }

    /**
     * A postpaid order keeps its reservation until its invoice is paid; called before the invoice is settled from credit
     * (the reservation would otherwise stand in the way of the very payment it waits for) and when the invoice turns paid.
     */
    public function releaseReservation(Invoice $invoice, CommandContext $context): void
    {
        if ($invoice->order_id === null || ! $invoice->bookedAtIssue()) {
            return;
        }
        $order = Order::query()->find($invoice->order_id);
        $hold = $order?->wallet_hold_id !== null ? WalletHold::query()->find($order->wallet_hold_id) : null;
        if ($order !== null && $order->payment_mode === 'postpaid' && $hold !== null && $hold->isActive()) {
            $this->wallets->release($hold, "invoice {$invoice->number} paid", $context->withScope($order->organization_id));
        }
    }

    /** Orders whose lines are all final and whose reservation still stands: a worker that died between the two. */
    public function sweep(int $limit = 200): int
    {
        $settled = 0;
        $orders = Order::query()->whereIn('state', [OrderStateMachine::ACTIVE, OrderStateMachine::PARTIALLY_ACTIVE, OrderStateMachine::FAILED])
            ->whereNotNull('wallet_hold_id')->whereIn('wallet_hold_id', WalletHold::query()->where('state', 'active')->where('purpose', 'order')->select('id'))
            ->orderBy('updated_at')->limit($limit)->get();
        foreach ($orders as $order) {
            if (! isset($order->meta['settlement']) && $this->settle($order, CommandContext::system("settle order {$order->number}")) !== null) {
                $settled++;
            }
        }

        return $settled;
    }

    /**
     * @param  list<OrderItem>  $delivered
     * @param  array{back:int, back_tax:int, kept:int, kept_tax:int, eligible:list<string>}|null  $share  the points discount that stays with the delivered lines (G3)
     */
    private function takeDelivered(Order $order, WalletHold $hold, array $delivered, int $captured, CommandContext $context, ?array $share = null): void
    {
        if ($captured === 0) {
            if ($hold->isActive()) {
                $this->wallets->release($hold, "order {$order->number}: nothing was delivered", $context);
            }

            return;
        }
        $tax = Money::minor((int) array_sum(array_map(fn (OrderItem $i) => (int) $i->tax_minor, $delivered)) - (int) ($share['kept_tax'] ?? 0), $order->currency);
        $split = [];
        $discounted = []; // the revenue of the delivered lines the points discounted, per family (a domain never is one)
        foreach ($delivered as $item) {
            $family = (string) ($item->config['family'] ?? ($item->isDomain() ? 'domain' : 'services'));
            $split[$family] = ($split[$family] ?? 0) + ((int) $item->total_minor - (int) $item->tax_minor);
            if (in_array((string) $item->id, $share['eligible'] ?? [], true)) {
                $discounted[$family] = ($discounted[$family] ?? 0) + ((int) $item->total_minor - (int) $item->tax_minor);
            }
        }
        $split = self::lessDiscount($split, $discounted, (int) ($share['kept'] ?? 0) - (int) ($share['kept_tax'] ?? 0));
        $amount = Money::minor($captured, $order->currency);
        if ($hold->isActive()) {
            $this->wallets->capture($hold, (string) array_key_first($split), $context, $amount, $tax, "Objednávka {$order->number}", $split);

            return;
        }
        if ($hold->state === 'captured') {
            return;
        }
        // the reservation is gone (an order retried after it had failed, a hold somebody released by hand): what was delivered is
        // still owed — taken from the credit if it is there, handed to finance if it is not
        try {
            $this->wallets->charge($order->organization_id, $amount, (string) array_key_first($split), "order-settle:{$order->id}", $context, 'order', $order->id, $tax, enforceBudget: false, revenueSplit: $split);
        } catch (DomainError $e) {
            $this->outbox->publish(GenericEvent::of('order.settlement_failed', 'order', $order->id, ['number' => $order->number, 'amount' => $amount, 'reason' => $e->error], $order->organization_id));
            $this->audit->record($context, 'order.settle', 'failed', ['number' => $order->number, 'amount' => $captured, 'reason' => $e->error], 'order', $order->id);
        }
    }

    /**
     * The revenue of the delivered families less the points discount that stays with them (G3): taken from the families of the lines
     * the points discounted, in proportion to their revenue; the last one takes the remainder, so the split still adds up to the
     * captured net (WalletService trusts only a split that does).
     *
     * @param  array<string,int>  $split  revenue per family of every delivered line
     * @param  array<string,int>  $discounted  revenue per family of the delivered lines the points discounted
     * @return array<string,int>
     */
    private static function lessDiscount(array $split, array $discounted, int $net): array
    {
        $discounted = array_filter($discounted, fn (int $v) => $v > 0);
        $base = (int) array_sum($discounted);
        if ($net <= 0 || $base <= 0) {
            return $split;
        }
        $left = $net;
        $keys = array_keys($discounted);
        foreach ($keys as $n => $family) {
            $cut = $n === count($keys) - 1 ? $left : intdiv($discounted[$family] * $net, $base);
            $split[$family] -= $cut;
            $left -= $cut;
        }

        return $split;
    }

    /** @param list<string> $itemIds */
    private function credit(Order $order, array $itemIds, CommandContext $context): ?Invoice
    {
        $document = Invoice::query()->where('order_id', $order->id)->whereIn('type', ['statement', 'invoice'])->orderBy('created_at')->first();
        if ($document === null || ! $document->isIssued()) {
            return null;
        }
        $lineIds = $document->lines()->whereIn('order_item_id', $itemIds)->pluck('id')->all();
        if ($lineIds === []) {
            return null;
        }
        $all = $document->lines()->count() === count($lineIds);

        return $this->invoices->creditNote($document, "Objednávka {$order->number}: nezřízené položky", $context, $all ? null : $lineIds);
    }
}
