<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * A 24-hour clock sweep (TASK-0047). Billing code ran green all day and failed at night: the suite only ever looked at
 * the one instant it happened to start at. `clockSweep()` runs a scenario at every step of a day, each run on a clean
 * slate (its own savepoint, rolled back afterwards, with after-commit callbacks still firing as in a normal test), and
 * returns the instants at which it failed. `clockSweepWindows()` folds those instants into readable windows for a
 * failure message.
 *
 * Required by the test files that use it (not autoloaded): `require_once __DIR__.'/../../Support/ClockSweep.php';`.
 * Every function name starts with `clockSweep` — Pest files share one global function namespace.
 */

/**
 * Runs `$scenario` at every `$stepMinutes` of the local day `$day` in `$tz` (midnight to midnight) and returns the
 * failing instants as `local label => first line of the failure (test file:line)`. The scenario receives the instant (in
 * `$tz`); the application clock (`now()`, `Date`, `Carbon`, `CarbonImmutable`) is frozen there when it starts, and it may
 * travel. The day defaults to tomorrow: what the test seeded before the sweep (catalogue prices valid from the real now)
 * must already be valid, so a sweep never goes back in time — for another season `clockSweepNextDay('01-15')`.
 *
 * @param  callable(CarbonImmutable): void  $scenario
 * @return array<string, string>
 */
function clockSweep(callable $scenario, int $stepMinutes = 15, string $tz = 'UTC', ?string $day = null): array
{
    if ($stepMinutes < 1 || 1440 % $stepMinutes !== 0) {
        throw new InvalidArgumentException("a sweep step must divide the day evenly, {$stepMinutes} minutes does not");
    }
    $midnight = CarbonImmutable::parse($day ?? clockSweepNextDay(), $tz)->startOfDay();
    $failures = [];
    for ($minute = 0; $minute < 1440; $minute += $stepMinutes) {
        $at = $midnight->addMinutes($minute);
        $error = clockSweepRunAt($scenario, $at);
        if ($error !== null) {
            $failures[clockSweepLabel($at)] = $error;
        }
    }

    return $failures;
}

/** The next `$monthDay` (m-d) after today, or tomorrow when none is given (Y-m-d, in `$tz`). */
function clockSweepNextDay(?string $monthDay = null, string $tz = 'UTC'): string
{
    $today = CarbonImmutable::now($tz)->startOfDay();
    if ($monthDay === null) {
        return $today->addDay()->toDateString();
    }
    $next = CarbonImmutable::parse($today->year.'-'.$monthDay, $tz);

    return ($next->greaterThan($today) ? $next : $next->addYear())->toDateString();
}

/** Runs `$scenario` at each of the given instants (e.g. the edges of a window), same contract as clockSweep(). */
function clockSweepAt(callable $scenario, iterable $instants, string $tz = 'UTC'): array
{
    $failures = [];
    foreach ($instants as $instant) {
        $at = CarbonImmutable::parse($instant, $tz);
        $error = clockSweepRunAt($scenario, $at);
        if ($error !== null) {
            $failures[clockSweepLabel($at)] = $error;
        }
    }

    return $failures;
}

/** "2026-09-28 01:15 UTC" / "2026-09-28 01:15 Europe/Prague (23:15 UTC)". */
function clockSweepLabel(CarbonImmutable $at): string
{
    $local = $at->format('Y-m-d H:i').' '.$at->getTimezone()->getName();

    return $at->getTimezone()->getName() === 'UTC' ? $local : $local.' ('.$at->utc()->format('H:i').' UTC)';
}

/**
 * Folds the failures of one sweep into windows for an assertion message:
 * "2026-09-28 22:00 UTC … 23:45 UTC ×8: <error>" (consecutive steps with the same error form one window).
 *
 * @param  array<string, string>  $failures  as returned by clockSweep()
 */
function clockSweepWindows(array $failures, int $stepMinutes = 15): string
{
    if ($failures === []) {
        return 'no failing instant';
    }
    $windows = [];
    $current = null;
    foreach ($failures as $label => $error) {
        $local = CarbonImmutable::parse(substr($label, 0, 16), 'UTC'); // the local wall time, only compared with its neighbours
        if ($current !== null && $current['last']->addMinutes($stepMinutes)->equalTo($local) && $current['error'] === $error) {
            $current = ['last' => $local, 'last_label' => $label, 'count' => $current['count'] + 1] + $current;

            continue;
        }
        if ($current !== null) {
            $windows[] = $current;
        }
        $current = ['first_label' => $label, 'last' => $local, 'last_label' => $label, 'count' => 1, 'error' => $error];
    }
    $windows[] = $current;

    return implode("\n", array_map(fn (array $w) => $w['first_label'].($w['count'] > 1 ? ' … '.substr($w['last_label'], 11) : '').' ×'.$w['count'].': '.$w['error'], $windows));
}

/** One run on a clean slate; null when it passed, the first line of the failure otherwise. */
function clockSweepRunAt(callable $scenario, CarbonImmutable $at): ?string
{
    $connection = DB::connection();
    $restore = clockSweepSavepointManager($connection);
    Carbon::setTestNow($at);
    CarbonImmutable::setTestNow($at);
    Cache::flush(); // idempotency answers, locks and rate limits of the previous run must not answer this one
    $connection->beginTransaction();
    try {
        $scenario($at);

        return null;
    } catch (Throwable $e) {
        return class_basename($e).': '.strtok(trim($e->getMessage()), "\n").clockSweepWhere($e);
    } finally {
        while ($connection->transactionLevel() > 1) {
            $connection->rollBack();
        }
        $restore();
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }
}

/**
 * RefreshDatabase's transaction manager runs after-commit callbacks (outbox relay, queued mail) when a transaction
 * commits back to level 1 — its own test transaction. Inside the sweep's savepoint they would wait for a commit that
 * never comes, and the scenario would not behave as it does in a normal test. For the length of one run the baseline
 * is the savepoint (level 2); the original manager comes back afterwards.
 *
 * @return Closure(): void restores the original manager
 */
function clockSweepSavepointManager(Connection $connection): Closure
{
    $original = app()->bound('db.transactions') ? app('db.transactions') : null;
    $transacting = $original instanceof DatabaseTransactionsManager
        ? (fn () => $this->connectionsTransacting)->call($original)
        : [$connection->getName()];
    $manager = new class($transacting) extends DatabaseTransactionsManager
    {
        public function afterCommitCallbacksShouldBeExecuted($level)
        {
            return $level === 2;
        }
    };
    app()->instance('db.transactions', $manager);
    $connection->setTransactionManager($manager);

    return function () use ($connection, $original): void {
        if ($original !== null) {
            app()->instance('db.transactions', $original);
            $connection->setTransactionManager($original);
        }
    };
}

/** " (WithdrawalTest.php:147)": the line of the test that failed, so a window names its assertion. */
function clockSweepWhere(Throwable $e): string
{
    foreach (array_merge([['file' => $e->getFile(), 'line' => $e->getLine()]], $e->getTrace()) as $frame) {
        $file = str_replace(DIRECTORY_SEPARATOR, '/', (string) ($frame['file'] ?? ''));
        if (str_contains($file, '/tests/Feature/') || str_contains($file, '/tests/Unit/') || str_contains($file, '/tests/Contract/')) {
            return ' ('.basename($file).':'.($frame['line'] ?? '?').')';
        }
    }

    return '';
}
