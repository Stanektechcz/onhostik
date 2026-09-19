<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Commands;

use Carbon\CarbonImmutable;
use Onhost\Domain\Provisioning\ServiceMigrationService;
use Onhost\Domain\Services\Models\BackupPolicy;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceSpecService;
use Onhost\Domain\Services\Web\BackupScheduler;
use Onhost\Domain\Services\Web\DeployService;
use Onhost\Domain\Services\Web\UptimeMonitor;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class WebToolsCommandHandler implements CommandHandler
{
    public function __construct(private readonly UptimeMonitor $monitor, private readonly DeployService $deploy, private readonly ServiceFeatures $features, private readonly AuditRecorder $audit) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        $service = Service::query()->find((string) $command->get('service_id'));
        if ($service === null || $service->organization_id !== $command->organizationId) {
            throw DomainError::notFound('service');
        }
        $params = (array) $command->get('params', []);

        return match ((string) $command->get('op')) {
            'monitoring.set' => ['monitor' => $this->monitor->configure($service, $params, $context), 'status' => $this->monitor->status($service)],
            // declarative spec (audit §5e-6): converge the service on a document; every change is an ordinary action
            'spec.apply' => app(ServiceSpecService::class)->apply($service, (array) ($params['spec'] ?? []), $context, $command->idempotencyKey(), $command->permission()),
            // the customer's start time for a scheduled migration (audit §5h-3)
            'migration.window' => ['migration' => app(ServiceMigrationService::class)->reschedule($service, CarbonImmutable::parse((string) ($params['starts_at'] ?? '')), $context)],
            // per-service automation policy (audit §5e-1): whether the usage watch may move the service to the next plan on its own
            'policy.set' => (function () use ($service, $params, $context) {
                $tags = (array) ($service->tags ?? []);
                $policy = array_merge((array) ($tags['policy'] ?? []), array_intersect_key(array_map(fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN), $params), ['auto_upgrade' => 1]));
                $service->forceFill(['tags' => array_merge($tags, ['policy' => $policy])])->save();
                $this->audit->record($context->withScope($service->organization_id, $service->project_id), 'service.policy', 'succeeded', $policy, 'service', $service->id);

                return ['policy' => $policy];
            })(),
            'monitoring.delete' => (function () use ($service, $params, $context) {
                $this->monitor->delete($service, (string) ($params['id'] ?? ''), $context);

                return ['deleted' => true, 'status' => $this->monitor->status($service)];
            })(),
            'deploy.configure' => $this->deploy->configure($service, $params, $context),
            'deploy.rotate_secret' => ['webhook_secret' => $this->deploy->rotateWebhookSecret($service, $context), 'source' => $this->deploy->status($service)['source']],
            'deploy.disconnect' => (function () use ($service, $context) {
                $this->deploy->disconnect($service, $context);

                return ['disconnected' => true];
            })(),
            'backup_schedule.set' => $this->backupSchedule($service, $params, $context),
            default => throw new DomainError('op_unknown', 'Unknown web tools operation.', 422),
        };
    }

    /** @param  array<string,mixed>  $params */
    private function backupSchedule(Service $service, array $params, CommandContext $context): array
    {
        $features = $this->features->features($service);
        $schedule = $features['backup_schedule'] ?? null;
        if ($schedule === null || empty($schedule['enabled'])) {
            throw new DomainError('feature_unavailable', 'Scheduled backups are not part of this plan.', 422);
        }
        $plan = (array) ($schedule['options'] ?? []);
        $allowed = array_keys(BackupScheduler::FREQUENCIES);
        $planMinutes = BackupScheduler::FREQUENCIES[(string) ($plan['frequency'] ?? 'daily')] ?? 1440;
        $frequency = (string) ($params['frequency'] ?? $plan['frequency'] ?? 'daily');
        if (! in_array($frequency, $allowed, true)) {
            throw new DomainError('action_param_invalid', 'frequency must be one of '.implode(', ', $allowed).'.', 422, ['field' => 'frequency']);
        }
        if (BackupScheduler::FREQUENCIES[$frequency] < $planMinutes) {
            throw new DomainError('backup_frequency_above_plan', 'The plan allows backups every '.($plan['frequency'] ?? 'day').' at most.', 422, ['field' => 'frequency']);
        }
        $days = max(1, min((int) ($plan['days'] ?? 7), (int) ($params['days'] ?? $plan['days'] ?? 7)));
        $generations = max(1, min((int) ($plan['generations'] ?? 7), (int) ($params['generations'] ?? $plan['generations'] ?? 7)));
        $offsiteAllowed = (bool) data_get($service->entitlements, 'backup_offsite', false);
        $offsite = $offsiteAllowed && filter_var($params['offsite'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $policy = BackupPolicy::query()->updateOrCreate(['service_id' => $service->id], ['product_key' => $service->product_key, 'schedule' => ['frequency' => $frequency], 'retention' => ['days' => $days, 'generations' => $generations], 'offsite' => $offsite]);
        $this->audit->record($context->withScope($service->organization_id), 'service.backup_schedule.set', 'succeeded', ['frequency' => $frequency, 'days' => $days, 'generations' => $generations, 'offsite' => $offsite], 'service', $service->id);
        $this->features->forget($service);

        return ['schedule' => ['frequency' => $frequency, 'days' => $days, 'generations' => $generations, 'offsite' => $offsite, 'offsite_available' => $offsiteAllowed, 'plan' => $plan], 'policy_id' => $policy->id];
    }
}
