<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    protected $model = Server::class;

    public function definition(): array
    {
        return [
            'name'         => $this->faker->words(2, true) . ' node',
            'driver'       => ProvisioningDriver::AAPanel,
            'api_url'      => 'http://127.0.0.1:' . $this->faker->numberBetween(8080, 9090),
            'api_credentials' => ['key' => 'testkey', 'token_id' => '1'],
            'status'       => 'active',
            'max_services' => $this->faker->numberBetween(10, 100),
            'current_services' => 0,
            'is_default'   => false,
            'mock_mode'    => true,
        ];
    }
}
