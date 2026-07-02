<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscriber>
 */
class SubscriberFactory extends Factory
{
    protected $model = Subscriber::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'email'             => $this->faker->unique()->safeEmail(),
            'name'              => $this->faker->name(),
            'locale'            => 'cs',
            'source'            => 'web',
            'is_active'         => true,
            'confirmed_at'      => now(),
            'unsubscribed_at'   => null,
            'unsubscribe_token' => Str::random(64),
        ];
    }
}
