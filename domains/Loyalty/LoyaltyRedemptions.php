<?php

declare(strict_types=1);

namespace Onhost\Domain\Loyalty;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Loyalty\Models\LoyaltyPoint;
use Onhost\Domain\Loyalty\Models\LoyaltyRedemption;
use Onhost\Domain\Orders\Models\Cart;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Tax\CnbRates;
use Onhost\Domain\Tax\Models\ExchangeRate;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Currency;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Owner decision G-R2: loyalty points can be redeemed for a discount — only when the customer asks (`loyalty.redeem`), always
 * as a line of its own on the order and its document, never folded into a price.
 *
 * - 1 point = 1 CZK off the price before VAT (`config('loyalty.redeem')`); an order in another currency gets the CZK value at
 *   the Czech National Bank's rate of the day, and no rate means no redemption (never a guessed one).
 * - At least `min_points`; never more than the organization has free (its balance less what unpaid orders reserved).
 * - Only lines points may discount count: not a domain (it stays at its list price), not a plan change (it carries no
 *   discounts), and a credit top-up is never a cart line. Every discount on those lines together — promo code, commitment,
 *   streak and points — stays within `cap_pct` of their list price before VAT: points fill only what is left.
 * - The quote prices it, the order reserves it (under a lock on the organization, so two orders cannot spend the same points),
 *   the payment consumes it, a cancelled unpaid order releases it. A credit note on the order's document gives the points back
 *   in the proportion it credited the redemption line (RedemptionShare keeps that line in step with the lines it discounted).
 */
final class LoyaltyRedemptions
{
    public const PRODUCT = 'loyalty';

    public const SKU = 'loyalty-redeem';

    public const LINE_ID = 'loyalty';

    /** the state of the order line: it is applied with the order, never provisioned, never "pending" */
    public const ITEM_STATE = 'applied';

    public const RULE = 'redeem';

    public const RETURN_RULE = 'redeem.return';

