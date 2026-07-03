<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Monitoring\Enums\MonitorStatus;
use App\Domains\Monitoring\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Monitor>
 */
class MonitorFactory extends Factory
{
    protected $model = Monitor::class;

    public function definition(): array
    {
        return [
            'service_id'      => null,
            'name'            => $this->faker->domainName(),
            'type'            => 'http',
            'target'          => 'https://' . $this->faker->domainName(),
            'provider'        => 'internal',
            'status'          => MonitorStatus::Up,
            'is_active'       => true,
            'last_check_at'   => now(),
            'uptime_percent'  => 99.9,
            'ssl_expires_at'  => null,
            'external_id'     => null,
        ];
    }
}
