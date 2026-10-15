<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Invoicing\AccountingClock;

/**
 * TASK-0235 review: a gateway the daily reconciliation failed on, or skipped for missing credentials, was only a log line, so
 * nobody saw that its settlements went unchecked. WARN only.
 *
 * A gateway counts when it is configured: it holds its credentials, or something names it — Comgate's merchant id or one of its
 * two credentials, GoPay's GoID, or in production the gateway payments are created with (`onhost.payments.default`). A configured
 * gateway without its credentials is named as such (the job skips it); one with credentials but no run for the day due is named
 * too. Staging has no gateway configured (no merchant id, no GoID, not production — `.env.example` and docs/runbooks/comgate.md),
 * so the row is OK there. The bank is not a gateway here: it needs no credentials and its run is the scheduled job's own.
 *
 * The day due is yesterday from 06:00 in Prague (the job runs at 05:15), the day before yesterday until then. Until the job has run
 * once (`scheduled_job_runs`, the deploy morning) no day is due yet.
 */
final class ReconciliationHealth
{
    public const CHECK = 'every configured payment gateway was reconciled yesterday';

    public const COMMAND = 'onhost:payments:reconcile';

    /** An hour after the scheduled run (05:15 Europe/Prague) yesterday is due. */
    private const DUE_FROM_HOUR = 6;

    public function __construct(
        private readonly SettlementReconciliation $reconciliation,
        private readonly GatewayCredentials $credentials,
        private readonly PaymentProviderRegistry $providers,
    ) {}

    /** @return list<array{area:string, check:string, ok:bool, detail:string, blocking:bool, remedy:string}> */
    public function checks(): array
    {
        $ready = $incomplete = [];
        foreach ($this->reconciliation->configuredProviders() as $key) {
            if ($key === 'bank') {
                continue;
            }
            if ($this->credentials->missing($key) === null) {
                $ready[] = $key;
            } elseif ($this->configured($key)) {
                $incomplete[] = $key;
            }
        }
        if ($ready === [] && $incomplete === []) {
            return [$this->row(true, 'no payment gateway is configured; nothing to reconcile (the bank is reconciled every night)')];
        }
        $problems = $incomplete === [] ? [] : [implode(', ', $incomplete).' is configured but its credentials are missing, so it is skipped'];
        $now = AccountingClock::now();
        $day = $now->subDays($now->hour < self::DUE_FROM_HOUR ? 2 : 1)->toDateString();
        $ranOnce = DB::table('scheduled_job_runs')->where('command', self::COMMAND)->whereNotNull('last_started_at')->exists();
        $behind = $ranOnce ? array_values(array_filter($ready, fn (string $key) => ! SettlementReconciliation::hasRun($key, $day))) : [];
        if ($behind !== []) {
            $problems[] = implode(', ', $behind)." has no reconciliation for {$day}";
        }
        if ($problems === []) {
            return [$this->row(true, $ranOnce ? implode(', ', $ready)." reconciled for {$day}" : implode(', ', $ready).': the job has not run since it was scheduled; the first run reconciles yesterday')];
        }

        return [$this->row(false, implode('; ', $problems),
            'missing credentials: put them in the store (docs/runbooks/comgate.md); a missing day: run `php artisan onhost:payments:reconcile --provider=<gateway>` and read the log line `payments reconcile: provider failed` — the next scheduled run catches up missed days')];
    }

    /** Something names the gateway although it may lack its credentials (never a value is read, only whether one is set). */
    private function configured(string $key): bool
    {
        if (app()->environment('production') && $key === (string) config('onhost.payments.default')) {
            return true;
        }

        return match ($key) {
            'comgate' => (string) config('onhost.payments.comgate.merchant', '') !== '' || $this->comgatePartly(),
            'gopay' => (string) config('onhost.payments.gopay.goid', '') !== '',
            default => false,
        };
    }

    private function comgatePartly(): bool
    {
        $provider = $this->providers->get('comgate');
        if (! method_exists($provider, 'credentialState')) {
            return false;
        }
        $state = (array) $provider->credentialState();

        return ! empty($state['merchant']) || ! empty($state['secret']);
    }

    private function row(bool $ok, string $detail, string $remedy = ''): array
    {
        return ['area' => 'payments', 'check' => self::CHECK, 'ok' => $ok, 'detail' => $detail, 'blocking' => false, 'remedy' => $remedy];
    }
}
