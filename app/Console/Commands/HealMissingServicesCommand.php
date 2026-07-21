<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Enums\ServiceSyncState;
use App\Domains\Provisioning\Jobs\ProvisionHostingServiceJob;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Console\Command;

/**
 * Auto-healing: re-provisions services that vanished from their panel.
 *
 * `services:sync-remote` already detects the money-losing case — a service the
 * customer PAYS for that is `MissingRemote` (active locally, absent in the
 * panel) — but until now it only notified an admin, who then clicked
 * "Zřídit službu nyní". This closes that loop automatically.
 *
 * OPT-IN (`provisioning.auto_heal`, default off): auto-reprovision is a write,
 * and provisioning itself stays behind the same mock/real-writes gates — in
 * mock mode this simply re-runs the (safe) mock provision. The reprovision job
 * is idempotent (checks `external_id` before create), so a race with a manual
 * fix cannot double-provision.
 */
final class HealMissingServicesCommand extends Command
{
    protected $signature = 'services:heal {--limit=25 : Max services to heal per run}';

    protected $description = 'Automatically re-provision services missing from their panel';

    public function handle(): int
    {
        if (! (bool) config('provisioning.auto_heal', false)) {
            $this->info('Auto-heal is disabled (provisioning.auto_heal=false).');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));

        $services = Service::query()
            ->where('sync_state', ServiceSyncState::MissingRemote->value)
            // Only things the customer is entitled to have running.
            ->whereIn('status', [ServiceStatus::Active->value, ServiceStatus::Suspended->value])
            ->limit($limit)
            ->get();

        if ($services->isEmpty()) {
            $this->info('Nothing to heal.');

            return self::SUCCESS;
        }

        $healed = 0;
        foreach ($services as $service) {
            ProvisionHostingServiceJob::dispatchSync($service->id);

            activity('provisioning')
                ->performedOn($service)
                ->withProperties(['operation' => 'auto_heal', 'reason' => 'missing_remote'])
                ->log('service.auto_healed');

            $healed++;
        }

        $this->info("Auto-heal ran for {$healed} service(s).");

        return self::SUCCESS;
    }
}
