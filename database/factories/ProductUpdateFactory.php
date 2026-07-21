<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Communication\Models\ProductUpdate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductUpdate> */
class ProductUpdateFactory extends Factory
{
    protected $model = ProductUpdate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title'        => $this->faker->sentence(4),
            'body'         => $this->faker->paragraph(),
            'category'     => $this->faker->randomElement(array_keys(ProductUpdate::CATEGORIES)),
            'version'      => $this->faker->numerify('#.#.#'),
            'is_published' => true,
            'published_at' => now()->subDays($this->faker->numberBetween(1, 60)),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['is_published' => false, 'published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'is_published' => true,
            'published_at' => now()->addDays(7),
        ]);
    }
}
