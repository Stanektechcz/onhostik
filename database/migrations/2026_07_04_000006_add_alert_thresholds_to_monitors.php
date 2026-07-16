<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table): void {
            $table->unsignedInteger('response_time_threshold_ms')->nullable()->after('external_id');
            $table->decimal('uptime_threshold_percent', 5, 2)->nullable()->after('response_time_threshold_ms');
            $table->unsignedSmallInteger('ssl_warn_days')->default(30)->after('uptime_threshold_percent');
        });

        if (!Schema::hasTable('monitor_alerts')) {
            Schema::create('monitor_alerts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
                $table->string('type');                           // response_time | uptime | ssl_expiry
                $table->decimal('threshold_value', 10, 2);
                $table->decimal('current_value', 10, 2);
                $table->timestamp('triggered_at');
                $table->timestamp('notified_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->index(['monitor_id', 'type', 'resolved_at']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_alerts');

        Schema::table('monitors', function (Blueprint $table): void {
            $table->dropColumn(['response_time_threshold_ms', 'uptime_threshold_percent', 'ssl_warn_days']);
        });
    }
};
