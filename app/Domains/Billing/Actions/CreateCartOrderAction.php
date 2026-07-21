<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Billing\Models\Order;
use App\Domains\Billing\Services\VatResolver;
use App\Domains\Customer\Models\Customer;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Models\DiscountCode;
use App\Models\DiscountCodeUsage;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates ONE order holding MULTIPLE cart lines — the multi-service
 * checkout counterpart of CreateOrderAction (which is single-plan).
 *
 * Same pricing convention as CreateOrderAction: plan prices are NET
 * (excl. VAT) minor units. Each OrderItem carries its own net unit_price
 * and line total; Order.subtotal is the net sum, Order.tax_amount is VAT
 * resolved once for the customer, Order.total is gross.
 *
 * A discount code applies to the whole cart subtotal, not per line.
 *
 * `$lines` is a list of:
 *   - plan   (PricingPlan)            required
 *   - qty    (int >= 1)               defaults to 1
 *   - config (array<string,mixed>)    per-item provisioning data (domain …)
 *
 * `$config` keys:
 *   - discount_code (?string)
 *   - markup_percent (float)   reseller markup applied to every line
 */
final class CreateCartOrderAction
{
    public function __construct(
        private readonly VatResolver $vatResolver,
        private readonly \App\Domains\Reseller\Services\ResellerPriceResolver $priceResolver,
    ) {}

    /**
     * @param  list<array{plan: PricingPlan, qty?: int, config?: array<string, mixed>}>  $lines
     * @param  array<string, mixed>  $config
     */
    public function execute(Customer $customer, array $lines, array $config = []): Order
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Cart is empty.');
        }

        $currency = $customer->preferred_currency;

        foreach ($lines as $line) {
            if (!$line['plan']->supportsCurrency($currency)) {
                throw new InvalidArgumentException(
                    "Plan [{$line['plan']->id}] has no price for currency [{$currency->value}]."
                );
            }
        }

        $discountCode = null;
        if (is_string($config['discount_code'] ?? null) && $config['discount_code'] !== '') {
            $discountCode = DiscountCode::valid()
                ->where('code', strtoupper($config['discount_code']))
                ->first();
        }

        $scenario = $this->vatResolver->resolveScenario($customer);
        $vatRate  = $this->vatResolver->resolveRate($customer);

        // A reseller profile pins individual per-plan prices (K146) on top of
        // the blanket markup. Passed by id rather than object so this action's
        // signature stays serialisable; a bare markup_percent (no profile) is
        // still honoured for legacy call sites.
        $reseller = null;
        if (is_int($config['reseller_profile_id'] ?? null)) {
            $reseller = \App\Domains\Reseller\Models\ResellerProfile::find($config['reseller_profile_id']);
        }

        $markupPercent = is_float($config['markup_percent'] ?? null) || is_int($config['markup_percent'] ?? null)
            ? (float) $config['markup_percent']
            : 0.0;

        // Price every line first so the order totals and the item rows can
        // never drift apart.
        $priced   = [];
        $subtotal = Money::zero($currency->value);

        foreach ($lines as $line) {
            $plan = $line['plan'];
            $qty  = max(1, (int) ($line['qty'] ?? 1));

            $unitPrice = $this->priceResolver->unitPrice($reseller, $plan, $currency, $markupPercent);

            $lineTotal = $unitPrice->multipliedBy($qty, RoundingMode::HALF_UP);
            $subtotal  = $subtotal->plus($lineTotal);

            /** @var array<string, mixed> $lineConfig */
            $lineConfig = is_array($line['config'] ?? null) ? $line['config'] : [];

            $priced[] = [
                'plan'      => $plan,
                'qty'       => $qty,
                'unitPrice' => $unitPrice,
                'lineTotal' => $lineTotal,
                'config'    => $lineConfig,
            ];
        }

        $discountAmount = $discountCode !== null
            ? $discountCode->calculateDiscount($subtotal)
            : Money::zero($currency->value);
        $discountedNet = $subtotal->minus($discountAmount);
        $tax           = $discountedNet->multipliedBy($vatRate / 100, RoundingMode::HALF_UP);
        $total         = $discountedNet->plus($tax);

        $periodFrom = now()->startOfDay();

        $order = DB::transaction(function () use (
            $customer, $priced, $scenario, $vatRate, $currency,
            $subtotal, $discountAmount, $discountCode, $tax, $total, $periodFrom,
        ): Order {
            $order = Order::create([
                'customer_id'      => $customer->id,
                'status'           => OrderStatus::Pending,
                'currency'         => $currency,
                'subtotal'         => $subtotal,
                'tax_amount'       => $tax,
                'total'            => $total,
                'discount_code_id' => $discountCode?->id,
                'discount_amount'  => $discountAmount,
                'vat_scenario'     => $scenario->value,
            ]);

            if ($discountCode !== null) {
                DiscountCodeUsage::create([
                    'discount_code_id' => $discountCode->id,
                    'customer_id'      => $customer->id,
                    'order_id'         => $order->id,
                ]);
                $discountCode->increment('used_count');
            }

            foreach ($priced as $line) {
                $plan        = $line['plan'];
                $productName = $plan->product->name ?? 'Hosting';

                $order->items()->create([
                    'pricing_plan_id'     => $plan->id,
                    'description'         => trim("{$productName} {$plan->name} ({$plan->billing_cycle->label()})"),
                    'quantity'            => $line['qty'],
                    'currency'            => $currency->value,
                    'unit_price'          => $line['unitPrice'],
                    'vat_rate'            => $vatRate,
                    'total'               => $line['lineTotal'],
                    'period_from'         => $periodFrom,
                    'period_to'           => $periodFrom->copy()->addMonths($plan->billing_cycle->months()),
                    'provisioning_status' => TaskStatus::Pending,
                    'config'              => $line['config'],
                ]);
            }

            return $order;
        });

        activity('order')
            ->performedOn($order)
            ->causedBy($customer->user)
            ->withProperties([
                'lines'    => count($priced),
                'plan_ids' => array_map(static fn (array $l): int => $l['plan']->id, $priced),
                'total'    => $total->getMinorAmount()->toInt(),
                'currency' => $currency->value,
            ])
            ->log('order.created_from_cart');

        $customer->user?->notify(new \App\Notifications\OrderReceivedNotification($order));

        return $order->load('items');
    }
}
