<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Metering;

/**
 * What the usage watch's metrics are (disk, traffic, inodes, memory, mail) and what each of them may cause (owner
 * decision 9, TASK-0023 metering-core). `MetricRegistry` is keyed by the plan's entitlement keys; the watch speaks in
 * metric keys, and this is the one place that maps the two. The limit kind is read from the registry row, never
 * restated here, so a row changed to `soft` there changes what the platform does here.
 *
 * - **hard**: tells the customer, may block growth (`UsageGuard`) and may order the next plan (auto-upgrade policy).
 * - **soft**: only ever tells the customer — never throttles, never orders, never bills.
 * - A metric on a family it never drove consequences for before (`watched`) is **new**: it is sampled and shown as
 *   observed, and tells, blocks or orders nothing until `onhost.metering.enforce_new_metrics` is switched on.
 */
final class UsageMetrics
{
    /** @var array<string, array{registry:string, unit:string, watched:list<string>}> */
    public const METRICS = [
        'disk' => ['registry' => 'nvme_gb', 'unit' => 'bytes', 'watched' => ['web', 'managed', 'cloud', 'game']],
        'traffic' => ['registry' => 'traffic_gb', 'unit' => 'bytes_month', 'watched' => ['web', 'managed']],
        'inodes' => ['registry' => 'inodes', 'unit' => 'count', 'watched' => ['web', 'managed']],
        'memory' => ['registry' => 'ram_mb', 'unit' => 'bytes', 'watched' => ['cloud', 'game']],
        'mail' => ['registry' => 'quota_mb', 'unit' => 'bytes', 'watched' => ['mail']],
    ];

    /** The metrics read for each family (what a failed reading is recorded as missing). @var array<string, list<string>> */
    public const BY_FAMILY = [
        'web' => ['disk', 'traffic', 'inodes'], 'managed' => ['disk', 'traffic', 'inodes'],
        'cloud' => ['disk', 'memory'], 'game' => ['disk', 'memory'], 'data' => ['disk', 'memory'], 'mail' => ['mail'],
    ];

    /**
     * Test seam: a metric's limit kind as if its registry row said so (no soft metric is sold today).
     *
     * @var array<string, string>
     */
    public static array $kindOverrides = [];

    public static function known(string $metric): bool
    {
        return isset(self::METRICS[$metric]);
    }

    public static function unit(string $metric): string
    {
        return self::METRICS[$metric]['unit'] ?? 'count';
    }

    /** hard | soft | none, from the registry row of the entitlement the metric is measured against. */
    public static function limitKind(string $metric): string
    {
        if (isset(self::$kindOverrides[$metric])) {
            return self::$kindOverrides[$metric];
        }
        $registry = self::METRICS[$metric]['registry'] ?? null;

        return $registry === null ? MetricRegistry::NONE : (MetricRegistry::get($registry)['limit_kind'] ?? MetricRegistry::NONE);
    }

    public static function isSoft(string $metric): bool
    {
        return self::limitKind($metric) === MetricRegistry::SOFT;
    }

    /** Whether the metric never drove anything on this family before metering (and so waits for the operator's switch). */
    public static function isNew(string $metric, string $family): bool
    {
        return ! in_array($family, self::METRICS[$metric]['watched'] ?? [], true);
    }

    /** May a reading of this metric tell the customer (a warning, a "full" notice)? Hard and soft limits may. */
    public static function notifies(string $metric, string $family): bool
    {
        if (! self::known($metric) || ! in_array(self::limitKind($metric), [MetricRegistry::HARD, MetricRegistry::SOFT], true)) {
            return false;
        }

        return ! self::isNew($metric, $family) || (bool) config('onhost.metering.enforce_new_metrics', false);
    }

    /** May a reading of this metric block growth or order a plan? Only a hard limit, and a new metric only once enforced. */
    public static function drivesConsequences(string $metric, string $family): bool
    {
        return self::notifies($metric, $family) && self::limitKind($metric) === MetricRegistry::HARD;
    }

    /** @return list<string> */
    public static function forFamily(string $family): array
    {
        return self::BY_FAMILY[$family] ?? [];
    }
}
