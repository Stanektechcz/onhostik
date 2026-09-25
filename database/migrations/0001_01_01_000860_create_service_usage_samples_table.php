<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every usage reading the platform takes, kept (owner decisions 9 and 12, TASK-0023 metering-core).
 *
 * Until now a reading lived only as the last snapshot in `services.tags.usage`, and a number the panel could not give was
 * written there as 0 — "0 % used, fine". Here `value` is nullable and null means **not measured**, never 0; `quality`
 * says whether a number was measured, estimated from part of the answer, or unavailable, and `reason` carries only an
 * error code, never a vendor message. Raw readings (`granularity = sample`, one per service, metric and hour) are
 * rolled into days and months by `UsageRollup` and pruned by the owner's retention (raw 45 days, daily 400 days,
 * monthly for ever). This table is telemetry only: `usage_events` stays the billing table and never sees a sample.
 *
 * `services.usage_checked_at` lets the watch visit the service measured longest ago first, so it can go round every
 * service instead of the first 200 (behind the default-off automation rule `usage.rotation`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_usage_samples', function (Blueprint $table): void {
            $table->id();
            $table->string('service_id', 40);
            $table->string('organization_id', 40);
            $table->string('provider_instance_id', 40)->nullable();
            $table->string('metric', 40);
            $table->string('granularity', 8); // sample | day | month
            $table->timestamp('window_start');
            $table->timestamp('window_end');
            $table->unsignedBigInteger('value')->nullable(); // null = not measured, never 0
            $table->string('unit', 16);
            $table->unsignedBigInteger('limit_value')->nullable(); // the plan's limit at the time; null = none known
            $table->string('limit_kind', 8); // hard | soft | none (MetricRegistry)
            $table->string('quality', 12); // measured | estimated | unavailable
            $table->string('reason', 60)->nullable(); // an error code only, never a vendor message
            $table->string('source', 40)->nullable();
            $table->string('scope', 8)->default('service');
            $table->unsignedInteger('samples_total')->default(1);
            $table->unsignedInteger('samples_measured')->default(1);
            $table->timestamp('observed_at');
            $table->string('dedupe_key', 200)->unique();
            $table->timestamp('created_at')->nullable();
            $table->index(['service_id', 'metric', 'granularity', 'window_start']);
            $table->index(['granularity', 'window_start']);
        });
        Schema::table('services', function (Blueprint $table): void {
            $table->timestamp('usage_checked_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropIndex(['usage_checked_at']);
            $table->dropColumn('usage_checked_at');
        });
        Schema::dropIfExists('service_usage_samples');
    }
};
