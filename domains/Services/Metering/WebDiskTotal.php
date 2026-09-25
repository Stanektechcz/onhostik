<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Metering;

use Carbon\CarbonImmutable;
use Onhost\Domain\Services\IncludedServices;
use Onhost\Domain\Services\Mail\MailDomains;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\StagingLink;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\UsageWatch;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\DatabaseSizeCapable;
use Onhost\Providers\Contracts\MailToolsProvider;
use Throwable;

/**
 * The plan's space as it is sold: "files, databases and mail together" (TASK-0023 web-disk-total, owner decision 10).
 * Only the site's files were ever measured; the platform now adds the three up for the paying service and the further
 * sites its plan carries. A test copy is shown apart and not counted. A part the panel could not say is null with a
 * reason — never 0 — and the total is then a lower bound (`partial`).
 *
 * The total is shown at once, for everybody. It counts against the plan only from the date the operator sets
 * (`onhost.metering.web_disk_total.enforce_from`, null = never), and only for a service that was told in time
 * (`tags.usage_notices.disk_total`, at least `notice_min_days` before that date) or was ordered on or after it. Panel
 * quotas are never rewritten here: this class reads, the watch stores, `UsageGuard` and `PlanFit` act.
 */
final class WebDiskTotal
{
    public const METRIC = 'disk_total';

    public const MEASURED = 'measured';

    public const PARTIAL = 'partial';

    public const UNAVAILABLE = 'unavailable';

    /** The parts of the total, as sample metrics. */
    public const COMPONENTS = ['files' => 'disk_files', 'databases' => 'disk_databases', 'mail' => 'disk_mail'];

    /** An included site in one of these states still holds data on the node. */
    private const HOLDING = [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED, ServiceStateMachine::SUSPENDED];

    public function __construct(private readonly ServiceFeatures $features) {}

    /**
     * Reads the total of the plan `$service` belongs to from the panels (no write).
     *
     * @return array{files:int|null, databases:int|null, mail:int|null, total:int|null, limit:int|null, pct:int|null, quality:string, unavailable:array<string,string>, parts:list<array<string,mixed>>, staging:list<array<string,mixed>>, checked_at:string}
     */
    public function measure(Service $service): array
    {
        $owner = self::ownerOf($service);
        $staging = array_map('strval', StagingLink::query()->where('organization_id', $owner->organization_id)->pluck('staging_service_id')->all());
        $sites = collect([$owner])->concat(IncludedServices::of($owner)->filter(fn (Service $site) => in_array($site->state, self::HOLDING, true))->values());
        $sums = array_fill_keys(array_keys(self::COMPONENTS), ['value' => 0, 'complete' => true, 'reason' => null]);
        $parts = $copies = [];
        $known = false;
        foreach ($sites as $site) {
            $files = $this->files($site);
            if (in_array((string) $site->id, $staging, true)) { // a test copy is shown, never counted
                $copies[] = ['service_id' => $site->id, 'hostname' => $site->hostname, 'files' => $files['complete'] ? $files['value'] : null];

                continue;
            }
            $read = ['files' => $files, 'databases' => $this->databases($site), 'mail' => $this->mail($site)];
            $part = ['service_id' => $site->id, 'hostname' => $site->hostname];
            foreach ($read as $component => $one) {
                $part[$component] = $one['complete'] ? $one['value'] : null;
                $known = $known || $one['value'] !== null;
                $sums[$component]['value'] += (int) $one['value'];
                $sums[$component]['complete'] = $sums[$component]['complete'] && $one['complete'];
                $sums[$component] = self::firstReason($sums[$component], $one);
            }
            $parts[] = $part;
        }
        $total = $known ? (int) array_sum(array_column($sums, 'value')) : null;
        $limit = self::limitBytes($owner);
        $unavailable = array_filter(array_map(fn (array $sum) => $sum['complete'] ? null : (string) $sum['reason'], $sums));

        return [
            'files' => $sums['files']['complete'] ? $sums['files']['value'] : null,
            'databases' => $sums['databases']['complete'] ? $sums['databases']['value'] : null,
            'mail' => $sums['mail']['complete'] ? $sums['mail']['value'] : null,
            'total' => $total, 'limit' => $limit, 'pct' => $total !== null && $limit !== null ? (int) min(999, round($total / $limit * 100)) : null,
            'quality' => $total === null ? self::UNAVAILABLE : ($unavailable === [] ? self::MEASURED : self::PARTIAL),
            'unavailable' => $unavailable, 'parts' => $parts, 'staging' => $copies, 'checked_at' => now()->toIso8601String(),
        ];
    }

