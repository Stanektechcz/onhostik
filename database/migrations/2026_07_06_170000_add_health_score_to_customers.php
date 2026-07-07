<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->unsignedTinyInteger('health_score')->nullable()->after('churn_risk_score');
            $table->timestamp('health_score_updated_at')->nullable()->after('health_score');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['health_score', 'health_score_updated_at']);
        });
    }
};
