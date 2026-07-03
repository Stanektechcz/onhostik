<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Customer\Models\Customer;
use App\Domains\Products\Models\Product;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'customer_id'         => Customer::factory(),
            'order_item_id'       => null,
            'product_id'          => Product::factory(),
            'server_id'           => null,
            'provisioning_driver' => ProvisioningDriver::AAPanel,
            'external_id'         => null,
            'status'              => ServiceStatus::Active,
            'label'               => $this->faker->domainName(),
            'resources'           => null,
            'next_due_date'       => now()->addMonth(),
            'suspended_at'        => null,
            'terminated_at'       => null,
            'suspension_reason'   => null,
            'usage_snapshot'      => null,
        ];
    }

    public function suspended(): static
    {
        return $this->state([
            'status'       => ServiceStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }

    public function terminated(): static
    {
        return $this->state([
            'status'         => ServiceStatus::Terminated,
            'terminated_at'  => now(),
        ]);
    }
}
