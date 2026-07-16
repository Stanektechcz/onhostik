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
            // health_score_updated_at is added later (2026_07_06_170000) —
            // position hint dropped, it's purely cosmetic.
            $table->json('credit_auto_topup')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('credit_auto_topup');
        });
    }
};
