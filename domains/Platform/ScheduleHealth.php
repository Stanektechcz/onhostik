<?php

declare(strict_types=1);

namespace Onhost\Domain\Platform;

use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * P14 (phase Q): every scheduled job records its last start, success, failure and skip (`scheduled_job_runs`, written from the
 * scheduler's own events, so no line of routes/console.php has to remember a hook), and every job has an age budget derived from
 * its own schedule: twice the longest gap between two of its runs plus ten minutes of slack. The doctor row "every scheduled job
 * ran within its budget" names the jobs past it; `/metrics` exports `onhost_schedule_last_success_seconds{command}` and the budget
 * for the Prometheus alert (P12). Before this the doctor saw only that the scheduler ticked — a nightly job failing every night
 * (backup verify, FX rates, dunning) stayed invisible.
 *
 * A job that never ran is not overdue until its budget has passed since tracking began (the first row written), so a fresh
 * deploy is not a warning. A skip (a `when()` condition said no — e.g. the FIO bank sync without a token) counts as looked at.
 */
final class ScheduleHealth
{
    public const CHECK = 'every scheduled job ran within its budget';

    public const BUDGETS_CACHE_KEY = 'onhost:schedule:budgets';

    private const BUDGETS_REFRESH_SECONDS = 600;

    /** Two days: a scheduler that stopped longer than that is reported by the doctor row and OnhostSchedulerDown anyway. */
    private const BUDGETS_TTL_SECONDS = 172800;

    private const SLACK_SECONDS = 600;

    /** A fixed Monday: the budget is a property of the schedule, never of the hour the doctor runs. */
    private const REFERENCE = '2026-01-05 00:00:00';

    private const SAMPLES = 16;

    // ── recording (scheduler events) ──────────────────────────────────────────────────────────────────────────────────────

    public static function starting(ScheduledTaskStarting $event): void
    {
        self::write($event->task, ['last_started_at' => now()]);
        self::rememberBudgets();
    }

    /**
     * S3: the schedule lives in routes/console.php, which only the console kernel loads. PHP-FPM serving `/metrics` never
     * loads it, so its Schedule is empty and the metric said `command="none"` while jobs ran (staging, 2026-10-13). The
     * scheduler is the one process that always has the schedule: on its ticks it leaves the budgets in the cache, at most
     * every ten minutes, and a process without the schedule reads them from there.
     */
    private static function rememberBudgets(): void
    {
        try { // bookkeeping must never fail the job it describes
            $cached = Cache::get(self::BUDGETS_CACHE_KEY);
            if (is_array($cached) && now()->getTimestamp() - (int) ($cached['written_at'] ?? 0) < self::BUDGETS_REFRESH_SECONDS) {
                return;
            }
            $budgets = app(self::class)->budgets();
            if ($budgets !== []) {
                Cache::put(self::BUDGETS_CACHE_KEY, ['written_at' => now()->getTimestamp(), 'budgets' => $budgets], now()->addSeconds(self::BUDGETS_TTL_SECONDS));
            }
        } catch (Throwable $e) {
            Log::warning('schedule.health.budgets_not_cached', ['error' => mb_substr($e->getMessage(), 0, 200)]);
        }
    }

    public static function finished(ScheduledTaskFinished $event): void
    {
        $code = $event->task->exitCode;
        if ($code === null || $code === 0) {
            self::write($event->task, ['last_success_at' => now(), 'last_exit_code' => $code ?? 0, 'failures_in_a_row' => 0]);

            return;
        }
        self::fail($event->task, $code);
    }

    public static function failed(ScheduledTaskFailed $event): void
    {
        self::fail($event->task, $event->task->exitCode ?? 1);
    }

    public static function skipped(ScheduledTaskSkipped $event): void
    {
        self::write($event->task, ['last_skipped_at' => now()]);
    }

    private static function fail(Event $task, int $code): void
    {
        $key = self::key($task);
        $previous = (int) (DB::table('scheduled_job_runs')->where('command', $key)->value('failures_in_a_row') ?? 0);
        self::write($task, ['last_failure_at' => now(), 'last_exit_code' => $code, 'failures_in_a_row' => $previous + 1]);
    }

    /** @param  array<string, mixed>  $values */
    private static function write(Event $task, array $values): void
    {
        try { // bookkeeping must never fail the job it describes
            $key = self::key($task);
            $now = now();
            DB::table('scheduled_job_runs')->insertOrIgnore(['command' => $key, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('scheduled_job_runs')->where('command', $key)->update($values + ['updated_at' => $now]);
        } catch (Throwable $e) {
            Log::warning('schedule.health.write_failed', ['command' => mb_substr(self::key($task), 0, 191), 'error' => mb_substr($e->getMessage(), 0, 200)]);
        }
    }

    // ── what is scheduled, and its budget ─────────────────────────────────────────────────────────────────────────────────

    /** The job's name: the artisan command with its arguments, or the description of a job/callback. */
    public static function key(Event $event): string
    {
        $command = (string) ($event->command ?? '');
        if ($command !== '') {
            $at = strrpos($command, 'artisan');
            $name = $at === false ? $command : substr($command, $at + strlen('artisan'));
            $name = trim(str_replace(["'", '"'], '', $name));
            $name = (string) preg_replace('/\s+>\s+.*$/', '', $name); // output redirection is not part of the name

            return mb_substr($name !== '' ? $name : $command, 0, 191);
        }

        return mb_substr((string) ($event->description ?: 'callback'), 0, 191);
    }

    /** Twice the longest gap between two runs of the schedule, plus slack, in seconds. */
    public static function budgetSeconds(Event $event): int
    {
        $cron = new CronExpression($event->expression);
        $timezone = $event->timezone instanceof \DateTimeZone ? $event->timezone->getName() : (string) ($event->timezone ?: 'UTC');
        $at = CarbonImmutable::parse(self::REFERENCE, 'UTC')->setTimezone($timezone);
        $runs = [];
        for ($i = 0; $i < self::SAMPLES; $i++) {
            $at = CarbonImmutable::instance($cron->getNextRunDate($at, 0, false, $timezone));
            $runs[] = $at->getTimestamp();
        }
        $gap = 0;
        for ($i = 1; $i < count($runs); $i++) {
            $gap = max($gap, $runs[$i] - $runs[$i - 1]);
        }

        return 2 * $gap + self::SLACK_SECONDS;
    }

    /** @return array<string, int> job => budget in seconds (the shorter budget when a job is scheduled more than once) */
    public function budgets(): array
    {
        $events = app(Schedule::class)->events();
        if ($events === []) { // a process without the schedule (PHP-FPM): what the scheduler last saw, or nothing before its first tick
            $cached = Cache::get(self::BUDGETS_CACHE_KEY);

            return is_array($cached) && is_array($cached['budgets'] ?? null) ? array_map('intval', $cached['budgets']) : [];
        }
        $out = [];
        foreach ($events as $event) {
            $key = self::key($event);
            $budget = self::budgetSeconds($event);
            $out[$key] = isset($out[$key]) ? min($out[$key], $budget) : $budget; // scheduled twice (fx:sync): either run counts
        }
        ksort($out);

        return $out;
    }

    /**
     * Every scheduled job with its budget, last success and how long ago it last ran (success or skip).
     *
     * @return list<array{command: string, budget: int, last_success_at: ?string, age: ?int, overdue: bool, failures_in_a_row: int}>
     */
    public function jobs(): array
    {
        $rows = DB::table('scheduled_job_runs')->get()->keyBy('command');
        $started = DB::table('scheduled_job_runs')->min('created_at');
        $trackedFor = $started === null ? null : (int) CarbonImmutable::parse((string) $started)->diffInSeconds(now(), true);
        $out = [];
        foreach ($this->budgets() as $command => $budget) {
            $row = $rows->get($command);
            $seen = array_filter([$row?->last_success_at, $row?->last_skipped_at]);
            $last = $seen === [] ? null : max(array_map(fn ($t) => CarbonImmutable::parse((string) $t)->getTimestamp(), $seen));
            $age = $last === null ? null : max(0, now()->getTimestamp() - $last);
            $overdue = $age !== null ? $age > $budget : ($trackedFor !== null && $trackedFor > $budget);
            $out[] = ['command' => $command, 'budget' => $budget, 'last_success_at' => $row?->last_success_at, 'age' => $age, 'overdue' => $overdue,
                'failures_in_a_row' => (int) ($row->failures_in_a_row ?? 0)];
        }

        return $out;
    }

    /** @return list<array{area: string, check: string, ok: bool, detail: string, blocking: bool, remedy: string}> */
    public function checks(): array
    {
        $jobs = $this->jobs();
        $overdue = array_values(array_filter($jobs, fn (array $j) => $j['overdue']));
        if (DB::table('scheduled_job_runs')->doesntExist()) {
            return [$this->row(true, count($jobs).' jobs scheduled; no run recorded yet (the scheduler writes the first within a minute)')];
        }
        if ($overdue === []) {
            return [$this->row(true, count($jobs).' jobs, every one within its budget')];
        }
        $named = array_map(fn (array $j) => $j['command'].' ('.($j['age'] === null ? 'never ran' : 'last '.self::human($j['age']).' ago').', budget '.self::human($j['budget'])
            .($j['failures_in_a_row'] > 0 ? ', failed '.$j['failures_in_a_row'].'× in a row' : '').')', array_slice($overdue, 0, 5));

        return [$this->row(false, count($overdue).' of '.count($jobs).' past their budget: '.implode('; ', $named).(count($overdue) > 5 ? '; …' : ''),
            'run the job by hand (php artisan <command>) and read its error; storage/logs and `journalctl -u onhost-scheduler`')];
    }

    private static function human(int $seconds): string
    {
        return match (true) {
            $seconds >= 86400 => round($seconds / 86400, 1).' d',
            $seconds >= 3600 => round($seconds / 3600, 1).' h',
            $seconds >= 60 => (int) round($seconds / 60).' min',
            default => $seconds.' s',
        };
    }

    /** @return array{area: string, check: string, ok: bool, detail: string, blocking: bool, remedy: string} */
    private function row(bool $ok, string $detail, string $remedy = ''): array
    {
        return ['area' => 'automation', 'check' => self::CHECK, 'ok' => $ok, 'detail' => $detail, 'blocking' => false, 'remedy' => $remedy];
    }
}
