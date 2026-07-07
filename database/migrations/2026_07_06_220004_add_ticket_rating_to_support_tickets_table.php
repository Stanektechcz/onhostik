<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->tinyInteger('csat_score')->nullable()->after('status');
            $table->text('csat_comment')->nullable()->after('csat_score');
            $table->timestamp('csat_rated_at')->nullable()->after('csat_comment');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropColumn(['csat_score', 'csat_comment', 'csat_rated_at']);
        });
    }
};
