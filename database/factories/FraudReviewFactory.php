<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Customer\Models\Customer;
use App\Models\FraudReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FraudReview>
 */
class FraudReviewFactory extends Factory
{
    protected $model = FraudReview::class;

    public function definition(): array
    {
        return [
            'customer_id'   => Customer::factory(),
            'order_id'      => null,
            'score'         => $this->faker->numberBetween(0, 100),
            'signals'       => ['new_customer'],
            'status'        => 'pending',
            'reviewed_by'   => null,
            'reviewer_note' => null,
            'reviewed_at'   => null,
        ];
    }
}
