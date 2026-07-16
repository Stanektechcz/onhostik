<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bulk_email_campaigns')) {
            Schema::create('bulk_email_campaigns', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->string('subject', 255);
                $table->text('body_html');
                $table->string('target_segment', 50)->nullable();
                $table->string('target_country', 10)->nullable();
                $table->enum('status', ['draft', 'sending', 'sent', 'failed'])->default('draft');
                $table->unsignedInteger('recipient_count')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_email_campaigns');
    }
};
