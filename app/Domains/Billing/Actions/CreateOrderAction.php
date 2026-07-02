<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Billing\Models\Order;
use App\Domains\Billing\Services\VatResolver;
use App\Domains\Customer\Models\Customer;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Services\DriverResolver;
use App\Models\DiscountCode;
use App\Models\DiscountCodeUsage;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates an order with an immutable pricing snapshot.
 *
 * Pricing convention: plan prices are NET (excl. VAT) minor units.
 * OrderItem.unit_price/total are net; Order.subtotal is net,
 * Order.tax_amount is VAT resolved for the customer, Order.total is gross —
 * the amount the proforma invoice (and the payment) is issued for.
 *
 * `$config` keys understood in Phase 2:
 *  - domain (?string)            optional domain to register with the hosting
 *  - register_domain (bool)      whether the domain should be registered
 *  - simulate_failure (bool)     mock-mode only: force provisioning failure
 */
final class CreateOrderAction
{
    public function __construct(
        private readonly VatResolver $vatResolver,
        private readonly DriverResolver $drivers,
    ) {}

    /** @param array<string, mixed> $config */
    public function execute(Customer $customer, PricingPlan $plan, array $config = []): Order
    {
        $currency = $customer->preferred_currency;

        if (!$plan->supportsCurrency($currency)) {
            throw new InvalidArgumentException(
                "Plan [{$plan->id}] has no price for currency [{$currency->value}]."
            );
        }

        $domain = is_string($config['domain'] ?? null) ? mb_strtolower(trim($config['domain'])) : null;
        $registerDomain = $domain !== null && ($config['register_domain'] ?? false) === true;

        // Resolve discount code if supplied
        $discountCode = null;
        if (is_string($config['discount_code'] ?? null) && $config['discount_code'] !== '') {
            $discountCode = DiscountCode::valid()
                ->where('code', strtoupper($config['discount_code']))
                ->first();
        }

        // Defense in depth — controllers validate availability for UX, the
        // action re-validates so no unavailable domain can enter an order.
        if ($registerDomain) {
            $check = $this->drivers->registrar()->checkDomain($domain);

            if (!$check->available) {
                throw new InvalidArgumentException(
                    "Domain [{$domain}] is not available: {$check->reason}."
                );
            }
        }

        $scenario = $this->vatResolver->resolveScenario($customer);
        $vatRate  = $this->vatResolver->resolveRate($customer);

        $unitPrice      = $plan->priceFor($currency);
        $subtotal       = $unitPrice;
        $discountAmount = $discountCode !== null
            ? $discountCode->calculateDiscount($subtotal)
            : Money::zero($currency->value);
        $discountedNet  = $subtotal->minus($discountAmount);
        $tax            = $discountedNet->multipliedBy($vatRate / 100, RoundingMode::HALF_UP);
        $total          = $discountedNet->plus($tax);

        $periodFrom = now()->startOfDay();
        $periodTo   = $periodFrom->copy()->addMonths($plan->billing_cycle->months());

        $order = DB::transaction(function () use (
            $customer, $plan, $scenario, $vatRate, $currency,
            $unitPrice, $subtotal, $discountAmount, $discountCode, $tax, $total,
            $periodFrom, $periodTo, $domain, $registerDomain, $config,
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

            $productName = $plan->product->name ?? 'Hosting';

            $order->items()->create([
                'pricing_plan_id'     => $plan->id,
                'description'         => trim("{$productName} {$plan->name} ({$plan->billing_cycle->label()})"),
                'quantity'            => 1,
                'currency'            => $currency->value,
                'unit_price'          => $unitPrice,
                'vat_rate'            => $vatRate,
                'total'               => $subtotal,
                'period_from'         => $periodFrom,
                'period_to'           => $periodTo,
                'provisioning_status' => TaskStatus::Pending,
                'config'              => array_filter([
                    'domain'           => $domain,
                    'register_domain'  => $registerDomain,
                    'simulate_failure' => ($config['simulate_failure'] ?? false) === true,
                ], fn (mixed $value): bool => $value !== null && $value !== false),
            ]);

            return $order;
        });

        activity('order')
            ->performedOn($order)
            ->causedBy($customer->user)
            ->withProperties([
                'plan_id'  => $plan->id,
                'domain'   => $domain,
                'total'    => $total->getMinorAmount()->toInt(),
                'currency' => $currency->value,
            ])
            ->log('order.created');

        return $order->load('items');
    }
}
