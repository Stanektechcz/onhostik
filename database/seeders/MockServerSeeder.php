<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Models\Server;
use Illuminate\Database\Seeder;

/**
 * Seeds the default MOCK aaPanel node used by the Phase 2 vertical slice.
 *
 * No real backend exists behind this server — `mock_mode` is force-set and
 * the AapanelMockDriver never performs network I/O.
 */
class MockServerSeeder extends Seeder
{
    public function run(): void
    {
        Server::updateOrCreate(
            ['name' => 'MOCK-AAP-01'],
            [
                'driver'           => ProvisioningDriver::AAPanel,
                'api_url'          => 'https://mock.aapanel.local',
                'api_credentials'  => ['note' => 'mock server — no real credentials'],
                'status'           => 'active',
                'max_services'     => 500,
                'current_services' => 0,
                'is_default'       => true,
                'mock_mode'        => true,
            ],
        );
    }
}
