<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Services\Metering\UsageMetrics;
use Onhost\Domain\Services\Metering\UsageReading;
use Onhost\Domain\Services\Metering\UsageRecorder;
use Onhost\Domain\Services\Metering\WebDiskTotal;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderException;
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
 *
 * Metering (TASK-0023, owner decisions 9 and 12): every reading is also kept as a sample (`UsageRecorder`), and a
 * number the panel could not give is recorded as not measured — it used to become 0, i.e. "0 %, fine". A service
 * whose readings all failed shows level `unknown`. A first reading of a metric is only a baseline: it may tell the
 * customer, but it blocks nothing (`UsageGuard`) and orders nothing — the automatic upgrade needs two critical readings
 * in a row. With the default-off rule `usage.rotation` the watch goes round every service, measured-longest-ago
 * first, instead of the first 200 by id.
 *
 * The plan's space in total (TASK-0023 web-disk-total): files, databases and mail of a web plan are added up
 * (`WebDiskTotal`) and kept beside the metrics as `tags.usage.disk_total`. They enter the metrics — and so the level,
 * the notice, the automatic upgrade and `UsageGuard` — only once the total is enforced for the service.
 */
final class UsageWatch
{
    public const WARN_PCT = 85;

    public const CRITICAL_PCT = 95;

    /** Full: the plan is used up. ISPConfig stops the site writing at this point; on aaPanel nothing does, so the platform does. */
    public const FULL_PCT = 100;

    /** How long a measurement is taken as the truth: an old one must not hold a customer's site back for ever. */
    public const FRESH_HOURS = 26;

    /** With rotation on, every service is visited at least once within this many hourly runs (below FRESH_HOURS). */
    public const ROTATION_HOURS = 24;

    public const ROTATION_RULE = 'usage.rotation';

    /** The families visited as before; the managed database (`data`) only joins with rotation on. */
    public const FAMILIES = ['web', 'managed', 'cloud', 'game', 'mail'];

    public function __construct(
        private readonly ServiceFeatures $features,
        private readonly ServiceService $services,
        private readonly PlanChangeService $plans,
        private readonly OutboxPublisher $outbox,
        private readonly AuditRecorder $audit,
        private readonly UsageRecorder $recorder,
        private readonly AutomationLedger $ledger,
        private readonly WebDiskTotal $diskTotal,
    ) {}

    /** @return array<string, int|null> checked, warned, critical, full, upgraded, errors, unavailable, baseline, observed_only, rotation, lag_hours */
    public function run(int $limit = 200): array
    {
        $rotation = $this->ledger->enabled(self::ROTATION_RULE);
        $stats = ['checked' => 0, 'warned' => 0, 'critical' => 0, 'full' => 0, 'upgraded' => 0, 'errors' => 0, 'unavailable' => 0, 'baseline' => 0, 'observed_only' => 0, 'rotation' => $rotation ? 1 : 0];
        $eligible = $this->eligible($rotation);
        $stats['lag_hours'] = self::lagHours($eligible);
        foreach ($this->select($eligible, $rotation, $limit) as $service) {
            $stats['checked']++;
            [$readings, $error] = $this->read($service);
            // the reading is kept even when it failed (as "not measured"), and the service goes to the back of the queue
            // either way, so a dead panel cannot hold the rotation up; a query update keeps the tags and updated_at as they are
            $this->recorder->record($service, $readings, now());
            Service::query()->whereKey($service->id)->toBase()->update(['usage_checked_at' => now()]);
            if ($error !== null) {
                $stats['errors']++;

                continue;
            }
            $total = $this->diskTotal($service);
            if ($total !== null) {
                $this->recorder->record($service, WebDiskTotal::readings($total, WebDiskTotal::enforcedFor($service)), now());
            }
            $this->apply($service, $readings, $stats, $total);
        }

        return $stats;
    }

