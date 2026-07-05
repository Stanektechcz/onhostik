<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_tiers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedTinyInteger('uptime_percent_x100'); // 9999 = 99.99%, 9990 = 99.9%
            $table->unsignedInteger('response_time_minutes');
            $table->unsignedInteger('resolution_time_hours');
            $table->unsignedInteger('credit_percent_per_hour'); // credit % per hour excess downtime
            $table->unsignedInteger('max_credit_percent');       // monthly cap
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sla_uptime_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('status');                // up | down | degraded | unknown
            $table->unsignedSmallInteger('response_ms')->nullable();
            $table->string('check_url')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['service_id', 'checked_at']);
        });

        Schema::create('sla_incidents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('severity');              // critical | high | medium | low
            $table->string('status');                // open | investigating | resolved
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('downtime_minutes')->default(0);
            $table->boolean('sla_breached')->default(false);
            $table->unsignedInteger('credit_haler')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['service_id', 'started_at']);
        });

        Schema::create('sla_incident_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_id')->constrained('sla_incidents')->cascadeOnDelete();
            $table->text('message');
            $table->string('status');                // investigating | identified | monitoring | resolved
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->foreignId('sla_tier_id')->nullable()->constrained('sla_tiers')->nullOnDelete();
            $table->boolean('sla_monitoring_enabled')->default(false);
            $table->string('sla_check_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropForeign(['sla_tier_id']);
            $table->dropColumn(['sla_tier_id', 'sla_monitoring_enabled', 'sla_check_url']);
        });
        Schema::dropIfExists('sla_incident_updates');
        Schema::dropIfExists('sla_incidents');
        Schema::dropIfExists('sla_uptime_checks');
        Schema::dropIfExists('sla_tiers');
    }
};
