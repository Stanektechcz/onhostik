<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Carbon\CarbonImmutable;

/**
 * Which scheduled collectors should have reported by now. Each one records its run in the AutomationLedger; a collector
 * whose last success is older than twice its interval (or that never ran, or whose last run failed) is silent in a way no
 * other check would notice — the scheduler and the queue can be healthy while one command quietly died. Collectors staff
 * switched off (setting `automation.disabled`) are skipped.
 */
final class CollectorHeartbeat
{
    /** @var array<string,array{name:string, minutes:int, needs?:string}> ledger key => expected interval */
    public const EXPECTED = [
        'nodes.usage' => ['name' => 'node disk usage', 'minutes' => 15],
        'usage.watch' => ['name' => 'plan usage watch', 'minutes' => 60],
        'certificates.watch' => ['name' => 'certificate watch', 'minutes' => 1440],
        'dns.check' => ['name' => 'public DNS check', 'minutes' => 1440],
        'dns.drift' => ['name' => 'DNS drift check', 'minutes' => 1440],
        'sites.headers' => ['name' => 'website security headers', 'minutes' => 1440],
        'sites.origin' => ['name' => 'CDN origin exposure', 'minutes' => 1440],
        'dns.dnssec' => ['name' => 'DNSSEC DS at the registry', 'minutes' => 1440],
        'sites.integrity' => ['name' => 'site integrity', 'minutes' => 1440],
        'mail.blocklist' => ['name' => 'mail blocklists', 'minutes' => 1440],
        'metering.rollup' => ['name' => 'usage rollup', 'minutes' => 1440],
        'platform.backup' => ['name' => 'platform backup', 'minutes' => 1440],
        'platform.backup.verify' => ['name' => 'platform backup verify', 'minutes' => 1440, 'needs' => 'platform.backup'],
        'audit.verify' => ['name' => 'audit chain verify', 'minutes' => 1440],
        'digest.staff' => ['name' => 'staff daily digest', 'minutes' => 1440],
        'digest.weekly' => ['name' => 'customer weekly digest', 'minutes' => 10080],
    ];

    public const GRACE_FACTOR = 2;

    public function __construct(private readonly AutomationLedger $ledger) {}

    /** @return list<array{area:string, check:string, ok:bool, detail:string, blocking:bool}> */
    public function checks(): array
    {
        $rows = [];
        foreach (self::EXPECTED as $key => $collector) {
            if (! $this->ledger->enabled($key) || (isset($collector['needs']) && ! $this->ledger->enabled($collector['needs']))) {
                continue;
            }
            $rows[] = $this->row($key, $collector);
        }

        return $rows;
    }

    /**
     * @param  array{name:string, minutes:int}  $collector
     * @return array{area:string, check:string, ok:bool, detail:string, blocking:bool}
     */
    private function row(string $key, array $collector): array
    {
        $check = 'collector '.$collector['name'].' ('.$key.')';
        $last = $this->ledger->last($key);
        if ($last === null || empty($last['at'])) {
            return ['area' => 'automation', 'check' => $check, 'ok' => false, 'detail' => 'never ran (expected every '.$collector['minutes'].' min)', 'blocking' => false];
        }
        if (($last['error'] ?? null) !== null) {
            return ['area' => 'automation', 'check' => $check, 'ok' => false, 'detail' => 'last run failed: '.mb_substr((string) $last['error'], 0, 120), 'blocking' => false];
        }
        $at = CarbonImmutable::parse((string) $last['at']);
        $limit = $collector['minutes'] * self::GRACE_FACTOR;
        $age = (int) $at->diffInMinutes(now(), true);

        return [
            'area' => 'automation', 'check' => $check, 'ok' => $age <= $limit,
            'detail' => 'last success '.$at->diffForHumans().' (expected every '.$collector['minutes'].' min, warns after '.$limit.')', 'blocking' => false,
        ];
    }
}
