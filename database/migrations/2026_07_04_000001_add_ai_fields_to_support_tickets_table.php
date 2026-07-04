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
            $table->string('ai_classification', 32)->nullable()->after('sla_deadline');
            $table->string('ai_sentiment', 16)->nullable()->after('ai_classification');
            $table->text('ai_draft')->nullable()->after('ai_sentiment');
            $table->timestamp('ai_analysed_at')->nullable()->after('ai_draft');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropColumn(['ai_classification', 'ai_sentiment', 'ai_draft', 'ai_analysed_at']);
        });
    }
};