    /**
     * What switching `usage.rotation` on would do for the next batch, read from the panels but written nowhere: no sample,
     * no tag, no event, no order, no ledger entry. The operator reads it before deciding.
     *
     * @return array<string, int|null>
     */
    public function preview(int $limit = 200): array
    {
        $stats = ['eligible' => 0, 'checked' => 0, 'never_measured' => 0, 'ok' => 0, 'warn' => 0, 'critical' => 0, 'full' => 0, 'unknown' => 0, 'would_notify' => 0,
            'would_auto_upgrade' => 0, 'would_block' => 0, 'unavailable' => 0, 'baseline' => 0, 'observed_only' => 0, 'errors' => 0];
        $eligible = $this->eligible(true);
        $stats['eligible'] = (clone $eligible)->count();
        $stats['lag_hours'] = self::lagHours($eligible);
        $today = now()->toDateString();
        foreach ($this->select($eligible, true, $limit) as $service) {
            $stats['checked']++;
            $stats['never_measured'] += $service->usage_checked_at === null ? 1 : 0;
            [$readings, $error] = $this->read($service);
            if ($error !== null) {
                $stats['errors']++;

                continue;
            }
            [$metrics, $observed, $unavailable] = self::split($readings, (string) $service->family);
            $stats['unavailable'] += $unavailable === [] ? 0 : 1;
            if ($metrics === []) {
                $stats[$unavailable === [] ? 'observed_only' : 'unknown'] += $unavailable === [] && $observed === [] ? 0 : 1;

                continue;
            }
            $previous = (array) data_get($service->tags, 'usage', []);
            $level = self::level($metrics);
            $baseline = self::baselineOf($metrics, $previous);
            $stats[$level]++;
            $stats['baseline'] += $baseline === [] ? 0 : 1;
            if ($level !== 'ok' && (self::rank($level) > self::rank((string) ($previous['notified_level'] ?? 'ok')) || ($previous['notified_on'] ?? null) !== $today)) {
                $stats['would_notify']++;
            }
            if (in_array($level, ['critical', 'full'], true) && ! empty(data_get($service->tags, 'policy.auto_upgrade')) && ($previous['auto_upgrade_on'] ?? null) !== $today
                && self::confirmedCritical((array) ($previous['metrics'] ?? []), $metrics, (string) $service->family) && $this->nextPlan($service) !== null) {
                $stats['would_auto_upgrade']++;
            }
            $stats['would_block'] += self::wouldBlock($service, $metrics, $baseline) ? 1 : 0;
        }

        return $stats;
    }

    /**
     * Per metric: used, limit and the share used, only for metrics the plan limits and that may tell the customer.
     *
     * @return array<string, array{used:int, limit:int, pct:int}>
     */
    public function measure(Service $service): array
    {
        return self::split($this->readings($service), (string) $service->family)[0];
    }

    /**
     * Every metric the service's panel reports, as typed readings: a number the panel did not give is `unavailable`,
     * never 0; a number with no limit in the plan is still a reading (observed). Throws when the panel cannot be asked.
     *
     * @return list<UsageReading>
     */
    public function readings(Service $service): array
    {
        $entitlements = (array) ($service->entitlements ?? []);
        if (in_array($service->family, ['web', 'managed'], true)) {
            $quotas = $this->features->resources($service, 'quotas', true, []);
            $diskLimit = (int) ($quotas['disk_limit_bytes'] ?? 0) ?: (int) (($entitlements['nvme_gb'] ?? 0) * 1024 ** 3) ?: (int) (($entitlements['quota_mb'] ?? 0) * 1024 ** 2);
            $trafficLimit = (int) ($quotas['traffic_limit_bytes'] ?? 0) ?: (int) (($entitlements['traffic_gb'] ?? 0) * 1024 ** 3);

            // both panels count the files of a site (`inodes_used`); the plan sold a number and nothing ever compared the two,
            // so a site that had eaten its whole file allowance heard about it from the node, not from us (audit §5ad)
            return [
                self::reading('disk', $quotas['disk_used_bytes'] ?? null, $diskLimit, 'quotas'),
                self::reading('traffic', $quotas['traffic_used_bytes'] ?? null, $trafficLimit, 'quotas'),
                self::reading('inodes', $quotas['inodes_used'] ?? null, (int) ($entitlements['inodes'] ?? 0), 'quotas'),
            ];
        }
        if (in_array($service->family, ['cloud', 'game', 'data'], true)) {
            // VPS, game servers and managed databases report live usage; Proxmox gives no used disk at all, which is now
            // "not measured" instead of a VPS that was always at 0 % (audit §5f-3)
            $metrics = $this->services->usage($service)->metrics;

            return [
                self::reading('disk', $metrics['disk_bytes'] ?? null, (int) (($entitlements['nvme_gb'] ?? $service->desired_spec['nvme_gb'] ?? 0) * 1024 ** 3), 'usage'),
                self::reading('memory', $metrics['mem_bytes'] ?? null, (int) (($entitlements['ram_mb'] ?? $service->desired_spec['ram_mb'] ?? 0) * 1024 ** 2), 'usage'),
            ];
        }
        if ($service->family === 'mail') {
            return [$this->mailReading($service, $entitlements)];
        }

        return [];
    }

