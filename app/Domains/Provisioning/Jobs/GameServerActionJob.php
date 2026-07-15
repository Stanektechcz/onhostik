<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Jobs;

use App\Domains\Integrations\Clients\PterodactylClient;
use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

/**
 * Game server (Pterodactyl) actions — queued and ProvisioningTask-tracked.
 *
 * Currently supports `reinstall` (resets the server files to the egg
 * default). The PterodactylClient refuses real HTTP while the provider is
 * mocked or inactive — the task result then carries dry_run=true.
 */
final class GameServerActionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public int $serviceId,
        public string $action, // reinstall
        public ?int $requestedBy = null,
    ) {
        if (!in_array($action, ['reinstall'], true)) {
            throw new InvalidArgumentException("Unsupported game server action [{$action}].");
        }

        $this->onQueue(Config::string('provisioning.queues.default', 'provisioning'));
    }

    public function handle(): void
    {
        $service = Service::find($this->serviceId);

        if ($service === null || $service->provisioning_driver !== ProvisioningDriver::Pterodactyl) {
            return;
        }

        $serverId = (int) $service->external_id;

        $task = $service->provisioningTasks()->create([
            'operation'    => "game_{$this->action}",
            'status'       => TaskStatus::Running,
            'attempts'     => 1,
            'max_attempts' => 3,
            'payload'      => ['server_id' => $serverId, 'requested_by' => $this->requestedBy],
            'started_at'   => now(),
        ]);

        if ($serverId <= 0) {
            $task->update([
                'status'        => TaskStatus::Failed,
                'error_message' => 'Service has no valid Pterodactyl server id in external_id.',
                'finished_at'   => now(),
            ]);

            return;
        }

        $setting = IntegrationSetting::query()->firstOrCreate(
            ['provider' => 'pterodactyl'],
            ['label' => 'Pterodactyl Panel (game servery)', 'mock_mode' => true, 'dry_run' => true],
        );

        $dryRun = $setting->mock_mode || !$setting->is_active;
        $client = new PterodactylClient($setting);

        try {
            $ok = match ($this->action) {
                'reinstall' => $client->reinstallServer($serverId),
            };
            $error = null;
        } catch (\Throwable $e) {
            $ok    = false;
            $error = $e->getMessage();
        }

        if ($ok) {
            $task->update([
                'status'      => TaskStatus::Success,
                'result'      => ['dry_run' => $dryRun, 'server_id' => $serverId],
                'finished_at' => now(),
            ]);

            activity('provisioning')
                ->performedOn($service)
                ->withProperties([
                    'operation'    => "game_{$this->action}",
                    'task_id'      => $task->id,
                    'server_id'    => $serverId,
                    'dry_run'      => $dryRun,
                    'requested_by' => $this->requestedBy,
                ])
                ->log("service.game_{$this->action}");

            return;
        }

        $task->update([
            'status'        => TaskStatus::Failed,
            'error_message' => $error ?? "Pterodactyl {$this->action} returned failure.",
            'finished_at'   => now(),
        ]);

        activity('provisioning')
            ->performedOn($service)
            ->withProperties(['operation' => "game_{$this->action}", 'task_id' => $task->id, 'error' => $error])
            ->log('provisioning.failed');
    }
}
