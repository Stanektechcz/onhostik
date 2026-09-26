<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Commands;

use Carbon\CarbonImmutable;
use Onhost\Domain\Orders\CreditOrderPolicy;
use Onhost\Domain\Provisioning\AutomationLedger;
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
use Onhost\Platform\Commands\CommandAuthorizer;
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
            // declarative spec (audit §5e-6): converge the service on a document; every change is an ordinary action that asks
            // its own permission (TASK-0029 D29.7), not the `service.manage` this command was checked with
            'spec.apply' => app(ServiceSpecService::class)->apply($service, (array) ($params['spec'] ?? []), $context, $command->idempotencyKey()),
            // the customer's start time for a scheduled migration (audit §5h-3)
            'migration.window' => ['migration' => app(ServiceMigrationService::class)->reschedule($service, CarbonImmutable::parse((string) ($params['starts_at'] ?? '')), $context)],
            // per-service automation policy (audit §5e-1): whether the usage watch may move the service to the next plan on its own
            'policy.set' => (function () use ($service, $params, $context) {
                $tags = (array) ($service->tags ?? []);
                $policy = array_merge((array) ($tags['policy'] ?? []), array_intersect_key(array_map(fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN), $params), ['auto_upgrade' => 1, 'availability_alerts' => 1]));
                // owner decision 20 (TASK-0021): switching the automatic upgrade on is a standing order paid from credit (the usage
                // watch places it as the platform) — only the owner or the billing admin commits it; switching it off stays open
                $upgradeSwitchedOn = ! empty($policy['auto_upgrade']) && empty(((array) ($tags['policy'] ?? []))['auto_upgrade']);
                if ($upgradeSwitchedOn) {
                    app(CreditOrderPolicy::class)->assertMaySpend($service->organization_id, $context, 'Požádejte vlastníka o zapnutí automatického navýšení tarifu.');
                }
                $service->forceFill(['tags' => array_merge($tags, ['policy' => $policy])])->save();
                $this->audit->record($context->withScope($service->organization_id, $service->project_id), 'service.policy', 'succeeded', $policy + ($upgradeSwitchedOn ? ['auto_upgrade_enabled_by' => $context->onBehalfOfUserId ?? $context->actorId] : []), 'service', $service->id);

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
        if (isset($plan['frequency'])) { // `1h` on the price list is hourly only under the owner's rule (TASK-0024); otherwise as before
            $plan['frequency'] = BackupScheduler::normalizeFrequency((string) $plan['frequency'], app(AutomationLedger::class)->enabled(BackupScheduler::AS_SOLD_RULE));
        }
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
        $stepUp = $this->assertMayThin($service, (array) ($schedule['options'] ?? []), ['frequency' => $frequency, 'days' => $days, 'generations' => $generations], $context);
        $policy = BackupPolicy::query()->updateOrCreate(['service_id' => $service->id], ['product_key' => $service->product_key, 'schedule' => ['frequency' => $frequency], 'retention' => ['days' => $days, 'generations' => $generations], 'offsite' => $offsite]);
        $restarted = BackupScheduler::resume($service); // a schedule that had stopped itself after repeated failures starts again here, and only here (H447)
        $this->audit->record($context->withScope($service->organization_id), 'service.backup_schedule.set', 'succeeded', ['frequency' => $frequency, 'days' => $days, 'generations' => $generations, 'offsite' => $offsite], 'service', $service->id, stepUp: $stepUp);
        $this->features->forget($service);

        return ['schedule' => ['frequency' => $frequency, 'days' => $days, 'generations' => $generations, 'offsite' => $offsite, 'offsite_available' => $offsiteAllowed, 'plan' => $plan], 'policy_id' => $policy->id, 'restarted' => $restarted];
    }

    /**
     * Fewer days or generations than the schedule keeps now is a deletion: the next BackupScheduler tick prunes every scheduled
     * backup beyond them (TASK-0029 review round 2, HIGH). So lowering asks exactly what deleting a backup by hand asks — the
     * bus's own decision on `backup.delete` at this service: `backup.delete`, a fresh step-up, never a token. Otherwise a
     * developer or a `services:power` token, which lost backup deletion (D29.2), could set one generation and let the tick do it.
     * The frequency is part of it (security review round 2, MEDIUM, fix round 1): with `backups.as_sold` off the prune keeps
     * the newest N generations, so weekly x 7 reaches seven weeks back and daily x 7 one — switching to daily took six weeks of
     * history within a week with days and generations untouched. So a change is thinning when the history kept afterwards
     * reaches less far back (BackupScheduler::historyReach — the prune's own keeper decision and reach, not a second
     * formula), or when it keeps fewer days or generations. Keeping or reaching further stays `service.manage`, as this
     * command was checked.
     *
     * @param  array<string,mixed>  $options  the plan's backup_schedule options, as the scheduler reads them
     * @param  array{frequency:string, days:int, generations:int}  $wanted
     * @return string|null the step-up the thinning was made under (for the audit row), null when nothing is thinned
     */
    private function assertMayThin(Service $service, array $options, array $wanted, CommandContext $context): ?string
    {
        // both sides resolved exactly as the tick resolves a schedule: what it runs today (the stored policy, else the plan)
        // and what it would run once `$wanted` is stored — also when no schedule runs yet (it would read this once it runs)
        $scheduler = app(BackupScheduler::class);
        $now = $scheduler->scheduleFrom($service, $options);
        $next = $scheduler->scheduleFrom($service, $options, null, $wanted);
        $thins = $next['days'] < $now['days'] || $next['generations'] < $now['generations']
            || BackupScheduler::historyReach($service, $next) < BackupScheduler::historyReach($service, $now);
        if (! $thins) {
            return null;
        }
        $delete = new ServiceActionCommand($service->organization_id, 'backup-schedule-thin:'.$service->id, ['service_id' => $service->id, 'project_id' => $service->project_id, 'action' => 'backup.delete', 'params' => []]);
        $decision = app(CommandAuthorizer::class)->authorize($delete, $context);
        if (! $decision->allowed) {
            throw match ($decision->requirement) {
                'step_up' => new DomainError('step_up_required', 'Keeping fewer backups or a shorter history deletes the ones beyond it; confirm it with a fresh step-up.', 403, ['requirement' => 'step_up', 'help' => '/v1/auth/step-up']),
                'approval', 'human' => new DomainError('approval_required', $decision->reason ?? 'A person has to confirm this.', 403, ['requirement' => $decision->requirement === 'human' ? 'human' : 'approval']),
                default => DomainError::forbidden(($decision->reason ?? 'Permission denied').' (keeping fewer backups or a shorter history deletes the ones beyond it)'),
            };
        }

        return $decision->stepUpMethod;
    }
}
