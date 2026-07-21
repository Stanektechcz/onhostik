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
use Throwable;

/**
 * Applies a webhosting configuration change (database, FTP account, cron job,
 * SSL, disk quota) to an aaPanel site — queued and ProvisioningTask-tracked,
 * like every other provisioning write.
 *
 * Security: every AapanelClient method used here routes through dryRunOr(),
 * so while the provider is mocked or AAPANEL_ALLOW_REAL_WRITES is closed the
 * call is simulated and the task result carries dry_run=true. This job never
 * opens that gate.
 *
 * Credentials (FTP/DB passwords) are NEVER written to the task payload or the
 * activity log — only the fact that an account was created.
 */
final class WebhostingConfigActionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const ACTIONS = [
        'create_database' => 'Vytvoření databáze',
        'delete_database' => 'Smazání databáze',
        'create_ftp'      => 'Vytvoření FTP účtu',
        'delete_ftp'      => 'Smazání FTP účtu',
        'create_cron'     => 'Vytvoření cron úlohy',
        'delete_cron'     => 'Smazání cron úlohy',
        'issue_ssl'       => 'Vydání SSL certifikátu',
        'set_quota'       => 'Změna diskové kvóty',
        'create_mailbox'  => 'Vytvoření e-mailové schránky',
        'delete_mailbox'  => 'Smazání e-mailové schránky',
        'set_mailbox_quota' => 'Změna kvóty schránky',
    ];

    /** @param array<string, mixed> $params */
    public function __construct(
        public int $serviceId,
        public string $action,
        public array $params = [],
        public ?int $requestedBy = null,
    ) {
        if (! array_key_exists($action, self::ACTIONS)) {
            throw new InvalidArgumentException("Unsupported webhosting config action [{$action}].");
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
            'operation'    => $this->action,
            'status'       => TaskStatus::Running,
            'attempts'     => 1,
            'max_attempts' => 1,
            'payload'      => ['requested_by' => $this->requestedBy] + $this->safeParams(),
            'started_at'   => now(),
        ]);

        $setting = IntegrationSetting::query()->firstOrCreate(
            ['provider' => ProvisioningDriver::AAPanel->value],
            ['label' => ProvisioningDriver::AAPanel->label(), 'mock_mode' => true, 'dry_run' => true],
        );

        try {
            $result = $this->run(new AapanelClient($setting), $service);

            $task->update([
                'status'      => TaskStatus::Success,
                'result'      => $this->scalars($result),
                'finished_at' => now(),
            ]);

            activity('provisioning')
                ->performedOn($service)
                ->withProperties([
                    'task_id' => $task->id,
                    'action'  => $this->action,
                    'dry_run' => (bool) ($result['dry_run'] ?? false),
                ] + $this->safeParams())
                ->log("service.{$this->action}");
        } catch (Throwable $e) {
            $task->update([
                'status'        => TaskStatus::Failed,
                'error_message' => $e->getMessage(),
                'finished_at'   => now(),
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function run(AapanelClient $client, Service $service): array
    {
        $site   = (string) ($service->label ?? '');
        $siteId = (string) ($service->external_id ?? '');

        return match ($this->action) {
            'create_database' => $client->createDatabase(
                $this->str('name'),
                $this->str('username'),
            ),
            'delete_database' => $client->deleteDatabase($this->str('name')),
            'create_ftp'      => $client->createFtpAccount(
                $this->str('username'),
                $this->str('path') !== '' ? $this->str('path') : "/www/wwwroot/{$site}",
            ),
            'delete_ftp'      => $client->deleteFtpAccount($this->str('username')),
            'create_cron'     => $client->createCronJob(
                $this->str('name'),
                $this->str('command'),
                $this->str('type') !== '' ? $this->str('type') : 'day',
                (int) ($this->params['hour'] ?? 3),
                (int) ($this->params['minute'] ?? 0),
            ),
            'delete_cron'     => $client->deleteCronJob($this->str('cron_id')),
            'issue_ssl'       => $client->configureSsl($siteId !== '' ? $siteId : $site),
            'set_quota'       => $client->setDiskQuota($siteId !== '' ? $siteId : $site, (int) ($this->params['quota_mb'] ?? 0)),

            'create_mailbox' => $client->createMailbox(
                $site,
                $this->str('username'),
                $this->str('password'),
                (int) ($this->params['quota_mb'] ?? 1024),
            ),
            'delete_mailbox'    => $client->deleteMailbox($site, $this->str('username')),
            'set_mailbox_quota' => $client->setMailboxQuota($site, $this->str('username'), (int) ($this->params['quota_mb'] ?? 1024)),

            // The constructor already rejects unknown actions; this arm keeps
            // a future ACTIONS entry from silently doing nothing.
            default => throw new InvalidArgumentException("No handler for webhosting config action [{$this->action}]."),
        };
    }

    private function str(string $key): string
    {
        $value = $this->params[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * Params minus anything secret — passwords must never be persisted.
     *
     * @return array<string, mixed>
     */
    private function safeParams(): array
    {
        $safe = $this->params;
        unset($safe['password'], $safe['db_password'], $safe['ftp_password']);

        return $this->scalars($safe);
    }

    /**
     * @param  array<mixed>  $data
     * @return array<string, mixed>
     */
    private function scalars(array $data): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $flat[(string) $key] = $value;
            } elseif (is_array($value)) {
                $flat[(string) $key] = json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
            }
        }

        return $flat;
    }
}
