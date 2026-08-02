<?php

declare(strict_types=1);

namespace App\Domains\Products\Models;

use App\Domains\Billing\Models\OrderItem;
use App\Domains\Products\Enums\BillingCycle;
use App\Domains\Shared\Enums\Currency;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use Spatie\Translatable\HasTranslations;

/**
 * Pricing plan with per-currency price columns (minor units).
 *
 * Prices are entered manually per currency (no FX auto-conversion of
 * customer-facing prices — that is a pricing decision, not a math one).
 *
 * @property BillingCycle $billing_cycle
 * @property array<string, mixed>|null $resources
 * @property bool $is_active
 * @property bool $is_featured
 */
class PricingPlan extends Model
{
    protected static function booted(): void
    {
        // A price change must reach the public catalog immediately — the pages
        // cache product+plans together (audit 500 #17).
        $forget = static function (self $plan): void {
            $slug = $plan->product?->slug;

            if (is_string($slug)) {
                \Illuminate\Support\Facades\Cache::forget('catalog:product:' . $slug);
            }
        };

        static::saved($forget);
        static::deleted($forget);
    }

    /** @use HasFactory<\Database\Factories\PricingPlanFactory> */
    use HasFactory;
    use HasTranslations;

    /** @var array<int, string> */
    public array $translatable = ['name', 'tagline'];

    protected $fillable = [
        'product_id',
        'name',           // JSON translatable
        'tagline',        // JSON translatable
        'billing_cycle',
        'price_czk',      // minor units (haléře)
        'price_eur',      // minor units (cents)
        'price_usd',      // minor units (cents)
        'setup_fee_czk',
        'setup_fee_eur',
        'setup_fee_usd',
        'resources',      // JSON: disk_mb, bandwidth_gb, cpu, ram_mb, slots...
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'billing_cycle' => BillingCycle::class,
            'resources'     => 'array',
            'is_active'     => 'boolean',
            'is_featured'   => 'boolean',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // ---------------------------------------------------------------- pricing

    public function priceFor(Currency $currency): Money
    {
        $minor = match ($currency) {
            Currency::CZK => $this->price_czk,
            Currency::EUR => $this->price_eur,
            Currency::USD => $this->price_usd,
        };

        if ($minor === null) {
            throw new InvalidArgumentException(
                "PricingPlan #{$this->id} has no price for {$currency->value}."
            );
        }

        return Money::ofMinor($minor, $currency->value);
    }

    /**
     * Returns the plan price with a reseller markup applied.
     * If markup is 0 or negative, returns the base price unchanged.
     */
    public function priceWithMarkup(Currency $currency, float $markupPercent): Money
    {
        $base = $this->priceFor($currency);

        if ($markupPercent <= 0.0) {
            return $base;
        }

        return $base->multipliedBy(1 + $markupPercent / 100, \Brick\Math\RoundingMode::HALF_UP);
    }

    public function setupFeeFor(Currency $currency): Money
    {
        $minor = match ($currency) {
            Currency::CZK => $this->setup_fee_czk,
            Currency::EUR => $this->setup_fee_eur,
            Currency::USD => $this->setup_fee_usd,
        } ?? 0;

        return Money::ofMinor($minor, $currency->value);
    }

    public function supportsCurrency(Currency $currency): bool
    {
        return match ($currency) {
            Currency::CZK => $this->price_czk !== null,
            Currency::EUR => $this->price_eur !== null,
            Currency::USD => $this->price_usd !== null,
        };
    }
}
