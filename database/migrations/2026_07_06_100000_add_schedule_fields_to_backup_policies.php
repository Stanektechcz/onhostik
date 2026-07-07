<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_policies', function (Blueprint $table): void {
            // Hour of the day (0–23) when the backup should run (UTC).
            $table->tinyInteger('scheduled_hour')->unsigned()->default(3)->after('frequency');
            // Day of the week (0=Mon … 6=Sun) for weekly policies; ignored for daily.
            $table->tinyInteger('scheduled_weekday')->unsigned()->nullable()->after('scheduled_hour');
            $table->boolean('notify_on_failure')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('backup_policies', function (Blueprint $table): void {
            $table->dropColumn(['scheduled_hour', 'scheduled_weekday', 'notify_on_failure']);
        });
    }
};
