<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Products\Enums\BillingCycle;
use App\Domains\Products\Models\PricingPlan;
use App\Domains\Products\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingPlan>
 */
class PricingPlanFactory extends Factory
{
    protected $model = PricingPlan::class;

    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word());

        return [
            'product_id'    => Product::factory(),
            'name'          => ['cs' => $name, 'en' => $name],
            'tagline'       => ['cs' => fake()->sentence(3), 'en' => fake()->sentence(3)],
            'billing_cycle' => BillingCycle::Monthly,
            // Minor units (haléře / cents).
            'price_czk'     => fake()->numberBetween(4_900, 49_900),
            'price_eur'     => fake()->numberBetween(199, 1_999),
            'resources'     => [
                'disk_mb'      => 10_240,
                'bandwidth_gb' => 100,
                'databases'    => 5,
                'emails'       => 10,
            ],
            'is_active'     => true,
            'is_featured'   => false,
            'sort_order'    => 0,
        ];
    }
}
