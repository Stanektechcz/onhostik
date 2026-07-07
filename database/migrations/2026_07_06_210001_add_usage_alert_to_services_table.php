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
            $table->unsignedTinyInteger('usage_alert_threshold')->default(80)->after('billing_paused_until');
            $table->timestamp('usage_alert_sent_at')->nullable()->after('usage_alert_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['usage_alert_threshold', 'usage_alert_sent_at']);
        });
    }
};