    public function __construct(
        private readonly LoyaltyService $loyalty,
        private readonly CnbRates $rates,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    public static function minPoints(): int
    {
        return max(1, (int) config('loyalty.redeem.min_points', 100));
    }

    public static function capPct(): int
    {
        return max(0, min(100, (int) config('loyalty.redeem.cap_pct', 20)));
    }

    public static function isRedemption(OrderItem|array $item): bool
    {
        return ($item instanceof OrderItem ? $item->product_key : ($item['product_key'] ?? null)) === self::PRODUCT;
    }

    /** Points reserved by orders that are not paid yet. */
    public function reserved(string $organizationId): int
    {
        return (int) LoyaltyRedemption::query()->where('organization_id', $organizationId)->where('state', LoyaltyRedemption::RESERVED)->sum('points');
    }

    /** What the organization may redeem now: its balance less what unpaid orders hold. */
    public function available(string $organizationId): int
    {
        return max(0, $this->loyalty->points($organizationId) - $this->reserved($organizationId));
    }

    /** @return array<string,mixed> what the panel and the cart show next to the balance */
    public function summary(string $organizationId): array
    {
        return [
            'available' => $this->available($organizationId), 'reserved' => $this->reserved($organizationId), 'min_points' => self::minPoints(), 'cap_pct' => self::capPct(),
            'point_value' => Money::minor(max(1, (int) config('loyalty.redeem.point_value_czk_minor', 100)), 'CZK'),
        ];
    }

    /**
     * `loyalty.redeem`: the customer's choice for their cart. 0 removes it. The cart only remembers the wish; the quote decides how
     * much of it applies, the order reserves it.
     *
     * @return array<string,mixed>
     */
    public function request(Organization $organization, Cart $cart, int $points, CommandContext $context): array
    {
        if ($points < 0) {
            throw new DomainError('loyalty_points_invalid', 'Počet bodů nemůže být záporný.', 422, ['field' => 'points']);
        }
        $available = $this->available($organization->id);
        if ($points > 0 && $points < self::minPoints()) {
            throw new DomainError('loyalty_below_minimum', 'Uplatnit lze nejméně '.self::minPoints().' bodů.', 422, ['field' => 'points', 'min_points' => self::minPoints(), 'available' => $available]);
        }
        if ($points > $available) {
            throw new DomainError('loyalty_points_unavailable', "K dispozici máte {$available} bodů.", 422, ['field' => 'points', 'available' => $available, 'min_points' => self::minPoints()]);
        }
        $cart->forceFill(['loyalty_points' => $points > 0 ? $points : null, 'loyalty_organization_id' => $points > 0 ? $organization->id : null])->save(); // the choice belongs to this organization only
        $this->audit->record($context->withScope($organization->id), 'loyalty.redeem', 'succeeded', ['cart' => $cart->id, 'points' => $points], 'organization', $organization->id);

        return ['cart_id' => $cart->id, 'points' => $points, 'available' => $available, 'min_points' => self::minPoints(), 'cap_pct' => self::capPct()];
    }

    /** The points a cart asks to redeem for this organization: a choice made for another organization of the same person is no choice here. */
    public static function requestedOn(Cart $cart, ?Organization $organization): int
    {
        return $organization !== null && $cart->loyalty_organization_id === $organization->id ? max(0, (int) $cart->loyalty_points) : 0;
    }

    /**
     * The redemption line of a quote, or why there is none. `$lines` are the quote's priced lines (Money amounts), before tax.
     *
     * @param  list<array<string,mixed>>  $lines
     * @return array{line:?array<string,mixed>, info:array<string,mixed>}
     */
    public function price(?Organization $organization, int $requested, array $lines, Currency $currency, string $locale = 'cs'): array
    {
        $info = ['requested' => max(0, $requested), 'applied' => 0, 'value' => 0, 'available' => 0, 'max_points' => 0, 'min_points' => self::minPoints(), 'cap_pct' => self::capPct(), 'reason' => null];
        if ($organization === null || $requested <= 0) {
            return ['line' => null, 'info' => $info + ['eligible' => []]];
        }
        $info['available'] = $this->available($organization->id);
        $excluded = array_map('strval', (array) config('loyalty.redeem.excluded_families', ['domain']));
        $list = 0;
        $discounted = 0;
        $eligible = [];
        foreach ($lines as $line) {
            $net = $line['net'] instanceof Money ? $line['net']->minor : (int) $line['net'];
            $discount = $line['discount'] instanceof Money ? $line['discount']->minor : (int) $line['discount'];
            if (in_array((string) ($line['family'] ?? ''), $excluded, true) || ($line['product_key'] ?? '') === 'domain' || ! empty($line['config']['plan_change']) || self::isRedemption($line) || $net <= 0) {
                continue;
            }
            $list += $net + $discount;
            $discounted += $discount;
            $eligible[] = (string) $line['line_id'];
        }
        $room = max(0, intdiv($list * self::capPct(), 100) - $discounted);
        $rate = null;
        if ($currency !== Currency::CZK) {
            $rate = $this->rates->rateFor($currency->value, now('Europe/Prague'));
            if ($rate === null) {
                return ['line' => null, 'info' => array_merge($info, ['reason' => 'rate_unknown', 'eligible' => $eligible])];
            }
        }
        $info['max_points'] = $this->pointsWorth($room, $rate);
        $applied = min($requested, $info['available'], $info['max_points']);
        if ($applied < self::minPoints()) {
            $reason = $eligible === [] ? 'nothing_eligible' : ($info['available'] < self::minPoints() ? 'unavailable' : ($info['max_points'] < self::minPoints() ? 'cap' : 'below_minimum'));

            return ['line' => null, 'info' => array_merge($info, ['reason' => $reason, 'eligible' => $eligible])];
        }
        $value = $this->valueOf($applied, $rate);
        $cs = $locale !== 'en';
        $line = [
            'line_id' => self::LINE_ID, 'sku' => self::SKU, 'product_key' => self::PRODUCT, 'plan_key' => null, 'plan_version_id' => null, 'price_id' => null,
            'name' => $cs ? "Sleva za věrnostní body ({$applied} bodů)" : "Loyalty points discount ({$applied} points)",
            'qty' => 1, 'period' => 'once', 'unit_net' => Money::minor(-$value, $currency), 'discount' => Money::zero($currency), 'net' => Money::minor(-$value, $currency), 'renewal_net' => Money::zero($currency),
            'product_class' => 'esd', 'family' => self::PRODUCT,
            'config' => ['line_id' => self::LINE_ID, 'loyalty' => array_filter(['points' => $applied, 'value_minor' => $value, 'eligible_lines' => $eligible, 'rate_micro' => $rate?->rate_micro, 'rate_amount' => $rate?->amount, 'rate_valid_on' => $rate?->valid_on?->format('Y-m-d')], fn ($v) => $v !== null)],
            'entitlements' => null,
        ];

        return ['line' => $line, 'info' => array_merge($info, ['applied' => $applied, 'value' => $value, 'eligible' => $eligible])];
    }

    /** How many whole points a discount of `$minor` (order currency) is worth. */
    private function pointsWorth(int $minor, ?ExchangeRate $rate): int
    {
        $czkPerPoint = max(1, (int) config('loyalty.redeem.point_value_czk_minor', 100));
        if ($rate === null) {
            return intdiv(max(0, $minor), $czkPerPoint);
        }

        // minor (foreign) → CZK haléře = minor × rate_micro / (amount × 10^6); rounded down, so the value never exceeds the room
        return intdiv(max(0, $minor) * $rate->rate_micro, max(1, $rate->amount) * 1_000_000 * $czkPerPoint);
    }

    /** The discount `$points` are worth in the order currency, rounded down (a point is never worth more than one crown). */
    private function valueOf(int $points, ?ExchangeRate $rate): int
    {
        $czk = $points * max(1, (int) config('loyalty.redeem.point_value_czk_minor', 100));

        return $rate === null ? $czk : intdiv($czk * max(1, $rate->amount) * 1_000_000, max(1, $rate->rate_micro));
    }

    /**
     * Placement, inside the order's transaction: the points the quote priced are reserved for this order. The organization row is
     * locked, so two orders placed at once take turns and the second one sees the first one's reservation.
     */
    public function reserve(Order $order, Quote $quote, ?User $user, CommandContext $context): ?LoyaltyRedemption
    {
        $line = collect((array) $quote->lines)->first(fn ($l) => is_array($l) && self::isRedemption($l));
        if ($line === null) {
            return null;
        }
        $points = (int) data_get($line, 'config.loyalty.points', 0);
        Organization::query()->whereKey($order->organization_id)->lockForUpdate()->first();
        $available = $this->available($order->organization_id);
        if ($points < self::minPoints() || $points > $available) {
            throw new DomainError('loyalty_points_unavailable', 'Body z košíku už nejsou k dispozici (uplatnila je jiná objednávka nebo propadly); obnovte košík.', 409, ['field' => 'loyalty_points', 'available' => $available, 'points' => $points]);
        }
        $redemption = LoyaltyRedemption::query()->create([
            'organization_id' => $order->organization_id, 'order_id' => $order->id, 'quote_id' => $quote->id, 'user_id' => $user?->id, 'state' => LoyaltyRedemption::RESERVED,
            'points' => $points, 'value_minor' => (int) data_get($line, 'config.loyalty.value_minor', 0), 'currency' => $order->currency,
            'rate_micro' => data_get($line, 'config.loyalty.rate_micro'), 'rate_amount' => data_get($line, 'config.loyalty.rate_amount'), 'rate_valid_on' => data_get($line, 'config.loyalty.rate_valid_on'),
            'reserved_at' => now(),
        ]);
        if ($user !== null) { // the wish was for this order: the next cart starts without it (nothing is redeemed nobody asked for)
            Cart::query()->where('user_id', $user->id)->where('state', 'open')->where('loyalty_organization_id', $order->organization_id)->update(['loyalty_points' => null, 'loyalty_organization_id' => null]);
        }
        $this->audit->record($context->withScope($order->organization_id), 'loyalty.redeem.reserve', 'succeeded', ['order' => $order->number, 'points' => $points, 'value' => Money::minor($redemption->value_minor, $order->currency)], 'order', $order->id);

        return $redemption;
    }

    /** Payment: the reserved points are spent — a row of their own in the history, once per order. */
    public function consume(Order $order, CommandContext $context): void
    {
        $redemption = LoyaltyRedemption::query()->where('order_id', $order->id)->lockForUpdate()->first();
        if ($redemption === null || $redemption->state !== LoyaltyRedemption::RESERVED) {
            return;
        }
        try {
            DB::transaction(fn () => LoyaltyPoint::query()->create(['organization_id' => $order->organization_id, 'rule' => self::RULE, 'reference' => $order->id, 'points' => -$redemption->points, 'note' => "Uplatněno na objednávku {$order->number}"]));
        } catch (QueryException) {
            // already spent by an earlier delivery of the same payment: the row is the proof
        }
        $redemption->forceFill(['state' => LoyaltyRedemption::CONSUMED, 'consumed_at' => now()])->save();
        $total = $this->loyalty->points($order->organization_id);
        $value = Money::minor($redemption->value_minor, $redemption->currency);
        $this->audit->record($context->withScope($order->organization_id), 'loyalty.redeem.consume', 'succeeded', ['order' => $order->number, 'points' => $redemption->points, 'value' => $value, 'total' => $total], 'order', $order->id);
        $this->outbox->publish(GenericEvent::of('loyalty.redeemed', 'organization', $order->organization_id, ['points' => $redemption->points, 'value' => $value, 'order_id' => $order->id, 'number' => $order->number, 'total' => $total], $order->organization_id));
    }

    /** An order cancelled before it was paid gives its reservation back; nothing was spent. */
    public function release(Order $order, string $reason, CommandContext $context): void
    {
        $redemption = LoyaltyRedemption::query()->where('order_id', $order->id)->lockForUpdate()->first();
        if ($redemption === null || $redemption->state !== LoyaltyRedemption::RESERVED) {
            return;
        }
        $redemption->forceFill(['state' => LoyaltyRedemption::RELEASED, 'released_at' => now()])->save();
        $this->audit->record($context->withScope($order->organization_id), 'loyalty.redeem.release', 'succeeded', ['order' => $order->number, 'points' => $redemption->points, 'reason' => $reason], 'order', $order->id);
    }

    /**
     * A credit note that credited the redemption line (in step with the lines it discounted — RedemptionShare) gives back the same
     * share of the points: half the line, half the points; the line credited in full returns the rest. Only the organization's own
     * documents, once per credit note, never more than were spent.
     *
     * @return int points given back
     */
    public function onCreditNote(string $organizationId, string $creditNoteId, CommandContext $context): int
    {
        return DB::transaction(function () use ($organizationId, $creditNoteId, $context) {
            Organization::query()->whereKey($organizationId)->lockForUpdate()->first();
            $note = Invoice::query()->find($creditNoteId);
            if ($note === null || $note->type !== 'credit_note' || $note->corrects_invoice_id === null || $note->organization_id !== $organizationId) {
                return 0;
            }
            $original = Invoice::query()->find($note->corrects_invoice_id);
            if ($original === null || $original->organization_id !== $organizationId || $original->order_id === null) {
                return 0;
            }
            $line = $original->lines()->where('sku', self::SKU)->first();
            if ($line === null || (int) $line->total_minor >= 0 || ! $note->lines()->where('corrects_line_id', $line->id)->exists()) {
                return 0;
            }
            $redemption = LoyaltyRedemption::query()->where('organization_id', $organizationId)->where('order_id', $original->order_id)->lockForUpdate()->first();
            if ($redemption === null || $redemption->state !== LoyaltyRedemption::CONSUMED) {
                return 0;
            }
            $whole = -(int) $line->total_minor;
            $credited = -(int) (app(InvoiceService::class)->creditedByLine($original)[$line->id]['total'] ?? 0);
            $target = $credited >= $whole ? $redemption->points : intdiv($redemption->points * max(0, $credited), $whole);
            $give = min($target, $redemption->points) - $redemption->returned_points;
            if ($give <= 0) {
                return 0;
            }
            try {
                DB::transaction(fn () => LoyaltyPoint::query()->create(['organization_id' => $organizationId, 'rule' => self::RETURN_RULE, 'reference' => mb_substr($original->order_id.':'.$note->id, 0, 120), 'points' => $give, 'note' => "Dobropis {$note->number}"]));
            } catch (QueryException) {
                return 0; // this credit note was counted already
            }
            $redemption->forceFill(['returned_points' => $redemption->returned_points + $give])->save();
            $total = $this->loyalty->points($organizationId);
            $this->audit->record($context->withScope($organizationId), 'loyalty.redeem.return', 'succeeded', ['credit_note' => $note->number, 'points' => $give, 'total' => $total], 'organization', $organizationId);
            $this->outbox->publish(GenericEvent::of('loyalty.points_returned', 'organization', $organizationId, ['points' => $give, 'total' => $total, 'credit_note' => $note->number, 'order_id' => $original->order_id], $organizationId));

            return $give;
        });
    }

    /** @return array<string,mixed> the redemption of an order, for the order's presenter and the history */
    public static function present(?LoyaltyRedemption $redemption): ?array
    {
        return $redemption === null ? null : [
            'points' => $redemption->points, 'value' => Money::minor($redemption->value_minor, $redemption->currency), 'state' => $redemption->state, 'returned_points' => $redemption->returned_points,
        ];
    }
}
