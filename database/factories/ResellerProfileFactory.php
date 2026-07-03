<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Reseller\Models\ResellerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResellerProfile>
 */
class ResellerProfileFactory extends Factory
{
    protected $model = ResellerProfile::class;

    public function definition(): array
    {
        return [
            'user_id'          => User::factory(),
            'business_name'    => $this->faker->company(),
            'custom_domain'    => $this->faker->domainName(),
            'markup_percent'   => $this->faker->randomFloat(2, 0, 30),
            'status'           => 'pending',
            'branding'         => null,
            'allowed_products' => null,
            'approved_at'      => null,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => 'active', 'approved_at' => now()]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => 'suspended']);
    }
}
