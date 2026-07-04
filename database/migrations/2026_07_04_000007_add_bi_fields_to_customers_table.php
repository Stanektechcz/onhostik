<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->unsignedTinyInteger('churn_risk_score')->nullable()->after('admin_notes');
            $table->string('segment', 20)->nullable()->after('churn_risk_score');
            $table->timestamp('insights_updated_at')->nullable()->after('segment');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['churn_risk_score', 'segment', 'insights_updated_at']);
        });
    }
};
