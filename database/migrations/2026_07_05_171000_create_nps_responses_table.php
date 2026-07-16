<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nps_responses')) {
            Schema::create('nps_responses', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
                $table->tinyInteger('score')->nullable();     // 0-10; null = notified but not yet submitted
                $table->text('comment')->nullable();
                $table->string('survey_token', 64)->unique(); // signed URL token
                $table->timestamp('notified_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('nps_responses');
    }
};
