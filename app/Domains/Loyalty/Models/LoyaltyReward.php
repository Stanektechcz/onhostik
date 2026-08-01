<?php

declare(strict_types=1);

namespace App\Domains\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A redeemable reward in the loyalty catalog. Costs points; grants credit on
 * redemption.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $points_cost
 * @property string $reward_type
 * @property int $reward_value_halere
 * @property bool $is_active
 * @property int $sort_order
 */
class LoyaltyReward extends Model
{
    protected $fillable = [
        'name',
        'description',
        'points_cost',
        'reward_type',
        'reward_value_halere',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'points_cost'         => 'integer',
            'reward_value_halere' => 'integer',
            'is_active'           => 'boolean',
            'sort_order'          => 'integer',
        ];
    }

    public function rewardLabel(): string
    {
        return match ($this->reward_type) {
            'credit_czk' => 'Kredit ' . number_format($this->reward_value_halere / 100, 0, ',', ' ') . ' Kč',
            default      => $this->reward_type,
        };
    }
}
