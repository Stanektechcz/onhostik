<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Integrations\Clients\AapanelClient;
use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Console\Command;

/**
 * Syncs live usage stats (disk, bandwidth, PHP processes) from aaPanel
 * for all active hosting services. Falls back gracefully in mock mode.
 *
 * Stores usage snapshot in services.usage_snapshot (JSON) for dashboard display.
 * Runs every 15 minutes via scheduler.
 */
class SyncServiceUsageCommand extends Command
{
    protected $signature   = 'provisioning:sync-usage';
    protected $description = 'Sync disk/bandwidth usage stats from aaPanel for active services';

    public function handle(): int
    {
        $integration = IntegrationSetting::where('provider', 'aapanel')
            ->where('is_active', true)
            ->first();

        $isMock = config('provisioning.mock_mode', true)
            || !config('integrations.real_write_gates.aapanel', false);

        $synced  = 0;
        $skipped = 0;

        Service::query()
            ->where('status', ServiceStatus::Active)
            ->whereNotNull('external_id')
            ->each(function (Service $service) use ($integration, $isMock, &$synced, &$skipped): void {
                try {
                    if ($isMock || $integration === null) {
                        $usage = $this->mockUsage($service);
                    } else {
                        $client = new AapanelClient($integration);
                        $usage  = $client->getSiteUsage($service->external_id ?? '');
                    }

                    $service->update(['usage_snapshot' => array_merge($usage, [
                        'synced_at' => now()->toIso8601String(),
                    ])]);

                    $synced++;
                } catch (\Throwable $e) {
                    report($e);
                    $skipped++;
                }
            });

        $this->info("Synced usage for {$synced} services, {$skipped} errors.");

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function mockUsage(Service $service): array
    {
        // Simulate realistic usage data for mock/demo mode
        $seed = crc32((string) $service->id . now()->format('YmdH'));
        srand($seed);

        return [
            'disk_used_mb'       => rand(50, 2048),
            'disk_quota_mb'      => 10240,
            'bandwidth_used_mb'  => rand(100, 50000),
            'bandwidth_quota_mb' => 102400,
            'php_processes'      => rand(0, 5),
            'db_count'           => rand(1, 5),
            'email_count'        => rand(1, 10),
            'mock'               => true,
        ];
    }
}
