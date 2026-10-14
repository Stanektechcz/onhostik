<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Onhost\Domain\Invoicing\AccountingClock;
use Onhost\Domain\Payments\Models\ReconciliationRun;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Throwable;

/**
 * TASK-0235: the daily settlement reconciliation (§64.6). `PaymentService::reconcile()` compares what a provider settled with the
 * intents and the ledger, settles an intent the provider settled but that is still open here, and opens a reconciliation item with
 * `finance.reconciliation.mismatch` for every difference — but nothing called it. This runs it for one accounting day (a day at the
 * seller's seat, AccountingClock) for each configured provider that holds its credentials, the bank included.
 *
 * Once per provider and day: a day that already has its run is left alone, so a second run (or a manual one next to the scheduled
 * one) is a no-op. A provider without credentials is skipped with a warning; one that fails is logged with the exception class only
 * and the others go on. A failed provider writes no run (the provider is asked before anything is written), so its day can be run
 * again with `--date`. The caller fails the job only when every provider it tried failed.
 */
final class SettlementReconciliation
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaymentProviderRegistry $providers,
        private readonly GatewayCredentials $credentials,
    ) {}

    /** The accounting day reconciled when none is given: yesterday at the seller's seat. */
    public static function defaultDay(): string
    {
        return AccountingClock::now()->subDay()->toDateString();
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

    /**
     * @param  list<string>  $providers
     * @return array{day:string, reconciled:list<string>, already:list<string>, skipped:list<string>, failed:list<string>, mismatches:int}
     */
    public function run(string $day, array $providers): array
    {
        if (! self::isReconcilableDay($day)) {
            throw new DomainError('reconcile_day_invalid', 'Only a past accounting day (YYYY-MM-DD) can be reconciled.', 422);
        }
        $zone = AccountingClock::timezone();
        $local = CarbonImmutable::createFromFormat('!Y-m-d', $day, $zone);
        $start = $local->startOfDay()->setTimezone(date_default_timezone_get());
        $end = $local->endOfDay()->startOfSecond()->setTimezone(date_default_timezone_get());
        $summary = ['day' => $day, 'reconciled' => [], 'already' => [], 'skipped' => [], 'failed' => [], 'mismatches' => 0];
        $context = CommandContext::system('reconcile:payments');
        sort($providers);
        foreach ($providers as $key) {
            $missing = $this->credentials->missing($key);
            if ($missing !== null) {
                $summary['skipped'][] = $key;
                Log::warning('payments reconcile: provider skipped, no credentials', ['provider' => $key, 'error' => $missing]);

                continue;
            }
            $outcome = Cache::lock("onhost:payments:reconcile:{$key}:{$day}", 3600)->get(fn () => $this->reconcileOne($key, $day, $start, $end, $context));
            $bucket = $outcome === false ? 'already' : $outcome[0]; // the lock is held: another run is reconciling this day right now
            $summary[$bucket][] = $key;
            $summary['mismatches'] += $outcome === false ? 0 : $outcome[1];
        }

        return $summary;
    }

    /** @return array{0:'reconciled'|'already'|'failed', 1:int} */
    private function reconcileOne(string $key, string $day, CarbonImmutable $start, CarbonImmutable $end, CommandContext $context): array
    {
        if ($this->hasRun($key, $start)) {
            return ['already', 0];
        }
        try {
            $run = $this->payments->reconcile($key, $start, $end, $context);
        } catch (Throwable $e) {
            Log::warning('payments reconcile: provider failed', ['provider' => $key, 'day' => $day, 'error' => get_class($e)]);

            return ['failed', 0];
        }

        return ['reconciled', (int) $run->mismatches];
    }

    private function hasRun(string $key, CarbonImmutable $start): bool
    {
        return ReconciliationRun::query()->where('kind', 'payments')->where('period_start', $start)->where('summary->provider', $key)->exists();
    }
}
