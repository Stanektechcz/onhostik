<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P14 (phase Q): the last run of every scheduled job. The scheduler's own events (starting, finished, failed, skipped) write one
 * row per job (`Onhost\Domain\Platform\ScheduleHealth`); the doctor row "every scheduled job ran within its budget" and the metric
 * `onhost_schedule_last_success_seconds{command}` read it. Before this table the doctor knew only that the scheduler ticked
 * (provisioning tick) — a daily job that failed every night (backup verify, FX rates, dunning) stayed invisible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_job_runs', function (Blueprint $table): void {
            $table->string('command', 191)->primary(); // the job as ScheduleHealth::key() names it (artisan command with its arguments, or the job class)
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('last_skipped_at')->nullable(); // a `when()` condition said no: the job was looked at, nothing was due
            $table->integer('last_exit_code')->nullable();
            $table->unsignedInteger('failures_in_a_row')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_job_runs');
    }
};
