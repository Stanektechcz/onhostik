<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('newsletter_campaigns', function (Blueprint $table): void {
            // all_subscribers | customers_all | customers_vip | customers_healthy
            // | customers_at_risk | customers_churned
            $table->string('target_audience', 40)->default('all_subscribers')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_campaigns', function (Blueprint $table): void {
            $table->dropColumn('target_audience');
        });
    }
};
