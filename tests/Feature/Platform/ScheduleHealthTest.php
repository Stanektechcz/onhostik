<?php

declare(strict_types=1);

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Platform\ScheduleHealth;

/*
 * P14 (phase Q): every scheduled job records its last success and has an age budget (twice the longest gap of its schedule plus ten
 * minutes). The doctor row "every scheduled job ran within its budget" and the metric onhost_schedule_last_success_seconds read it.
 * The snapshot pins every job's budget: a new scheduled job changes it, so its budget is a conscious line in a review, not an accident.
 */

function p14Task(string $command): ScheduledEvent
{
    $event = collect(app(Schedule::class)->events())->first(fn (ScheduledEvent $e) => ScheduleHealth::key($e) === $command);
    expect($event)->not->toBeNull("{$command} is scheduled");

    return $event;
}

function p14Row(): array
{
    return collect(app(ScheduleHealth::class)->checks())->firstWhere('check', ScheduleHealth::CHECK);
}

it('gives every scheduled job a budget and pins them in a snapshot', function () {
    $budgets = app(ScheduleHealth::class)->budgets();
    $snapshot = base_path('tests/Feature/Platform/schedule-budgets-snapshot.json');
    expect(count($budgets))->toBeGreaterThan(80)
        ->and(collect($budgets)->every(fn (int $b) => $b >= 600 + 120))->toBeTrue()
        ->and($budgets['onhost:outbox:relay'])->toBe(2 * 60 + 600)
        ->and($budgets['onhost:platform:backup:verify'])->toBe(2 * 86400 + 600)
        ->and($budgets['onhost:digest:weekly'])->toBe(2 * 7 * 86400 + 600);
    $json = json_encode($budgets, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    expect(is_file($snapshot))->toBeTrue("missing snapshot; it would be:\n{$json}")
        ->and((string) file_get_contents($snapshot))->toBe($json, 'a scheduled job or its schedule changed: update tests/Feature/Platform/schedule-budgets-snapshot.json');
});

it('records start, success, failure and skip from the scheduler events and never fails the job', function () {
    $task = p14Task('onhost:outbox:relay');
    $this->travelTo(Carbon::parse('2026-10-20 10:00:00', 'UTC'));
    event(new ScheduledTaskStarting($task));
    $task->exitCode = 0;
    event(new ScheduledTaskFinished($task, 0.2));
    $row = DB::table('scheduled_job_runs')->where('command', 'onhost:outbox:relay')->first();
    expect($row->last_success_at)->not->toBeNull()->and((int) $row->last_exit_code)->toBe(0)->and((int) $row->failures_in_a_row)->toBe(0);

    $task->exitCode = 1;
    event(new ScheduledTaskFinished($task, 0.2));
    event(new ScheduledTaskFailed($task, new RuntimeException('boom')));
    $row = DB::table('scheduled_job_runs')->where('command', 'onhost:outbox:relay')->first();
    expect((int) $row->failures_in_a_row)->toBe(2)->and($row->last_failure_at)->not->toBeNull();

    event(new ScheduledTaskSkipped($task));
    expect(DB::table('scheduled_job_runs')->where('command', 'onhost:outbox:relay')->value('last_skipped_at'))->not->toBeNull();
});

it('says fresh, past budget and never ran — a fresh deploy is not a warning', function () {
    $this->travelTo(Carbon::parse('2026-10-20 10:00:00', 'UTC'));
    expect(p14Row()['ok'])->toBeTrue()->and(p14Row()['detail'])->toContain('no run recorded yet');

    // the scheduler has just started: every-minute jobs ran, the nightly ones never did — fine for now
    foreach (app(ScheduleHealth::class)->budgets() as $command => $budget) {
        if ($budget < 3600) {
            DB::table('scheduled_job_runs')->insert(['command' => $command, 'last_success_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
    }
    expect(p14Row()['ok'])->toBeTrue()->and(p14Row()['detail'])->toContain('every one within its budget');

    // three days on, nothing more ran: the nightly backup verify never ran, the outbox relay stopped
    $this->travelTo(Carbon::parse('2026-10-23 10:30:00', 'UTC'));
    $row = p14Row();
    expect($row['ok'])->toBeFalse()->and($row['blocking'])->toBeFalse()
        ->and($row['detail'])->toContain('past their budget')->toContain('never ran')->toContain('budget')
        ->and($row['remedy'])->toContain('php artisan');
    $jobs = collect(app(ScheduleHealth::class)->jobs())->keyBy('command');
    expect($jobs['onhost:platform:backup:verify']['overdue'])->toBeTrue()->and($jobs['onhost:platform:backup:verify']['age'])->toBeNull()
        ->and($jobs['onhost:outbox:relay']['overdue'])->toBeTrue()->and($jobs['onhost:outbox:relay']['age'])->toBe(3 * 86400 + 1800);

    // a skip counts as looked at (a when() condition said no)
    DB::table('scheduled_job_runs')->where('command', 'onhost:outbox:relay')->update(['last_skipped_at' => now()]);
    expect(collect(app(ScheduleHealth::class)->jobs())->firstWhere('command', 'onhost:outbox:relay')['overdue'])->toBeFalse();
});

it('exports the age and the budget of every job in /metrics', function () {
    DB::table('scheduled_job_runs')->insert(['command' => 'onhost:outbox:relay', 'last_success_at' => now()->subSeconds(30), 'created_at' => now(), 'updated_at' => now()]);
    $body = (string) $this->get('/metrics', ['REMOTE_ADDR' => '127.0.0.1'])->assertOk()->getContent();
    expect($body)->toContain('# TYPE onhost_schedule_last_success_seconds gauge')
        ->toMatch('/onhost_schedule_last_success_seconds\{command="onhost:outbox:relay"\} (29|30|31)/')
        ->toContain('onhost_schedule_budget_seconds{command="onhost:outbox:relay"} 720')
        ->toContain('onhost_schedule_last_success_seconds{command="onhost:platform:backup"} -1');
});

it('prints the row in the doctor, a warning and never a failure', function () {
    Artisan::call('onhost:doctor', ['--json' => true]);
    $row = collect(json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'])->firstWhere('check', ScheduleHealth::CHECK);
    expect($row['area'])->toBe('automation')->and($row['status'])->toBe('OK');

    DB::table('scheduled_job_runs')->insert(['command' => 'onhost:outbox:relay', 'last_success_at' => now()->subDays(3), 'created_at' => now()->subDays(3), 'updated_at' => now()]);
    config(['app.env' => 'production']);
    app()->detectEnvironment(fn () => 'production');
    Artisan::call('onhost:doctor', ['--json' => true]);
    $row = collect(json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'])->firstWhere('check', ScheduleHealth::CHECK);
    expect($row['status'])->toBe('WARN')->and($row['detail'])->toContain('past their budget');
});
