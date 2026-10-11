<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An alert raised again while its first one is still open is one alert seen N times, not N rows: the row counts its
 * occurrences and remembers when it was last raised.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oncall_alerts', function (Blueprint $table): void {
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('last_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('oncall_alerts', function (Blueprint $table): void {
            $table->dropColumn(['occurrences', 'last_seen_at']);
        });
    }
};