    /** The paying service a plan's space belongs to: an included site's owner (same organization only), else the service itself. */
    public static function ownerOf(Service $service): Service
    {
        if (! IncludedServices::isIncluded($service)) {
            return $service;
        }

        return Service::query()->where('organization_id', $service->organization_id)->whereKey((string) data_get($service->tags, 'parent_service_id'))->first() ?? $service;
    }

    /** The total the watch last stored for the plan (no panel call). @return array<string,mixed>|null */
    public static function held(Service $service): ?array
    {
        $held = data_get(self::ownerOf($service)->tags, 'usage.disk_total');

        return is_array($held) ? $held : null;
    }

    /**
     * What a reader of `$service` is shown of the plan total. On the paying service: all of it. On an included site —
     * which can be shared on its own (`svc_view`) — the plan's figures and that site's own row only: never the hostnames
     * and sizes of the sibling sites or the test copies (review round 1, sharing boundary).
     *
     * @return array<string,mixed>|null
     */
    public static function shownFor(Service $service): ?array
    {
        $held = self::held($service);
        if ($held === null || (string) self::ownerOf($service)->id === (string) $service->id) {
            return $held;
        }
        $own = array_values(array_filter((array) ($held['parts'] ?? []), fn (mixed $part) => is_array($part) && (string) ($part['service_id'] ?? '') === (string) $service->id));

        return array_replace($held, ['parts' => $own, 'staging' => []]);
    }

    /** What the plan sells as space: the plan's GB plus a mail add-on's quota. The one place a paid limit raise must extend. */
    public static function limitBytes(Service $owner): ?int
    {
        $entitlements = (array) ($owner->entitlements ?? []);
        $gb = (int) ($entitlements['nvme_gb'] ?? 0);
        if ($gb <= 0) {
            return null;
        }

        return $gb * 1024 ** 3 + max(0, (int) ($entitlements['quota_mb'] ?? 0)) * 1024 ** 2;
    }

