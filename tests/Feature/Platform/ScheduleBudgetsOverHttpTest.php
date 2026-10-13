<?php

declare(strict_types=1);

use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Onhost\Domain\Platform\ScheduleHealth;

/*
 * S3 (phase S, found on the staging deploy of fda0cedf on 2026-10-13): `/metrics` printed one sample,
 * `onhost_schedule_last_success_seconds{command="none"}`, while `scheduled_job_runs` already held rows and the doctor counted
 * 92 jobs. The schedule lives in routes/console.php, which only the console kernel loads; PHP-FPM serving `/metrics` never
 * loads it, so `Schedule::events()` is empty there and the Prometheus alert OnhostScheduledJobOverdue could never fire.
 * The tests run under the console kernel, which is why P14's own test saw every job. Here the web process is imitated by an
 * empty Schedule: the budgets the scheduler itself last saw must be what `/metrics` exports.
 */

function s3Task(string $command): ScheduledEvent
{
    $event = collect(app(Schedule::class)->events())->first(fn (ScheduledEvent $e) => ScheduleHealth::key($e) === $command);
    expect($event)->not->toBeNull("{$command} is scheduled");

    return $event;
}

/** The web process: no console kernel ever loaded routes/console.php, so the Schedule it resolves is empty. */
function s3AsWebProcess(): void
{
    app()->instance(Schedule::class, new Schedule);
    Facade::clearResolvedInstance(Schedule::class);
}

function s3Metrics(object $test): string
{
    return (string) $test->get('/metrics', ['REMOTE_ADDR' => '127.0.0.1'])->assertOk()->getContent();
}

beforeEach(fn () => Cache::forget(ScheduleHealth::BUDGETS_CACHE_KEY));

it('exports every job budget in the web process once the scheduler has ticked', function () {
    $console = app(ScheduleHealth::class)->budgets();
    expect(count($console))->toBeGreaterThan(80);

    event(new ScheduledTaskStarting(s3Task('onhost:outbox:relay'))); // one scheduler tick (console: the schedule is loaded)
    DB::table('scheduled_job_runs')->where('command', 'onhost:outbox:relay')->update(['last_success_at' => now()->subSeconds(30)]);

    s3AsWebProcess();
    expect(app(Schedule::class)->events())->toBe([])
        ->and(app(ScheduleHealth::class)->budgets())->toBe($console);

    $body = s3Metrics($this);
    expect($body)->not->toContain('command="none"')
        ->toMatch('/onhost_schedule_last_success_seconds\{command="onhost:outbox:relay"\} (29|30|31)/')
        ->toContain('onhost_schedule_budget_seconds{command="onhost:outbox:relay"} 720')
        ->toContain('onhost_schedule_last_success_seconds{command="onhost:platform:backup"} -1')
        ->toContain('onhost_schedule_budget_seconds{command="onhost:platform:backup:verify"} '.$console['onhost:platform:backup:verify']);
});

it('says none, not a made-up list, before the scheduler has ever ticked', function () {
    s3AsWebProcess();
    $body = s3Metrics($this);
    expect($body)->toContain('onhost_schedule_last_success_seconds{command="none"} -1')
        ->not->toContain('command="onhost:outbox:relay"');
});

it('writes the budgets at most every ten minutes and never fails the job when it cannot', function () {
    $task = s3Task('onhost:outbox:relay');
    $this->travelTo(now()->startOfMinute());
    event(new ScheduledTaskStarting($task));
    $first = Cache::get(ScheduleHealth::BUDGETS_CACHE_KEY);
    expect($first['written_at'])->toBe(now()->getTimestamp())->and($first['budgets'])->toHaveKey('onhost:outbox:relay');

    $this->travel(5)->minutes();
    event(new ScheduledTaskStarting($task));
    expect(Cache::get(ScheduleHealth::BUDGETS_CACHE_KEY)['written_at'])->toBe($first['written_at']);

    $this->travel(6)->minutes();
    event(new ScheduledTaskStarting($task));
    expect(Cache::get(ScheduleHealth::BUDGETS_CACHE_KEY)['written_at'])->toBe(now()->getTimestamp());

    Cache::shouldReceive('get')->andThrow(new RuntimeException('cache store down'));
    event(new ScheduledTaskStarting($task)); // bookkeeping only: the job itself still starts
    expect(DB::table('scheduled_job_runs')->where('command', 'onhost:outbox:relay')->value('last_started_at'))->not->toBeNull();
});
