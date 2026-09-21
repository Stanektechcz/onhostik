<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * Usage watch (audit §5e-1): every active web, managed and VPS service is measured against its plan — disk and
 * traffic quotas on hosting, disk and memory on servers. At 85 % the customer hears about it with the next plan and
 * what switching costs today (`service.usage.high`, once per level and day); at 95 % a service whose policy allows
 * it (`tags.policy.auto_upgrade`) is moved to the next plan through the ordinary plan-change order paid from credit,
 * and the customer is told which order did it. The measurement lives on the service (`tags.usage`) so the panel
 * rows and the summary show it without another node call.
 */
final class UsageWatch
{
    public const WARN_PCT = 85;

    public const CRITICAL_PCT = 95;

    public function __construct(
        private readonly ServiceFeatures $features,
        private readonly ServiceService $services,
        private readonly PlanChangeService $plans,
        private readonly OutboxPublisher $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    /** @return array{checked:int, warned:int, critical:int, upgraded:int, errors:int} */
    public function run(int $limit = 200): array
    {
        $stats = ['checked' => 0, 'warned' => 0, 'critical' => 0, 'upgraded' => 0, 'errors' => 0];
        $services = Service::query()->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->whereIn('family', ['web', 'managed', 'cloud', 'game', 'mail'])
            ->orderBy('id')->limit(max(1, $limit))->get();
        foreach ($services as $service) {
            $stats['checked']++;
            try {
                $metrics = $this->measure($service);
            } catch (Throwable $e) {
                $stats['errors']++;

                continue;
            }
            if ($metrics === []) {
                continue;
            }
            $level = self::level($metrics);
            $tags = (array) ($service->tags ?? []);
            $previous = (array) ($tags['usage'] ?? []);
            $usage = ['level' => $level, 'metrics' => $metrics, 'checked_at' => now()->toIso8601String(), 'notified_level' => $previous['notified_level'] ?? 'ok', 'notified_on' => $previous['notified_on'] ?? null, 'auto_upgrade_on' => $previous['auto_upgrade_on'] ?? null];
            $today = now()->toDateString();
            $upgrade = null;
            if ($level !== 'ok') {
                $upgrade = $this->nextPlan($service);
                $escalated = ($usage['notified_level'] === 'ok') || ($usage['notified_level'] === 'warn' && $level === 'critical') || $usage['notified_on'] !== $today;
                $order = null;
                if ($level === 'critical' && ! empty($tags['policy']['auto_upgrade']) && $upgrade !== null && $usage['auto_upgrade_on'] !== $today) {
                    $usage['auto_upgrade_on'] = $today;
                    $order = $this->autoUpgrade($service, $upgrade);
                    if ($order !== null) {
                        $stats['upgraded']++;
                        $escalated = true;
                    }
                }
                if ($escalated) {
                    $usage['notified_level'] = $level;
                    $usage['notified_on'] = $today;
                    $stats[$level === 'critical' ? 'critical' : 'warned']++;
                    $this->outbox->publish(GenericEvent::of('service.usage.high', 'service', $service->id, [
                        'level' => $level, 'metrics' => $metrics, 'top' => self::top($metrics), 'hostname' => $service->hostname, 'label' => $service->label,
                        'plan' => $upgrade['current'] ?? null, 'upgrade' => $upgrade === null ? null : array_diff_key($upgrade, ['current' => 1]),
                        'auto_upgrade' => ! empty($tags['policy']['auto_upgrade']), 'order' => $order,
                    ], $service->organization_id));
                }
            } else {
                $usage['notified_level'] = 'ok';
            }
            $service->forceFill(['tags' => array_merge($tags, ['usage' => $usage])])->save();
        }

        return $stats;
    }

    /**
     * Per metric: used, limit and the share used, only for metrics the plan limits.
     *
     * @return array<string, array{used:int, limit:int, pct:int}>
     */
    public function measure(Service $service): array
    {
        $entitlements = (array) ($service->entitlements ?? []);
        $out = [];
        if (in_array($service->family, ['web', 'managed'], true)) {
            $quotas = $this->features->resources($service, 'quotas', true, []);
            $diskLimit = (int) ($quotas['disk_limit_bytes'] ?? 0) ?: (int) (($entitlements['nvme_gb'] ?? 0) * 1024 ** 3) ?: (int) (($entitlements['quota_mb'] ?? 0) * 1024 ** 2);
            self::metric($out, 'disk', (int) ($quotas['disk_used_bytes'] ?? 0), $diskLimit);
            $trafficLimit = (int) ($quotas['traffic_limit_bytes'] ?? 0) ?: (int) (($entitlements['traffic_gb'] ?? 0) * 1024 ** 3);
            self::metric($out, 'traffic', (int) ($quotas['traffic_used_bytes'] ?? 0), $trafficLimit);
            // both panels count the files of a site (`inodes_used`); the plan sold a number and nothing ever compared the two,
            // so a site that had eaten its whole file allowance heard about it from the node, not from us (audit §5ad)
            self::metric($out, 'inodes', (int) ($quotas['inodes_used'] ?? 0), (int) ($entitlements['inodes'] ?? 0));
        } elseif ($service->family === 'cloud' || $service->family === 'game') {
            // VPS and game servers report live usage; game servers count against the plan's disk and memory the same way (audit §5f-3)
            $metrics = $this->services->usage($service)->metrics;
            self::metric($out, 'disk', (int) ($metrics['disk_bytes'] ?? 0), (int) (($entitlements['nvme_gb'] ?? $service->desired_spec['nvme_gb'] ?? 0) * 1024 ** 3));
            self::metric($out, 'memory', (int) ($metrics['mem_bytes'] ?? 0), (int) (($entitlements['ram_mb'] ?? $service->desired_spec['ram_mb'] ?? 0) * 1024 ** 2));
        } elseif ($service->family === 'mail') {
            // mail domains: the mailboxes' quotas summed (the plan's total when it sells one, else the sum of what the mailboxes were given)
            if (empty($this->features->features($service)['mail_usage']['enabled'])) {
                return [];
            }
            $boxes = (array) ($this->features->resources($service, 'mail_usage', true, [])['mailboxes'] ?? []);
            $used = array_sum(array_map(fn ($b) => (int) ($b['used_bytes'] ?? 0), $boxes));
            $limit = (int) (($entitlements['mail_quota_gb'] ?? 0) * 1024 ** 3) ?: (int) (($entitlements['quota_mb'] ?? 0) * 1024 ** 2) ?: array_sum(array_map(fn ($b) => (int) ($b['quota_bytes'] ?? 0), $boxes));
            self::metric($out, 'mail', $used, $limit);
        }

        return $out;
    }

    /** @param  array<string, array{used:int, limit:int, pct:int}>  $metrics */
    public static function level(array $metrics): string
    {
        $max = max(array_map(fn (array $m) => $m['pct'], $metrics) ?: [0]);

        return $max >= self::CRITICAL_PCT ? 'critical' : ($max >= self::WARN_PCT ? 'warn' : 'ok');
    }

    /** The metric closest to its limit. @param  array<string, array{used:int, limit:int, pct:int}>  $metrics @return array{key:string, pct:int}|null */
    public static function top(array $metrics): ?array
    {
        $best = null;
        foreach ($metrics as $key => $m) {
            if ($best === null || $m['pct'] > $best['pct']) {
                $best = ['key' => $key, 'pct' => $m['pct']];
            }
        }

        return $best;
    }

    /** The next plan up with today's price (null when the service already runs the top plan or has no plan). @return array<string,mixed>|null */
    public function nextPlan(Service $service): ?array
    {
        $organization = Organization::query()->find($service->organization_id);
        if ($organization === null) {
            return null;
        }
        $options = $this->plans->options($service, (string) $organization->currency);
        $upgrades = array_values(array_filter($options['plans'], fn (array $p) => $p['direction'] === 'upgrade'));
        usort($upgrades, fn (array $a, array $b) => $a['price']->minor <=> $b['price']->minor);
        $next = $upgrades[0] ?? null;
        if ($next === null || ! $options['changeable']) {
            return null;
        }

        return ['current' => $options['current_plan'], 'plan_key' => $next['plan_key'], 'name' => $next['name'], 'price' => $next['price'], 'change_now' => $next['change_now'], 'period' => $options['period']];
    }

    /** @param  array<string,mixed>  $upgrade @return array{number:string, state:string}|null */
    private function autoUpgrade(Service $service, array $upgrade): ?array
    {
        try {
            $order = $this->plans->orderUpgrade($service, (string) $upgrade['plan_key'], CommandContext::system('usage-watch'), 'auto');
        } catch (DomainError $e) {
            $this->audit->record(CommandContext::system('usage-watch')->withScope($service->organization_id, $service->project_id), 'service.auto_upgrade', 'refused', ['plan' => $upgrade['plan_key'], 'error' => $e->error, 'message' => $e->getMessage()], 'service', $service->id);

            return null;
        }

        return ['number' => $order->number, 'state' => $order->state];
    }

    /** @param  array<string, array{used:int, limit:int, pct:int}>  $out */
    private static function metric(array &$out, string $key, int $used, int $limit): void
    {
        if ($limit <= 0) {
            return;
        }
        $out[$key] = ['used' => max(0, $used), 'limit' => $limit, 'pct' => (int) min(999, round(max(0, $used) / $limit * 100))];
    }

    /** Human wording of a metric for notifications. */
    public static function metricLabel(string $key, string $locale = 'cs'): string
    {
        return match ($key) {
            'disk' => $locale === 'cs' ? 'prostor' : 'disk space', 'traffic' => $locale === 'cs' ? 'přenos dat' : 'traffic', 'memory' => $locale === 'cs' ? 'paměť' : 'memory', 'mail' => $locale === 'cs' ? 'poštovní schránky' : 'mailboxes',
            'inodes' => $locale === 'cs' ? 'počet souborů' : 'file count', default => $key,
        };
    }

    public static function money(Money $money): string
    {
        return $money->format();
    }
}
