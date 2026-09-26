<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Carbon\CarbonImmutable;
use Onhost\Domain\Services\Metering\UsageMetrics;
use Onhost\Domain\Services\Metering\WebDiskTotal;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Errors\DomainError;

/**
 * What happens when a plan is used up. The platform measured it (`UsageWatch`: 85 % tells the customer, 95 % may
 * order the next plan) and then did nothing at all at 100 %.
 *
 * On ISPConfig the node itself stops a full site writing — its `hd_quota` is a filesystem quota — and the customer
 * sees a site that suddenly cannot save anything, with no word from us. On aaPanel there is **no quota at all**: a
 * single site can go on growing until the whole node's disk is full, and then every other customer on that node
 * stops working too. Nothing is sold with that in it.
 *
 * So a service whose last measurement is over its plan may not be made to store more: the platform refuses its own
 * actions that grow it and says what to do. Everything that makes room or protects the data — deleting files and
 * databases, backups, restores, a plan change — goes through, because those are the ways out.
 *
 * The measurement is the one `UsageWatch` writes on the service (no panel call here, so nothing is slower for it),
 * and it counts only while it is fresh: a stale number must never hold a customer's site back for ever.
 */
final class UsageGuard
{
    /**
     * Actions that make the service store more. A name that is not here is not refused — the list is the promise.
     *
     * @var list<string>
     */
    public const GROWING = [
        'file.save', 'file.copy', 'file.mkdir', 'file.extract', 'file.archive', 'app.install',
        'database.create', 'database.import', 'dbuser.create',
        'site.create', 'staging.create', 'staging.refresh', 'staging.push', 'import.run', 'deploy.run',
        'wp.install', 'wp.plugin', 'mailbox.create', 'mailbox.restore',
    ];

    /**
     * The metrics that mean "there is no room on the node for more of this service". `disk_total` (files + databases +
     * mail, TASK-0023) counts only once it is enforced for the service (`WebDiskTotal::enforcedFor`).
     */
    public const STORAGE_METRICS = ['disk', 'inodes', WebDiskTotal::METRIC];

    /**
     * The measurement the platform holds, if it is fresh enough to act on.
     *
     * @return array{key:string, used:int, limit:int, pct:int}|null
     */
    public static function full(Service $service): ?array
    {
        $usage = (array) data_get($service->tags, 'usage', []);
        $checked = isset($usage['checked_at']) ? CarbonImmutable::parse((string) $usage['checked_at']) : null;
        if ($checked === null || $checked->addHours(UsageWatch::FRESH_HOURS)->isPast()) {
            return null;
        }
        // a first reading is only a baseline, and a soft limit only ever tells the customer (TASK-0023, owner decision 9);
        // a measurement written before metering has no baseline list and counts as confirmed
        $baseline = array_map('strval', (array) ($usage['baseline'] ?? []));
        foreach (self::STORAGE_METRICS as $key) {
            if (in_array($key, $baseline, true) || UsageMetrics::isSoft($key) || ($key === WebDiskTotal::METRIC && ! WebDiskTotal::enforcedFor($service))) {
                continue;
            }
            $metric = (array) data_get($usage, "metrics.{$key}", []);
            if ((int) ($metric['pct'] ?? 0) >= UsageWatch::FULL_PCT && (int) ($metric['limit'] ?? 0) > 0) {
                return ['key' => $key, 'used' => (int) $metric['used'], 'limit' => (int) $metric['limit'], 'pct' => (int) $metric['pct']];
            }
        }

        return null;
    }

    /** Refuses an action that would store more on a service that has no room left, and says how to get some. */
    public static function assertRoomFor(Service $service, string $action): void
    {
        if (! in_array($action, self::GROWING, true) || ! in_array($service->family, ['web', 'managed'], true)) {
            return;
        }
        // an included site has no plan space of its own: its owner's enforced total is the plan's
        $full = self::full($service) ?? (IncludedServices::isIncluded($service) ? WebDiskTotal::fullFor($service) : null);
        if ($full === null) {
            return;
        }
        $what = match ($full['key']) {
            'inodes' => 'počet souborů webu je na '.$full['pct'].' % tarifu ('.number_format($full['used'], 0, ',', ' ').' z '.number_format($full['limit'], 0, ',', ' ').')',
            WebDiskTotal::METRIC => 'místo tarifu (soubory + databáze + pošta) je zaplněno na '.$full['pct'].' % ('.self::gb($full['used']).' z '.self::gb($full['limit']).')',
            default => 'web má zaplněno '.$full['pct'].' % prostoru tarifu ('.self::gb($full['used']).' z '.self::gb($full['limit']).')',
        };

        throw new DomainError('service_storage_full', ucfirst($what).'. Uvolněte místo (smazání souborů, starých záloh nebo nepoužívané databáze), nebo zvyšte tarif. Do té doby nejde přidávat další obsah.', 409, [
            'metric' => $full['key'], 'used' => $full['used'], 'limit' => $full['limit'], 'pct' => $full['pct'], 'action' => $action,
        ]);
    }

    private static function gb(int $bytes): string
    {
        return $bytes >= 1024 ** 3 ? rtrim(rtrim(number_format($bytes / 1024 ** 3, 1, ',', ' '), '0'), ',').' GB' : max(1, (int) round($bytes / 1024 ** 2)).' MB';
    }
}
