<?php

declare(strict_types=1);

namespace App\Domains\Reseller\Services;

use App\Domains\Shared\Enums\Currency;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Reseller\Models\ResellerPricingOverride;
use App\Domains\Reseller\Models\ResellerProfile;
use Brick\Money\Money;

/**
 * The single price a reseller pays for a plan (audit K146).
 *
 * There are three layers, most specific first:
 *
 *   1. An explicit per-plan override (ResellerPricingOverride) — an individual
 *      negotiated price. This model and its admin CRUD already existed, but
 *      NOTHING read it at pricing time, so a negotiated price was configured
 *      and then silently ignored while the customer was charged the markup
 *      price instead.
 *   2. The reseller's blanket markup percentage.
 *   3. The plan's base price.
 *
 * An override wins outright — it is a fixed price, not an adjustment, so the
 * markup is not applied on top of it.
 */
final class ResellerPriceResolver
{
    public function unitPrice(
        ?ResellerProfile $reseller,
        PricingPlan $plan,
        Currency $currency,
        float $fallbackMarkupPercent = 0.0,
    ): Money {
        if ($reseller === null) {
            // No profile in hand, but a caller may still pass a bare markup
            // (admin order form, legacy call sites). Honour it.
            return $fallbackMarkupPercent > 0.0
                ? $plan->priceWithMarkup($currency, $fallbackMarkupPercent)
                : $plan->priceFor($currency);
        }

        $override = $this->overridePrice($reseller, $plan, $currency);

        if ($override !== null) {
            return $override;
        }

        return $plan->priceWithMarkup($currency, (float) $reseller->markup_percent);
    }

    private function overridePrice(ResellerProfile $reseller, PricingPlan $plan, Currency $currency): ?Money
    {
        /** @var ResellerPricingOverride|null $override */
        $override = ResellerPricingOverride::query()
            ->where('reseller_id', $reseller->id)
            ->where('pricing_plan_id', $plan->id)
            ->where('is_active', true)
            ->first();

        if ($override === null) {
            return null;
        }

        $minor = match ($currency) {
            Currency::CZK => $override->price_czk,
            Currency::EUR => $override->price_eur,
            Currency::USD => $override->price_usd,
        };

        // A partial override (price set for CZK but not EUR) must not become a
        // free plan in the missing currency — fall through to markup instead.
        if ($minor === null) {
            return null;
        }

        return Money::ofMinor($minor, $currency->value);
    }
}
