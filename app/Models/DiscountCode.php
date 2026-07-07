<?php

declare(strict_types=1);

namespace App\Models;

use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscountCode extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'currency', 'max_uses',
        'max_uses_per_customer', 'min_order_haler', 'total_saved_haler',
        'used_count', 'expires_at', 'is_active', 'description',
        'created_by_user_id', 'source',
    ];

    protected $casts = [
        'value'      => 'decimal:2',
        'expires_at' => 'datetime',
        'is_active'  => 'boolean',
    ];

    /** Route model binding by code slug. */
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<DiscountCodeUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(DiscountCodeUsage::class);
    }

    /**
     * @param  Builder<DiscountCode>  $query
     * @return Builder<DiscountCode>
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('max_uses')->orWhereRaw('used_count < max_uses'));
    }

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) return false;
        return true;
    }

    public function isUsableByCustomer(int $customerId): bool
    {
        if (!$this->isValid()) return false;
        if ($this->max_uses_per_customer === null) return true;
        $usedByCustomer = $this->usages()->where('customer_id', $customerId)->count();
        return $usedByCustomer < $this->max_uses_per_customer;
    }

    public function isMinOrderMet(int $orderHaler): bool
    {
        return $orderHaler >= (int) $this->min_order_haler;
    }

    /** @return array<string, mixed> */
    public function usageStats(): array
    {
        return [
            'used_count'        => $this->used_count,
            'max_uses'          => $this->max_uses,
            'unique_customers'  => $this->usages()->distinct('customer_id')->count('customer_id'),
            'total_saved_haler' => (int) $this->total_saved_haler,
        ];
    }

    /**
     * Calculate discount amount for a given price.
     * Returns Money in the same currency as $price.
     */
    public function calculateDiscount(Money $price): Money
    {
        if ($this->type === 'percent') {
            $factor = (float) $this->value / 100;
            return $price->multipliedBy($factor, RoundingMode::HALF_UP);
        }

        // Fixed discount in specified currency
        $discount = Money::ofMinor((int) ($this->value * 100), $this->currency ?? $price->getCurrency());

        // Never discount more than the price itself
        if ($discount->isGreaterThan($price)) {
            return $price;
        }

        return $discount;
    }

    public function formattedValue(): string
    {
        if ($this->type === 'percent') {
            return rtrim(rtrim(number_format((float) $this->value, 2, ',', ' '), '0'), ',') . ' %';
        }
        return number_format((float) $this->value, 0, ',', ' ') . ' ' . ($this->currency ?? 'CZK');
    }
}
