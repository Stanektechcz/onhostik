<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Jobs;

use App\Domains\Integrations\Clients\ProxmoxClient;
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
 * Start / stop / restart a Proxmox VPS — queued and task-tracked like
 * every other external operation.
 *
 * Security: the ProxmoxClient refuses real HTTP while the provider row is
 * in mock mode or inactive (the seeded default). In that state the action
 * is simulated and the task result carries dry_run=true.
 */
final class VpsPowerActionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public int $serviceId,
        public string $action, // start | stop | restart
        public ?int $requestedBy = null,
    ) {
        if (!in_array($action, ['start', 'stop', 'restart'], true)) {
            throw new InvalidArgumentException("Unsupported VPS action [{$action}].");
        }

        $this->onQueue(Config::string('provisioning.queues.default', 'provisioning'));
    }

    public function handle(): void
    {
        $service = Service::find($this->serviceId);

        if ($service === null || $service->provisioning_driver !== ProvisioningDriver::Proxmox) {
            return;
        }

        $vmid = (int) $service->external_id;

        $task = $service->provisioningTasks()->create([
            'operation'    => "vps_{$this->action}",
            'status'       => TaskStatus::Running,
            'attempts'     => 1,
            'max_attempts' => 3,
            'payload'      => ['vmid' => $vmid, 'requested_by' => $this->requestedBy],
            'started_at'   => now(),
        ]);

        if ($vmid <= 0) {
            $task->update([
                'status'        => TaskStatus::Failed,
                'error_message' => 'Service has no valid Proxmox VMID in external_id.',
                'finished_at'   => now(),
            ]);

            return;
        }

        $setting = IntegrationSetting::query()->firstOrCreate(
            ['provider' => 'proxmox'],
            ['label' => 'Proxmox VE (VPS / Cloud)', 'mock_mode' => true, 'dry_run' => true],
        );

        $dryRun = $setting->mock_mode || !$setting->is_active;
        $client = new ProxmoxClient($setting);

        try {
            $ok = match ($this->action) {
                'start'   => $client->startVM($vmid),
                'stop'    => $client->stopVM($vmid),
                'restart' => $client->rebootVM($vmid),
            };
            $error = null;
        } catch (\Throwable $e) {
            $ok    = false;
            $error = $e->getMessage();
        }

        if ($ok) {
            $task->update([
                'status'      => TaskStatus::Success,
                'result'      => ['dry_run' => $dryRun, 'vmid' => $vmid],
                'finished_at' => now(),
            ]);

            activity('provisioning')
                ->performedOn($service)
                ->withProperties([
                    'operation'    => "vps_{$this->action}",
                    'task_id'      => $task->id,
                    'vmid'         => $vmid,
                    'dry_run'      => $dryRun,
                    'requested_by' => $this->requestedBy,
                ])
                ->log("service.vps_{$this->action}");

            return;
        }

        $task->update([
            'status'        => TaskStatus::Failed,
            'error_message' => $error ?? "Proxmox {$this->action} returned failure.",
            'finished_at'   => now(),
        ]);

        activity('provisioning')
            ->performedOn($service)
            ->withProperties(['operation' => "vps_{$this->action}", 'task_id' => $task->id, 'error' => $error])
            ->log('provisioning.failed');
    }
}
