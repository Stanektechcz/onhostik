<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bulk_customer_emails')) {
            Schema::create('bulk_customer_emails', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->string('subject', 255);
                $table->text('body_html');
                $table->text('body_text')->nullable();
                $table->json('filters');             // {segment, country_code, tag_id, has_overdue}
                $table->string('status', 20)->default('draft');
                $table->unsignedInteger('recipients_count')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_customer_emails');
    }
};
