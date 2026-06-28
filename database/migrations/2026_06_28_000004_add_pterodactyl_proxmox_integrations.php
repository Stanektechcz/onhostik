<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $providers = [
            [
                'provider'   => 'pterodactyl',
                'label'      => 'Pterodactyl Panel (game servery)',
                'is_active'  => false,
                'mock_mode'  => true,
                'dry_run'    => true,
                'credentials'=> '{}',
                'meta'       => json_encode(['category' => 'provisioning']),
            ],
            [
                'provider'   => 'proxmox',
                'label'      => 'Proxmox VE (VPS / Cloud)',
                'is_active'  => false,
                'mock_mode'  => true,
                'dry_run'    => true,
                'credentials'=> '{}',
                'meta'       => json_encode(['category' => 'provisioning']),
            ],
        ];

        foreach ($providers as $provider) {
            DB::table('integration_settings')->insertOrIgnore(array_merge($provider, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        DB::table('integration_settings')
            ->whereIn('provider', ['pterodactyl', 'proxmox'])
            ->delete();
    }
};
