<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Onhost\Domain\Invoicing\AccountingClock;
use Onhost\Domain\Payments\Models\ReconciliationRun;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Throwable;

/**
 * TASK-0235: the daily settlement reconciliation (§64.6). `PaymentService::reconcile()` compares what a provider settled with the
 * intents and the ledger, settles an intent the provider settled but that is still open here, and opens a reconciliation item with
 * `finance.reconciliation.mismatch` for every difference — but nothing called it. This runs it per accounting day (a day at the
 * seller's seat, AccountingClock, half-open [00:00, next 00:00)) for each configured provider that holds its credentials, the bank
 * included.
 *
 * Once per provider and day: a day that has its run is left alone (the database refuses a second one), so a repeated or concurrent
 * run is a no-op. Catch-up: the scheduled run takes every day of the last (largest settlement lag + 1) days that has no run yet,
 * oldest first — a day the scheduler missed or a provider failed on is done the next night, so the missing-payout check of every
 * day still runs once. A provider without credentials is skipped with a warning; one that fails is logged with the exception
 * class only and the others go on (the run is written in one transaction, so a failed day leaves nothing behind). The caller fails
 * the job only when every provider it tried failed; the doctor names a gateway with credentials whose yesterday has no run
 * (ReconciliationHealth).
 */
final class SettlementReconciliation
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaymentProviderRegistry $providers,
        private readonly GatewayCredentials $credentials,
    ) {}

    /** Yesterday at the seller's seat. */
    public static function yesterday(): string
    {
        return AccountingClock::now()->subDay()->toDateString();
    }

    /**
     * The days the scheduled run looks at: yesterday and the (largest lag of the providers) days before it, oldest first.
     *
     * @param  list<string>  $providers
     * @return list<string>
     */
    public static function catchUpDays(array $providers): array
    {
        $lag = 0;
        foreach ($providers as $key) {
            $lag = max($lag, self::lag($key));
        }
        $yesterday = AccountingClock::now()->subDay()->startOfDay();
        $days = [];
        for ($back = $lag; $back >= 0; $back--) {
            $days[] = $yesterday->subDays($back)->toDateString();
        }

        return $days;
    }

    public static function lag(string $provider): int
    {
        return max(0, (int) config("onhost.payments.reconcile.settlement_lag_days.{$provider}", 0));
    }

    /** A day that is over at the seller's seat (Y-m-d); a day still running would be reconciled half and then never again. */
    public static function isReconcilableDay(string $day): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
            return false;
        }
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $day, AccountingClock::timezone());

        return $parsed !== null && $parsed->toDateString() === $day && $day < AccountingClock::date();
    }

    /**
     * The day's instants in the application's zone: its first instant and the first instant of the next day (half-open; a day with
     * a summer-time change has 23 or 25 hours).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function bounds(string $day): array
    {
        $local = CarbonImmutable::createFromFormat('!Y-m-d', $day, AccountingClock::timezone());
        $zone = date_default_timezone_get();

        return [$local->setTimezone($zone), $local->addDay()->setTimezone($zone)];
    }

    /** @return list<string> */
    public function configuredProviders(): array
    {
        $known = $this->providers->keys();
        $wanted = array_map('strval', (array) config('onhost.payments.reconcile.providers', []));

        return array_values(array_filter(array_unique($wanted), fn (string $key) => in_array($key, $known, true)));
    }

    public function isKnownProvider(string $key): bool
    {
        return in_array($key, $this->providers->keys(), true);
    }

    /** Whether the provider's day has its run. */
    public static function hasRun(string $provider, string $day): bool
    {
        return ReconciliationRun::query()->where('kind', 'payments')->where('provider', $provider)->where('period_start', self::bounds($day)[0])->exists();
    }

    /**
     * @param  list<string>  $providers
     * @return array{day:string, reconciled:list<string>, already:list<string>, skipped:list<string>, failed:list<string>, mismatches:int}
     */
    public function run(string $day, array $providers): array
    {
        if (! self::isReconcilableDay($day)) {
            throw new DomainError('reconcile_day_invalid', 'Only a past accounting day (YYYY-MM-DD) can be reconciled.', 422);
        }
        $summary = ['day' => $day, 'reconciled' => [], 'already' => [], 'skipped' => [], 'failed' => [], 'mismatches' => 0];
        $context = CommandContext::system('reconcile:payments');
        sort($providers);
        foreach ($providers as $key) {
            $missing = $this->credentials->missing($key);
            if ($missing !== null) {
                $summary['skipped'][] = $key;
                Log::warning('payments reconcile: provider skipped, no credentials', ['provider' => $key, 'day' => $day, 'error' => $missing]);

                continue;
            }
            [$bucket, $mismatches] = $this->reconcileOne($key, $day, $context);
            $summary[$bucket][] = $key;
            $summary['mismatches'] += $mismatches;
        }

        return $summary;
    }

    /** @return array{0:'reconciled'|'already'|'failed', 1:int} */
    private function reconcileOne(string $key, string $day, CommandContext $context): array
    {
        if (self::hasRun($key, $day)) {
            return ['already', 0];
        }
        [$start, $end] = self::bounds($day);
        try {
            $run = $this->payments->reconcile($key, $start, $end, $context);
        } catch (UniqueConstraintViolationException) {
            return ['already', 0]; // a concurrent run wrote the day first; this one's transaction left nothing behind
        } catch (Throwable $e) {
            Log::warning('payments reconcile: provider failed', ['provider' => $key, 'day' => $day, 'error' => get_class($e)]);

            return ['failed', 0];
        }

        return ['reconciled', (int) $run->mismatches];
    }
}
