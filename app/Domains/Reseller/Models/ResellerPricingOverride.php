<?php

declare(strict_types=1);

namespace App\Domains\Reseller\Models;

use App\Domains\Products\Models\PricingPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int  $reseller_id
 * @property int  $pricing_plan_id
 * @property bool $is_active
 */
class ResellerPricingOverride extends Model
{
    protected $fillable = [
        'reseller_id',
        'pricing_plan_id',
        'price_czk',
        'price_eur',
        'price_usd',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_czk' => 'integer',
            'price_eur' => 'integer',
            'price_usd' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<ResellerProfile, $this> */
    public function reseller(): BelongsTo
    {
        return $this->belongsTo(ResellerProfile::class, 'reseller_id');
    }

    /** @return BelongsTo<PricingPlan, $this> */
    public function pricingPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class);
    }

    public function effectivePriceCzk(): ?int
    {
        return $this->is_active ? $this->price_czk : null;
    }

    public function effectivePriceEur(): ?int
    {
        return $this->is_active ? $this->price_eur : null;
    }
}
