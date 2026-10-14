<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments;

use Onhost\Domain\Invoicing\AccountingClock;

/**
 * TASK-0235 review: a gateway the daily reconciliation failed on was only a log line, so nobody saw that its settlements went
 * unchecked. The doctor row names every payment gateway that holds its credentials and whose last due day has no run. WARN only.
 *
 * A gateway without credentials is not configured (staging has no gateway account): it is not counted, and with none counted the
 * row is OK. The bank is not a gateway here — it needs no credentials and its run is the scheduled job's own (the job's age
 * budget covers it). Before 06:00 in Prague the scheduled run (05:15) may not have reconciled yesterday yet, so the day due then
 * is the day before yesterday.
 */
final class ReconciliationHealth
{
    public const CHECK = 'every payment gateway with credentials was reconciled yesterday';

    /** An hour after the scheduled run (05:15 Europe/Prague) yesterday is due. */
    private const DUE_FROM_HOUR = 6;

    public function __construct(
        private readonly SettlementReconciliation $reconciliation,
        private readonly GatewayCredentials $credentials,
    ) {}

    /** @return list<array{area:string, check:string, ok:bool, detail:string, blocking:bool, remedy:string}> */
    public function checks(): array
    {
        $now = AccountingClock::now();
        $day = $now->subDays($now->hour < self::DUE_FROM_HOUR ? 2 : 1)->toDateString();
        $gateways = array_values(array_filter($this->reconciliation->configuredProviders(), fn (string $key) => $key !== 'bank' && $this->credentials->missing($key) === null));
        if ($gateways === []) {
            return [$this->row(true, 'no payment gateway holds credentials; nothing to reconcile (the bank is reconciled every night)')];
        }
        $behind = array_values(array_filter($gateways, fn (string $key) => ! SettlementReconciliation::hasRun($key, $day)));
        if ($behind === []) {
            return [$this->row(true, implode(', ', $gateways)." reconciled for {$day}")];
        }

        return [$this->row(false, implode(', ', $behind)." has no reconciliation for {$day}",
            'run `php artisan onhost:payments:reconcile --provider=<gateway>` and read the log line `payments reconcile: provider failed`; the next scheduled run catches up missed days')];
    }

    private function row(bool $ok, string $detail, string $remedy = ''): array
    {
        return ['area' => 'payments', 'check' => self::CHECK, 'ok' => $ok, 'detail' => $detail, 'blocking' => false, 'remedy' => $remedy];
    }
}