    /**
     * The configured date from which the total counts (null = never, also for a value that is not a date). A date alone
     * is not enough: the operator must also confirm (`parts_verified`) that databases and mail lie outside the files
     * quota and are read as the release steps say — else the total could count a part twice and refuse, or upgrade
     * from credit, a customer who is within the plan.
     */
    public static function enforceFrom(): ?CarbonImmutable
    {
        $value = trim((string) config('onhost.metering.web_disk_total.enforce_from', ''));
        if ($value === '' || config('onhost.metering.web_disk_total.parts_verified', false) !== true) {
            return null;
        }
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * From when the total counts for this service (Y-m-d): the configured date, when the service was ordered on or after
     * it, or was told of that very date at least `notice_min_days` before it. Null: it does not count (files only).
     */
    public static function enforcementDate(Service $service): ?string
    {
        $from = self::enforceFrom();
        $owner = self::ownerOf($service);
        if ($from === null || ! in_array($owner->family, ['web', 'managed'], true)) {
            return null;
        }
        if ($owner->created_at !== null && CarbonImmutable::instance($owner->created_at)->greaterThanOrEqualTo($from)) {
            return $from->toDateString();
        }
        $notice = (array) data_get($owner->tags, 'usage_notices.'.self::METRIC, []);
        $sent = self::date($notice['sent_at'] ?? null);
        $inTime = $sent !== null && $sent->lessThanOrEqualTo($from->subDays(self::noticeDays()));

        return $inTime && (string) ($notice['effective'] ?? '') === $from->toDateString() ? $from->toDateString() : null;
    }

    /** Whether the total counts against the plan for this service today. */
    public static function enforcedFor(Service $service): bool
    {
        $date = self::enforcementDate($service);

        return $date !== null && ! now()->startOfDay()->lessThan(CarbonImmutable::createFromFormat('!Y-m-d', $date));
    }

    public static function noticeDays(): int
    {
        return max(1, (int) config('onhost.metering.web_disk_total.notice_min_days', 30));
    }

    /** Full: the total is over the plan. A partial total is a lower bound — over the limit is proof, under it is not. @param  array<string,mixed>  $held */
    public static function isFull(array $held): bool
    {
        return (int) ($held['limit'] ?? 0) > 0 && is_numeric($held['total'] ?? null) && (int) $held['total'] >= (int) $held['limit']
            && in_array($held['quality'] ?? null, [self::MEASURED, self::PARTIAL], true);
    }

    /**
     * The watch metric for an enforced total: a fully measured total, or a partial one that is already over the plan.
     *
     * @param  array<string,mixed>  $total
     * @return array{used:int, limit:int, pct:int}|null
     */
    public static function metricOf(array $total): ?array
    {
        $limit = (int) ($total['limit'] ?? 0);
        if ($limit <= 0 || ! is_numeric($total['total'] ?? null) || (($total['quality'] ?? null) !== self::MEASURED && ! self::isFull($total))) {
            return null;
        }

        return ['used' => (int) $total['total'], 'limit' => $limit, 'pct' => (int) min(999, round((int) $total['total'] / $limit * 100))];
    }

    /**
     * A fresh, enforced total of the plan that is full — what `UsageGuard` refuses an included site's growth on.
     *
     * @return array{key:string, used:int, limit:int, pct:int}|null
     */
    public static function fullFor(Service $service): ?array
    {
        if (! self::enforcedFor($service)) {
            return null;
        }
        $held = self::held($service);
        $checked = self::date($held['checked_at'] ?? null);
        if ($held === null || $checked === null || $checked->addHours(UsageWatch::FRESH_HOURS)->isPast() || ! self::isFull($held)) {
            return null;
        }

        return ['key' => self::METRIC, 'used' => (int) $held['total'], 'limit' => (int) $held['limit'], 'pct' => (int) ($held['pct'] ?? 0)];
    }

    /**
     * The total and its parts as samples: a number the panel could not give stays null; the total limits only once enforced.
     *
     * @param  array<string,mixed>  $total
     * @return list<UsageReading>
     */
    public static function readings(array $total, bool $enforced): array
    {
        $quality = (string) ($total['quality'] ?? self::UNAVAILABLE);
        $value = is_numeric($total['total'] ?? null) ? (int) $total['total'] : null;
        $out = [new UsageReading(self::METRIC, $value, is_numeric($total['limit'] ?? null) ? (int) $total['limit'] : null, 'bytes', $enforced ? MetricRegistry::HARD : MetricRegistry::NONE,
            $value === null ? UsageReading::UNAVAILABLE : ($quality === self::MEASURED ? UsageReading::MEASURED : UsageReading::ESTIMATED),
            $value === null ? (string) ($total['reason'] ?? 'not_reported') : ($quality === self::MEASURED ? null : self::PARTIAL), self::METRIC)];
        foreach (self::COMPONENTS as $component => $metric) {
            $part = $total[$component] ?? null;
            $out[] = is_numeric($part)
                ? new UsageReading($metric, (int) $part, null, 'bytes', MetricRegistry::NONE, UsageReading::MEASURED, null, self::METRIC)
                : new UsageReading($metric, null, null, 'bytes', MetricRegistry::NONE, UsageReading::UNAVAILABLE, (string) (data_get($total, "unavailable.{$component}") ?? $total['reason'] ?? 'not_reported'), self::METRIC);
        }

        return $out;
    }

    /** What the quota listing shows next to the files: the held total and from when it counts. @return array{total: array<string,mixed>|null, total_enforced_from: string|null} */
    public static function display(Service $service): array
    {
        return ['total' => self::shownFor($service), 'total_enforced_from' => self::enforcementDate($service)];
    }

    /**
     * The mail template's variables for `service.disk_total.announced`.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,string>
     */
    public static function noticeVars(array $payload): array
    {
        $effective = self::date($payload['effective'] ?? null);

        return [
            'sluzba' => (string) (($payload['label'] ?? '') ?: ($payload['hostname'] ?? '')),
            'datum' => $effective?->format('j. n. Y') ?? '',
            'soubory' => self::size($payload['files'] ?? null), 'databaze' => self::size($payload['databases'] ?? null), 'posta' => self::size($payload['mail'] ?? null),
            'celkem' => self::size($payload['total'] ?? null).(($payload['quality'] ?? null) === self::PARTIAL ? ' (alespoň; část zatím neměříme)' : ''),
            'limit' => self::size($payload['limit'] ?? null),
            'stav' => ! empty($payload['over'])
                ? 'Služba dnes zabírá víc, než tarif nabízí. Do data účinnosti uvolněte místo (staré zálohy, logy, nepoužívané databáze nebo schránky), nebo přejděte na vyšší tarif — jinak po tomto datu nepůjde přidávat další obsah.'
                : 'Služba se dnes do tarifu vejde; nic nemusíte dělat.',
        ];
    }

    /** Bytes for people: "12,5 GB", and "nezměřeno" for a number nobody has — never "0". */
    public static function size(mixed $bytes): string
    {
        if (! is_numeric($bytes)) {
            return 'nezměřeno';
        }
        $bytes = (int) $bytes;

        return $bytes >= 1024 ** 3 ? rtrim(rtrim(number_format($bytes / 1024 ** 3, 1, ',', ' '), '0'), ',').' GB' : ($bytes === 0 ? '0 MB' : max(1, (int) round($bytes / 1024 ** 2)).' MB');
    }

    /** @return array{value:int|null, complete:bool, reason:string|null} */
    private function files(Service $site): array
    {
        try {
            $used = $this->features->resources($site, 'quotas', false, [])['disk_used_bytes'] ?? null;
        } catch (Throwable $e) {
            return ['value' => null, 'complete' => false, 'reason' => self::errorCode($e)];
        }

        return is_numeric($used) ? ['value' => max(0, (int) $used), 'complete' => true, 'reason' => null] : ['value' => null, 'complete' => false, 'reason' => 'not_reported'];
    }

    /** @return array{value:int|null, complete:bool, reason:string|null} */
    private function databases(Service $site): array
    {
        try {
            $adapter = $this->features->adapterFor($site);
            if (! $adapter instanceof DatabaseSizeCapable) {
                return ['value' => null, 'complete' => false, 'reason' => 'panel_reports_no_database_size'];
            }
            // off until the operator verified the read on a test panel: its faults would count against the panel's breaker every hour
            if (config('onhost.metering.web_disk_total.database_sizes', false) !== true) {
                return ['value' => null, 'complete' => false, 'reason' => 'database_size_read_off'];
            }
            $rows = $adapter->databaseSizes($this->features->refFor($site));
        } catch (Throwable $e) {
            return ['value' => null, 'complete' => false, 'reason' => self::errorCode($e)];
        }
        $known = array_values(array_filter($rows, fn (array $row) => is_numeric($row['used_bytes'] ?? null)));
        $sum = (int) array_sum(array_map(fn (array $row) => max(0, (int) $row['used_bytes']), $known));

        return count($known) === count($rows)
            ? ['value' => $sum, 'complete' => true, 'reason' => null]
            : ['value' => $known === [] ? null : $sum, 'complete' => false, 'reason' => 'not_measured_yet'];
    }

    /** @return array{value:int|null, complete:bool, reason:string|null} */
    private function mail(Service $site): array
    {
        $refs = MailDomains::refsOf($site);
        if ($refs === []) { // no mail domain: nothing stored
            return ['value' => 0, 'complete' => true, 'reason' => null];
        }
        try {
            $adapter = $this->features->adapterFor($site);
            if (! $adapter instanceof MailToolsProvider) {
                return ['value' => null, 'complete' => false, 'reason' => 'unsupported'];
            }
            $sum = 0;
            foreach ($refs as $ref) { // every mail domain of the service, not only the first
                foreach ($adapter->mailboxUsage($ref) as $box) {
                    $sum += is_numeric($box['used_bytes'] ?? null) ? max(0, (int) $box['used_bytes']) : 0;
                }
            }
        } catch (Throwable $e) {
            return ['value' => null, 'complete' => false, 'reason' => self::errorCode($e)];
        }

        return ['value' => $sum, 'complete' => true, 'reason' => null];
    }

    /**
     * A component keeps the reason of its first part that could not be read.
     *
     * @param  array{value:int, complete:bool, reason:string|null}  $sum
     * @param  array{value:int|null, complete:bool, reason:string|null}  $one
     * @return array{value:int, complete:bool, reason:string|null}
     */
    private static function firstReason(array $sum, array $one): array
    {
        if ($sum['reason'] === null && ! $one['complete']) {
            $sum['reason'] = $one['reason'] ?? 'not_reported';
        }

        return $sum;
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private static function errorCode(Throwable $e): string
    {
        return match (true) {
            $e instanceof DomainError => $e->error,
            $e instanceof ProviderException => 'provider_'.strtolower($e->errorCode->value),
            default => 'error',
        };
    }
}
