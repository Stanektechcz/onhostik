<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Jobs;

use App\Domains\Integrations\Clients\AapanelClient;
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
 * Change the PHP version of an aaPanel-hosted site — queued and
 * ProvisioningTask-tracked.
 *
 * Security: AapanelClient::setPhpVersion() routes through dryRunOr(), so
 * while the provider is mocked / the AAPANEL_ALLOW_REAL_WRITES gate is
 * closed the call is simulated and the task result carries dry_run=true.
 */
final class WebhostingPhpVersionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** aaPanel version identifiers => human labels. */
    public const VERSIONS = [
        '74' => 'PHP 7.4',
        '80' => 'PHP 8.0',
        '81' => 'PHP 8.1',
        '82' => 'PHP 8.2',
        '83' => 'PHP 8.3',
    ];

    public function __construct(
        public int $serviceId,
        public string $phpVersion,
        public ?int $requestedBy = null,
    ) {
        if (!array_key_exists($phpVersion, self::VERSIONS)) {
            throw new InvalidArgumentException("Unsupported PHP version [{$phpVersion}].");
        }

        $this->onQueue(Config::string('provisioning.queues.default', 'provisioning'));
    }

    public function handle(): void
    {
        $service = Service::find($this->serviceId);

        if ($service === null || $service->provisioning_driver !== ProvisioningDriver::AAPanel) {
            return;
        }

        $task = $service->provisioningTasks()->create([
            'operation'    => 'set_php_version',
            'status'       => TaskStatus::Running,
            'attempts'     => 1,
            'max_attempts' => 3,
            'payload'      => ['version' => $this->phpVersion, 'requested_by' => $this->requestedBy],
            'started_at'   => now(),
        ]);

        $siteId = (string) $service->external_id;

        if ($siteId === '') {
            $task->update([
                'status'        => TaskStatus::Failed,
                'error_message' => 'Service has no aaPanel site id in external_id.',
                'finished_at'   => now(),
            ]);

            return;
        }

        $setting = IntegrationSetting::query()->firstOrCreate(
            ['provider' => 'aapanel'],
            ['label' => 'aaPanel (webhosting)', 'mock_mode' => true, 'dry_run' => true],
        );

        try {
            $response = (new AapanelClient($setting))->setPhpVersion($siteId, $this->phpVersion);
            // Mock path returns ok=true; the real aaPanel API answers {status: bool}.
            $ok    = (bool) ($response['ok'] ?? $response['status'] ?? false);
            $error = $ok ? null : (string) ($response['msg'] ?? 'aaPanel returned failure.');
        } catch (\Throwable $e) {
            $response = [];
            $ok       = false;
            $error    = $e->getMessage();
        }

        if ($ok) {
            $service->update([
                'resources' => array_merge($service->resources ?? [], ['php_version' => $this->phpVersion]),
            ]);

            $task->update([
                'status'      => TaskStatus::Success,
                'result'      => ['dry_run' => (bool) ($response['dry_run'] ?? false), 'version' => $this->phpVersion],
                'finished_at' => now(),
            ]);

            activity('provisioning')
                ->performedOn($service)
                ->withProperties([
                    'operation'    => 'set_php_version',
                    'task_id'      => $task->id,
                    'version'      => $this->phpVersion,
                    'requested_by' => $this->requestedBy,
                ])
                ->log('service.php_version_changed');

            return;
        }

        $task->update([
            'status'        => TaskStatus::Failed,
            'error_message' => $error,
            'finished_at'   => now(),
        ]);

        activity('provisioning')
            ->performedOn($service)
            ->withProperties(['operation' => 'set_php_version', 'task_id' => $task->id, 'error' => $error])
            ->log('provisioning.failed');
    }
}
