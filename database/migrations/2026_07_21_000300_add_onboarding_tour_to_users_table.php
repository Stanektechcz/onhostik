<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * First-login product tour completion flag.
 *
 * NULL = the customer has not seen (or dismissed) the guided panel tour yet;
 * set once they finish or skip it, so it never runs twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'onboarding_tour_completed_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('onboarding_tour_completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('onboarding_tour_completed_at');
        });
    }
};
