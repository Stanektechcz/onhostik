<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->unsignedSmallInteger('cpu_limit_percent')->nullable()->after('usage_snapshot');
            $table->unsignedInteger('ram_limit_mb')->nullable()->after('cpu_limit_percent');
            $table->unsignedInteger('disk_limit_gb')->nullable()->after('ram_limit_mb');
            $table->unsignedInteger('bandwidth_limit_gb')->nullable()->after('disk_limit_gb');

            $table->unsignedSmallInteger('cpu_usage_percent')->nullable()->after('bandwidth_limit_gb');
            $table->unsignedInteger('ram_usage_mb')->nullable()->after('cpu_usage_percent');
            $table->unsignedInteger('disk_usage_gb')->nullable()->after('ram_usage_mb');
            $table->unsignedInteger('bandwidth_usage_gb')->nullable()->after('disk_usage_gb');

            $table->unsignedSmallInteger('resource_alert_threshold')->default(80)->after('bandwidth_usage_gb');
            $table->timestamp('last_resource_check_at')->nullable()->after('resource_alert_threshold');
        });

        if (!Schema::hasTable('service_resource_usages')) {
            Schema::create('service_resource_usages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('service_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('cpu_percent')->nullable();
                $table->unsignedInteger('ram_mb')->nullable();
                $table->unsignedInteger('disk_gb')->nullable();
                $table->unsignedInteger('bandwidth_gb')->nullable();
                $table->timestamp('recorded_at');
                $table->timestamps();

                $table->index(['service_id', 'recorded_at']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('service_resource_usages');
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn([
                'cpu_limit_percent', 'ram_limit_mb', 'disk_limit_gb', 'bandwidth_limit_gb',
                'cpu_usage_percent', 'ram_usage_mb', 'disk_usage_gb', 'bandwidth_usage_gb',
                'resource_alert_threshold', 'last_resource_check_at',
            ]);
        });
    }
};
