<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Carbon\CarbonImmutable;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\CollectorHeartbeat;
use Throwable;

/**
 * How old a measurement may be before it stops being a statement about the present. A finding built from stored or
 * collected data carries `observed_at` and a `state`:
 *
 *   current          measured within the limit
 *   stale            measured, but longer ago than the limit
 *   unknown          never measured (or the time of the measurement was not kept)
 *   collection_error the collector's last run recorded an error
 *
 * Limits are twice the collector's interval (CollectorHeartbeat::GRACE_FACTOR), so a missed run is tolerated and two are not.
 * Anything but `current` can never be reported as `ok`: old or missing data is not good news.
 */
final class Freshness
{
    public const CURRENT = 'current';

    public const STALE = 'stale';

    public const UNKNOWN = 'unknown';

    public const COLLECTION_ERROR = 'collection_error';

    /** @var array<string,string> source => AutomationLedger key of the collector that feeds it */
    public const COLLECTORS = ['certificate' => 'certificates.watch', 'usage' => 'usage.watch', 'node_usage' => 'nodes.usage', 'security_headers' => 'sites.headers', 'origin' => 'sites.origin', 'dnssec' => 'dns.dnssec', 'mail_auth' => 'mail.auth', 'integrity' => 'sites.integrity', 'audit_chain' => 'audit.verify', 'vulns_feed' => 'vulns.feed', 'vulns' => 'vulns.match'];

    /** Minutes a measurement of this source stays current; null = the source has no collector (the caller passes its own limit). */
    public static function limitMinutes(string $source): ?int
    {
        $key = self::COLLECTORS[$source] ?? null;
        $collector = $key !== null ? CollectorHeartbeat::EXPECTED[$key] : null;

        return $collector !== null ? $collector['minutes'] * CollectorHeartbeat::GRACE_FACTOR : null;
    }

    /**
     * @param  string|\DateTimeInterface|null  $observedAt
     * @param  int|null  $limitMinutes  overrides the collector-derived limit
     * @return array{state:string, observed_at:?string, age_minutes:?int}
     */
    public static function assess(string $source, $observedAt, ?int $limitMinutes = null): array
    {
        $at = null;
        if ($observedAt instanceof \DateTimeInterface) {
            $at = CarbonImmutable::instance($observedAt);
        } elseif (is_string($observedAt) && $observedAt !== '') {
            try {
                $at = CarbonImmutable::parse($observedAt);
            } catch (Throwable) {
                $at = null;
            }
        }
        $key = self::COLLECTORS[$source] ?? null;
        if ($key !== null && (($last = app(AutomationLedger::class)->last($key)) !== null) && ($last['error'] ?? null) !== null) {
            return ['state' => self::COLLECTION_ERROR, 'observed_at' => $at?->toIso8601String(), 'age_minutes' => $at !== null ? (int) $at->diffInMinutes(now(), true) : null];
        }
        if ($at === null) {
            return ['state' => self::UNKNOWN, 'observed_at' => null, 'age_minutes' => null];
        }
        $age = (int) $at->diffInMinutes(now(), true);
        $limit = $limitMinutes ?? self::limitMinutes($source) ?? 2880;

        return ['state' => $age > $limit ? self::STALE : self::CURRENT, 'observed_at' => $at->toIso8601String(), 'age_minutes' => $age];
    }

    /**
     * Stamp a finding with its freshness; a non-current one cannot stay `ok`.
     *
     * @param  array{key:string, level:string, cs:string, en:string}  $finding
     * @param  array{state:string, observed_at:?string, age_minutes:?int}  $assessment
     * @return array{key:string, level:string, cs:string, en:string, observed_at:?string, state:string}
     */
    public static function stamp(array $finding, array $assessment): array
    {
        $state = $assessment['state'];
        $finding['observed_at'] = $assessment['observed_at'];
        $finding['state'] = $state;
        if ($state === self::CURRENT) {
            return $finding;
        }
        $when = $assessment['observed_at'] !== null ? CarbonImmutable::parse($assessment['observed_at'])->format('j. n. Y H:i') : null;
        if ($finding['level'] === 'ok') {
            $finding['level'] = 'warn';
            [$cs, $en] = match ($state) {
                self::UNKNOWN => ['Tohle jsme zatím neměřili, takže nevíme, jestli je to v pořádku.', 'This has not been measured yet, so we cannot tell whether it is fine.'],
                self::COLLECTION_ERROR => ['Poslední měření selhalo'.($when ? "; poslední známý údaj je z {$when}" : '').', takže nevíme, jestli je to v pořádku.', 'The last measurement failed'.($when ? "; the last known reading is from {$when}" : '').', so we cannot tell whether it is fine.'],
                default => ["Údaj je zastaralý (naposledy změřeno {$when}), takže nevíme, jestli je to v pořádku.", "The data is old (last observed {$when}), so we cannot tell whether it is fine."],
            };
            $finding['cs'] = $cs.' '.$finding['cs'];
            $finding['en'] = $en.' '.$finding['en'];
        } elseif ($when !== null) {
            $finding['cs'] .= " (naposledy změřeno {$when})";
            $finding['en'] .= " (last observed {$when})";
        }

        return $finding;
    }
}
