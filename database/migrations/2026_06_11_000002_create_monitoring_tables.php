<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('http');       // http | ping | ssl
            $table->string('target');                      // url / hostname
            $table->string('provider')->default('internal_mock');
            $table->string('status')->default('unknown');  // up | down | unknown | paused
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_check_at')->nullable();
            $table->decimal('uptime_percent', 5, 2)->nullable();
            $table->date('ssl_expires_at')->nullable();
            $table->string('external_id')->nullable();     // provider-side id (Uptime Kuma…)
            $table->timestamps();

            $table->index(['status', 'is_active']);
        });

        Schema::create('monitor_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->string('status');                      // up | down
            $table->unsignedInteger('response_ms')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('checked_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['monitor_id', 'checked_at']);
        });

        Schema::create('monitor_incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->string('severity')->default('minor');  // minor | major
            $table->string('reason', 500);
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['monitor_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_incidents');
        Schema::dropIfExists('monitor_checks');
        Schema::dropIfExists('monitors');
    }
};
