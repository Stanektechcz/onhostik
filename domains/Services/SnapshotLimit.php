<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderException;

/**
 * The number of snapshots a server plan sells, kept (Phase C, C9; PlanPromises KNOWN_GAPS 'snapshots').
 *
 * VPS/VDS plans promise "N snapshotů" (`entitlements.snapshots`) and the panel showed the number — but nothing compared
 * it with what the hypervisor held, so a server could keep taking snapshots until its storage filled up. Now a
 * `snapshot` is refused when the server already holds as many as its plan sells: at the request (422, the panel says
 * why) and once more in the step that takes it, so two requests in a row cannot both slip under the limit.
 *
 * The snapshots the PLATFORM takes before it overwrites something (`pre_rollback`, `pre_reinstall` — protected rows of
 * `backups`) are not the customer's and do not count: a rollback must never use up the plan, nor be refused by it.
 */
final class SnapshotLimit
{
    /** What a plan sells when it names no number (the same default ServiceFeatures has always shown). */
    public const DEFAULT_LIMIT = 3;

    public function __construct(private readonly ServiceFeatures $features) {}

    /** The plan's number; null or 0 means the plan sets no limit. */
    public static function limit(Service $service): ?int
    {
        $limit = (int) (((array) $service->entitlements)['snapshots'] ?? self::DEFAULT_LIMIT);

        return $limit > 0 ? $limit : null;
    }

    /**
     * The listing as the hypervisor gave it, each snapshot saying whether it counts against the plan.
     *
     * @param  list<array<string,mixed>>  $snapshots
     * @return list<array<string,mixed>>
     */
    public static function annotate(Service $service, array $snapshots): array
    {
        $platform = self::platformNames($service);

        return array_map(fn (array $s) => $s + ['counts' => ! in_array((string) ($s['name'] ?? ''), $platform, true)], $snapshots);
    }

    /** @param list<array<string,mixed>> $snapshots */
    public static function used(Service $service, array $snapshots, ?string $except = null): int
    {
        $platform = self::platformNames($service);

        return count(array_filter($snapshots, fn (array $s) => ! in_array((string) ($s['name'] ?? ''), $platform, true) && ($except === null || (string) ($s['name'] ?? '') !== $except)));
    }

    /**
     * Refuses a new snapshot when the plan's number is used up. A hypervisor that does not answer refuses nothing here:
     * the step counts again before it takes the snapshot (a durable queue is not refused for a panel that is away, H02).
     */
    public function assertRoom(Service $service): void
    {
        $limit = self::limit($service);
        if ($limit === null) {
            return;
        }
        try {
            $snapshots = $this->features->resources($service, 'snapshots', true);
        } catch (ProviderException $e) {
            if (! $e->errorCode->isRetryable()) {
                throw $e;
            }

            return;
        }
        self::refuseWhenFull($service, $snapshots, $limit);
    }

    /** @param list<array<string,mixed>> $snapshots */
    public static function refuseWhenFull(Service $service, array $snapshots, int $limit, ?string $except = null): void
    {
        $used = self::used($service, $snapshots, $except);
        if ($used >= $limit) {
            throw new DomainError('feature_limit_reached', "Tarif umožňuje {$limit} snapshotů a server jich má {$used}; nejdřív některý smažte.", 422, ['limit' => $limit, 'used' => $used, 'feature' => 'snapshots']);
        }
    }

    /** @return list<string> the names of the snapshots the platform took for itself */
    private static function platformNames(Service $service): array
    {
        return Backup::query()->where('service_id', $service->id)->where('protected', true)->whereNotNull('remote_id')
            ->whereIn('kind', ['pre_restore', 'pre_rollback', 'pre_reinstall', 'pre_import', 'final'])->pluck('remote_id')->map(fn ($n) => (string) $n)->all();
    }
}