    /** @param  array<string, array{used:int, limit:int, pct:int}>  $metrics */
    public static function level(array $metrics): string
    {
        $max = max(array_map(fn (array $m) => $m['pct'], $metrics) ?: [0]);

        return $max >= self::FULL_PCT ? 'full' : ($max >= self::CRITICAL_PCT ? 'critical' : ($max >= self::WARN_PCT ? 'warn' : 'ok'));
    }

    /** Where a level stands on the ladder ok → warn → critical → full: the customer hears when it rises, and once a day. */
    public static function rank(string $level): int
    {
        return match ($level) {
            'full' => 3, 'critical' => 2, 'warn' => 1, default => 0
        };
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

    /**
     * Readings sorted by what they may do: `metrics` (a number against a limit, may tell the customer), `observed`
     * (a number with no limit, or a metric not yet enforced — value only) and `unavailable` (a limited metric the panel
     * did not report — metric => reason code).
     *
     * @param  list<UsageReading>  $readings
     * @return array{0: array<string, array{used:int, limit:int, pct:int}>, 1: array<string,int>, 2: array<string,string>}
     */
    public static function split(array $readings, string $family): array
    {
        $metrics = $observed = $unavailable = [];
        foreach ($readings as $reading) {
            $limited = $reading->limit !== null && UsageMetrics::notifies($reading->metric, $family);
            if ($reading->value !== null && $limited) {
                $metrics[$reading->metric] = ['used' => $reading->value, 'limit' => (int) $reading->limit, 'pct' => (int) $reading->pct()];
            } elseif ($reading->value !== null) {
                $observed[$reading->metric] = $reading->value;
            } elseif ($limited) {
                $unavailable[$reading->metric] = (string) ($reading->reason ?? 'not_reported');
            }
        }

        return [$metrics, $observed, $unavailable];
    }

    /**
     * The metrics read for the first time: neither measured nor missed before. A first reading tells the customer but
     * blocks and orders nothing (`UsageGuard`, auto-upgrade).
     *
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $previous  the service's previous `tags.usage`
     * @return list<string>
     */
    public static function baselineOf(array $metrics, array $previous): array
    {
        $seen = array_merge(array_keys((array) ($previous['metrics'] ?? [])), array_keys((array) ($previous['unavailable'] ?? [])));

        return array_values(array_filter(array_map('strval', array_keys($metrics)), fn (string $key) => ! in_array($key, $seen, true)));
    }

    /**
     * Two critical readings in a row of the same hard, enforced metric: only then is a plan ordered automatically.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, array{used:int, limit:int, pct:int}>  $now
     */
    public static function confirmedCritical(array $before, array $now, string $family): bool
    {
        foreach ($now as $key => $metric) {
            if ($metric['pct'] >= self::CRITICAL_PCT && (int) data_get($before, "{$key}.pct", 0) >= self::CRITICAL_PCT && UsageMetrics::drivesConsequences((string) $key, $family)) {
                return true;
            }
        }

        return false;
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

    /** Human wording of a metric for notifications. */
    public static function metricLabel(string $key, string $locale = 'cs'): string
    {
        return match ($key) {
            'disk' => $locale === 'cs' ? 'prostor' : 'disk space', 'traffic' => $locale === 'cs' ? 'přenos dat' : 'traffic', 'memory' => $locale === 'cs' ? 'paměť' : 'memory', 'mail' => $locale === 'cs' ? 'poštovní schránky' : 'mailboxes',
            'inodes' => $locale === 'cs' ? 'počet souborů' : 'file count',
            WebDiskTotal::METRIC => $locale === 'cs' ? 'prostor tarifu celkem (soubory, databáze, pošta)' : 'plan storage in total (files, databases, mail)', default => $key,
        };
    }

    public static function money(Money $money): string
    {
        return $money->format();
    }

    /**
     * One service's readings turned into `tags.usage`, the notice and the automatic upgrade.
     *
     * @param  list<UsageReading>  $readings
     * @param  array<string, int|null>  $stats
     * @param  array<string, mixed>|null  $total  the plan's total (web/managed owners only)
     */
    private function apply(Service $service, array $readings, array &$stats, ?array $total = null): void
    {
        [$metrics, $observed, $missing] = self::split($readings, (string) $service->family);
        $enforced = $total === null ? null : WebDiskTotal::metricOf($total);
        if ($enforced !== null && WebDiskTotal::enforcedFor($service)) { // before the date the total is only shown
            $metrics[WebDiskTotal::METRIC] = $enforced;
        }
        $tags = (array) ($service->tags ?? []);
        if ($metrics === [] && $missing === []) { // nothing limited to talk about: the samples hold the numbers, the tags stay as they were
            $stats['observed_only'] += $observed === [] ? 0 : 1;
            if ($total !== null) {
                $service->forceFill(['tags' => array_merge($tags, ['usage' => array_merge((array) ($tags['usage'] ?? []), ['disk_total' => $total])])])->save();
            }

            return;
        }
        $previous = (array) ($tags['usage'] ?? []);
        $unavailable = self::unavailableOf($missing, $previous);
        $stats['unavailable'] += $unavailable === [] ? 0 : 1;
        if ($metrics === []) {
            // not one limited number came back: say so instead of "0 %, fine"; what the customer was told and when stays,
            // so the next real reading does not repeat a notice, and the last real reading ages out of UsageGuard on its own
            $usage = array_merge(array_intersect_key($previous, ['checked_at' => 1, 'notified_level' => 1, 'notified_on' => 1, 'auto_upgrade_on' => 1, 'baseline' => 1]),
                ['level' => 'unknown', 'metrics' => [], 'unavailable' => $unavailable, 'observed' => $observed, 'unavailable_at' => now()->toIso8601String()], $total === null ? [] : ['disk_total' => $total]);
            $service->forceFill(['tags' => array_merge($tags, ['usage' => $usage])])->save();

            return;
        }
        $level = self::level($metrics);
        $baseline = self::baselineOf($metrics, $previous);
        $stats['baseline'] += $baseline === [] ? 0 : 1;
        $usage = ['level' => $level, 'metrics' => $metrics, 'checked_at' => now()->toIso8601String(), 'notified_level' => $previous['notified_level'] ?? 'ok', 'notified_on' => $previous['notified_on'] ?? null, 'auto_upgrade_on' => $previous['auto_upgrade_on'] ?? null,
            'baseline' => $baseline, 'observed' => $observed, 'unavailable' => $unavailable] + ($total === null ? [] : ['disk_total' => $total]);
        $today = now()->toDateString();
        $upgrade = null;
        if ($level !== 'ok') {
            $upgrade = $this->nextPlan($service);
            $escalated = self::rank($level) > self::rank((string) $usage['notified_level']) || $usage['notified_on'] !== $today;
            $order = null;
            if (in_array($level, ['critical', 'full'], true) && ! empty($tags['policy']['auto_upgrade']) && $upgrade !== null && $usage['auto_upgrade_on'] !== $today
                && self::confirmedCritical((array) ($previous['metrics'] ?? []), $metrics, (string) $service->family)) {
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
                $stats[match ($level) {
                    'full' => 'full', 'critical' => 'critical', default => 'warned'
                }]++;
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

    /**
     * The plan's total for a paying web/managed service (an included site's space is its owner's), never null-as-0: a total
     * that could not be read at all is stored as unavailable with the reason.
     *
     * @return array<string, mixed>|null
     */
    private function diskTotal(Service $service): ?array
    {
        if (! in_array($service->family, ['web', 'managed'], true) || IncludedServices::isIncluded($service)) {
            return null;
        }
        try {
            return $this->diskTotal->measure($service);
        } catch (Throwable $e) {
            return ['total' => null, 'limit' => WebDiskTotal::limitBytes($service), 'pct' => null, 'quality' => WebDiskTotal::UNAVAILABLE, 'reason' => self::errorCode($e), 'checked_at' => now()->toIso8601String()];
        }
    }

    /** @return array{0: list<UsageReading>, 1: string|null} the readings, and the error code when the panel could not be asked */
    private function read(Service $service): array
    {
        try {
            return [$this->readings($service), null];
        } catch (Throwable $e) {
            $code = self::errorCode($e);

            return [array_map(fn (string $metric) => UsageReading::unavailable($metric, null, $code, 'watch'), UsageMetrics::forFamily((string) $service->family)), $code];
        }
    }

    /** @param  array<string, mixed>  $entitlements */
    private function mailReading(Service $service, array $entitlements): UsageReading
    {
        // mail domains: the mailboxes' quotas summed (the plan's total when it sells one, else the sum of what the mailboxes were given)
        if (empty($this->features->features($service)['mail_usage']['enabled'])) {
            return UsageReading::unavailable('mail', null, 'unsupported', 'mail_usage');
        }
        $boxes = array_values(array_filter((array) ($this->features->resources($service, 'mail_usage', true, [])['mailboxes'] ?? []), 'is_array'));
        $limit = (int) (($entitlements['mail_quota_gb'] ?? 0) * 1024 ** 3) ?: (int) (($entitlements['quota_mb'] ?? 0) * 1024 ** 2) ?: array_sum(array_map(fn (array $b) => (int) ($b['quota_bytes'] ?? 0), $boxes));
        $known = array_values(array_filter($boxes, fn (array $b) => is_numeric($b['used_bytes'] ?? null)));
        if ($boxes !== [] && $known === []) {
            return UsageReading::unavailable('mail', $limit, 'not_reported', 'mail_usage');
        }
        $used = (int) array_sum(array_map(fn (array $b) => max(0, (int) $b['used_bytes']), $known));

        return count($known) < count($boxes) ? UsageReading::estimated('mail', $used, $limit, 'partial', 'mail_usage') : UsageReading::measured('mail', $used, $limit, 'mail_usage');
    }

    private static function reading(string $metric, mixed $value, int $limit, string $source): UsageReading
    {
        return is_numeric($value)
            ? UsageReading::measured($metric, max(0, (int) $value), $limit, $source)
            : UsageReading::unavailable($metric, $limit, 'not_reported', $source);
    }

    /**
     * What was not reported this time, since when, and the last number known for it.
     *
     * @param  array<string, string>  $missing
     * @param  array<string, mixed>  $previous
     * @return array<string, array{reason:string, since:string, last:array<string,mixed>|null}>
     */
    private static function unavailableOf(array $missing, array $previous): array
    {
        $out = [];
        foreach ($missing as $metric => $reason) {
            $before = (array) data_get($previous, "unavailable.{$metric}", []);
            $last = is_array($previous['metrics'][$metric] ?? null) ? $previous['metrics'][$metric] + ['at' => $previous['checked_at'] ?? null] : ($before['last'] ?? null);
            $out[$metric] = ['reason' => $reason, 'since' => (string) ($before['since'] ?? now()->toIso8601String()), 'last' => is_array($last) ? $last : null];
        }

        return $out;
    }

    /**
     * Whether `UsageGuard` would refuse growth on this reading: a web/managed storage metric over its plan that is not a
     * first reading and drives consequences.
     *
     * @param  array<string, array{used:int, limit:int, pct:int}>  $metrics
     * @param  list<string>  $baseline
     */
    private static function wouldBlock(Service $service, array $metrics, array $baseline): bool
    {
        if (! in_array($service->family, ['web', 'managed'], true)) {
            return false;
        }
        foreach (UsageGuard::STORAGE_METRICS as $key) {
            if (($metrics[$key]['pct'] ?? 0) >= self::FULL_PCT && ! in_array($key, $baseline, true) && UsageMetrics::drivesConsequences($key, (string) $service->family)) {
                return true;
            }
        }

        return false;
    }

    /** @return Builder<Service> */
    private function eligible(bool $rotation): Builder
    {
        return Service::query()->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])
            ->whereIn('family', $rotation ? [...self::FAMILIES, 'data'] : self::FAMILIES);
    }

    /**
     * Rotation off: the first `$limit` by id, exactly as before. Rotation on: the least recently measured first, and never
     * fewer than a share that goes round every service within ROTATION_HOURS runs — there is no fixed cap.
     *
     * @param  Builder<Service>  $eligible
     * @return Collection<int, Service>
     */
    private function select(Builder $eligible, bool $rotation, int $limit): Collection
    {
        if (! $rotation) {
            return (clone $eligible)->orderBy('id')->limit(max(1, $limit))->get();
        }
        $batch = max(1, $limit, (int) ceil((clone $eligible)->count() / self::ROTATION_HOURS));

        return (clone $eligible)->orderByRaw('case when usage_checked_at is null then 0 else 1 end')->orderBy('usage_checked_at')->orderBy('id')->limit($batch)->get();
    }

    /** @param  Builder<Service>  $eligible hours since the service measured longest ago was measured (null: none measured yet) */
    private static function lagHours(Builder $eligible): ?int
    {
        $oldest = (clone $eligible)->min('usage_checked_at');

        return $oldest === null ? null : (int) floor(CarbonImmutable::parse((string) $oldest)->diffInHours(now(), true));
    }

    private static function errorCode(Throwable $e): string
    {
        return match (true) {
            $e instanceof DomainError => $e->error,
            $e instanceof ProviderException => 'provider_'.$e->errorCode->value,
            default => 'error',
        };
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
}
