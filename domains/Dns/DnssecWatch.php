<?php

declare(strict_types=1);

namespace Onhost\Domain\Dns;

use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Incidents\OnCallService;
use Onhost\Platform\Dns\ParentDsResolver;
use Throwable;

/**
 * A signed zone only works while the registry holds the DS record of the key that signs it.
 *
 * Enabling DNSSEC at our nameserver is half of the job; the other half (the DS at the registry) is a step the customer or
 * the registrar does by hand, and the part that fails in silence: a DS that was never published means the signing is
 * decoration, and a DS for a key that has since been replaced makes every validating resolver answer SERVFAIL — the whole
 * domain, site and mail, vanishes for a large part of the internet while the zone itself looks perfectly healthy.
 *
 * So once a night, for every zone with DNSSEC on, the DS the parent publishes is compared with the DS derived from the
 * key our nameserver signs with. The parent's answer comes from a public validating resolver (`ParentDsResolver`; the
 * registrar adapters do not expose the DS they sent). The result is one of
 *
 *   ok        the parent holds a DS that matches one of our keys
 *   missing   the parent holds no DS at all
 *   mismatch  the parent holds DS records, none of them ours
 *   unknown   the question could not be answered (no DS of ours on record, resolver not answering) — never `ok`
 *
 * `missing` and `mismatch` page the on-call (one alert per zone, deduplicated) and show in the health check; nothing is changed.
 */
final class DnssecWatch
{
    public const OK = 'ok';

    public const MISSING = 'missing';

    public const MISMATCH = 'mismatch';

    public const UNKNOWN = 'unknown';

    public const EVENT = 'dns.dnssec.broken';

    public function __construct(private readonly ParentDsResolver $parent, private readonly DnsService $zones, private readonly OnCallService $oncall) {}

    /** @return array{checked:int, broken:int, unknown:int, errors:int} */
    public function run(int $limit = 200): array
    {
        $stats = ['checked' => 0, 'broken' => 0, 'unknown' => 0, 'errors' => 0];
        $zones = DnsZone::query()->where('state', 'active')->where('kind', 'primary')->where('dnssec', true)->orderBy('id')->limit(max(1, $limit))->get();
        foreach ($zones as $zone) {
            try {
                $check = $this->check($zone);
            } catch (Throwable) {
                $stats['errors']++;

                continue;
            }
            $zone->forceFill(['dnssec_check' => $check])->save();
            $stats['checked']++;
            $dedup = OnCallService::dedupKey(self::EVENT, 'dns_zone', (string) $zone->id);
            if (in_array($check['state'], [self::MISSING, self::MISMATCH], true)) {
                $stats['broken']++;
                $this->oncall->open(self::EVENT, 'dns_zone', (string) $zone->id, 'DNSSEC zóny '.$zone->name.': '.self::describe($check['state']), self::detail($zone, $check), null, 'hot');
            } else {
                $stats['unknown'] += $check['state'] === self::UNKNOWN ? 1 : 0;
                if ($check['state'] === self::OK) {
                    $this->oncall->resolveByDedup($dedup, 'dnssec watch'); // fixed at the registry: the alert closes itself
                }
            }
        }

        return $stats;
    }

    /** @return array{state:string, checked_at:string, ours:list<string>, parent:list<string>, reason:?string} */
    public function check(DnsZone $zone): array
    {
        $ours = $this->ourDs($zone);
        $base = ['checked_at' => now()->toIso8601String(), 'ours' => $ours, 'parent' => [], 'reason' => null];
        if ($ours === []) {
            return ['state' => self::UNKNOWN, 'reason' => 'no DS derived from our signing key is on record'] + $base;
        }
        $parent = $this->parent->lookup($zone->name);
        if ($parent === null) {
            return ['state' => self::UNKNOWN, 'reason' => 'the parent DS could not be read'] + $base;
        }
        $base['parent'] = $parent;
        $state = match (true) {
            $parent === [] => self::MISSING,
            array_intersect($ours, $parent) !== [] => self::OK,
            default => self::MISMATCH,
        };

        return ['state' => $state] + $base;
    }

    /** The DS of our keys: the one stored when DNSSEC was switched on, else asked of the nameserver now. @return list<string> */
    private function ourDs(DnsZone $zone): array
    {
        $stored = (array) $zone->dnssec_ds;
        if ($stored === []) {
            try {
                $stored = (array) ($this->zones->adapter($zone)->dnssecStatus($zone->name)['ds'] ?? []);
            } catch (Throwable) {
                $stored = [];
            }
        }

        return array_values(array_unique(array_filter(array_map(fn ($ds) => ParentDsResolver::normalize((string) $ds), $stored))));
    }

    public static function describe(string $state): string
    {
        return match ($state) {
            self::MISSING => 'registr nemá žádný záznam DS',
            self::MISMATCH => 'DS u registru neodpovídá klíči, kterým zónu podepisujeme',
            self::OK => 'DS u registru odpovídá',
            default => 'DS se nepodařilo ověřit',
        };
    }

    /** @param array{ours:list<string>, parent:list<string>} $check */
    private static function detail(DnsZone $zone, array $check): string
    {
        return mb_substr('Validující resolvery mohou doménu '.$zone->name.' přestat překládat. Náš DS: '.implode('; ', array_slice($check['ours'], 0, 3)).' · u registru: '.($check['parent'] === [] ? 'nic' : implode('; ', array_slice($check['parent'], 0, 3))).'. Zveřejněte správný DS u registru, nebo DNSSEC vypněte.', 0, 500);
    }
}
