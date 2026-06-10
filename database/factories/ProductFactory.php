<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Products\Enums\ProductType;
use App\Domains\Products\Models\Product;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug'                => str($name)->slug()->toString(),
            'type'                => ProductType::Webhosting,
            'name'                => ['cs' => $name, 'en' => $name],
            'description'         => ['cs' => fake()->sentence(), 'en' => fake()->sentence()],
            'provisioning_driver' => ProvisioningDriver::AAPanel,
            'is_active'           => true,
            'sort_order'          => 0,
        ];
    }
}
