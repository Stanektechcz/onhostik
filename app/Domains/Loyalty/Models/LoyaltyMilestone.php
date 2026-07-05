<?php

declare(strict_types=1);

namespace App\Domains\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyMilestone extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'trigger_type',
        'trigger_value',
        'reward_type',
        'reward_value',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'     => 'boolean',
            'trigger_value' => 'integer',
            'reward_value'  => 'integer',
            'sort_order'    => 'integer',
        ];
    }

    /** @return HasMany<CustomerLoyaltyReward, $this> */
    public function rewards(): HasMany
    {
        return $this->hasMany(CustomerLoyaltyReward::class);
    }

    public function triggerLabel(): string
    {
        return match ($this->trigger_type) {
            'account_age_days' => "Věk účtu: {$this->trigger_value} dní",
            'order_count'      => "Počet objednávek: {$this->trigger_value}",
            'total_spent_czk'  => 'Celkem utraceno: ' . number_format($this->trigger_value / 100, 2) . ' Kč',
        };
    }

    public function rewardLabel(): string
    {
        return match ($this->reward_type) {
            'credit_czk'       => 'Kredit: ' . number_format($this->reward_value / 100, 2) . ' Kč',
            'badge'            => "Odznak: {$this->reward_value}",
            'discount_percent' => "Sleva: {$this->reward_value} %",
        };
    }
}
